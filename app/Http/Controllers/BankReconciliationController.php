<?php

namespace App\Http\Controllers;

use App\Models\{BankReconciliation, BankStatementTransaction, ChartOfAccount, Customer, Expense, Payment};
use App\Services\{AccountingService, BankReconciliationMatchingService, BankStatementParserService, NumberingService, ProofUploadService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller
{
    private function bankAccounts()
    {
        return ChartOfAccount::where('account_subtype', 'bank')->where('is_active', true)->get();
    }

    public function index()
    {
        $history = BankReconciliation::with('bankAccount', 'creator')->withCount('transactions')->latest('statement_month')->limit(20)->get();
        return view('finance.bank-reconciliation.index', ['bankAccounts' => $this->bankAccounts(), 'history' => $history]);
    }

    public function store(Request $request, BankStatementParserService $parser, ProofUploadService $files)
    {
        $data = $request->validate([
            'bank_account_id' => 'required|exists:chart_of_accounts,id',
            'statement_month' => 'required|date',
            'opening_balance' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
            'statement_file' => 'required|file|mimes:csv,xlsx,xls,pdf|max:10240',
        ]);

        $parsed = $parser->parse($request->file('statement_file'));
        $fileFields = $files->store($request->file('statement_file'), 'bank-statements', [
            'text/csv', 'text/plain', 'application/csv',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip',
            'application/pdf',
        ]);

        $reconciliation = DB::transaction(function () use ($data, $parsed, $fileFields) {
            $reconciliation = BankReconciliation::create([
                'bank_account_id' => $data['bank_account_id'],
                'statement_month' => \Illuminate\Support\Carbon::parse($data['statement_month'])->startOfMonth(),
                'opening_balance' => $data['opening_balance'] ?? null,
                'closing_balance' => $data['closing_balance'] ?? null,
                'created_by' => auth()->id(),
                'statement_file_path' => $fileFields['proof_path'] ?? null,
                'statement_original_name' => $fileFields['proof_original_name'] ?? null,
                'statement_mime' => $fileFields['proof_mime'] ?? null,
                'statement_size' => $fileFields['proof_size'] ?? null,
            ]);

            $totalCredits = 0.0;
            $totalDebits = 0.0;
            foreach ($parsed['rows'] as $row) {
                if (!$row['date']) continue; // skip rows with no usable date (e.g. an "opening balance" line)
                $totalCredits += $row['credit'];
                $totalDebits += $row['debit'];
                $reconciliation->transactions()->create([
                    'txn_date' => $row['date'],
                    'description' => $row['description'],
                    'bank_reference' => $row['bank_reference'],
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                    'amount' => $row['amount'],
                    'balance' => $row['balance'],
                ]);
            }
            $reconciliation->update(['total_credits' => round($totalCredits, 2), 'total_debits' => round($totalDebits, 2)]);
            return $reconciliation;
        });

        return redirect()->route('finance.bank-reconciliation.show', $reconciliation)
            ->with($parsed['parsed'] ? 'success' : 'warning', $parsed['parsed'] ? 'Statement uploaded and parsed.' : $parsed['message']);
    }

    public function show(BankReconciliation $reconciliation, BankReconciliationMatchingService $matcher)
    {
        // Re-run matching live every time the page loads, so newly
        // created/linked payments and expenses are reflected immediately
        // without a separate "re-match" step.
        foreach ($reconciliation->transactions as $txn) {
            $matcher->match($txn);
        }
        $reconciliation->refresh();

        $matchedCount = $reconciliation->transactions()->where('match_status', 'matched')->count();
        $possibleCount = $reconciliation->transactions()->where('match_status', 'possible_match')->count();
        $unmatchedIn = $reconciliation->transactions()->where('match_status', 'missing_in_erp')->where('amount', '>', 0)->count();
        $unmatchedOut = $reconciliation->transactions()->where('match_status', 'missing_in_erp')->where('amount', '<', 0)->count();
        $missingFromStatement = $matcher->findErpTransactionsMissingFromStatement($reconciliation);
        $missingCount = $missingFromStatement['payments']->count() + $missingFromStatement['expenses']->count() + $missingFromStatement['raw_material_purchases']->count();

        $status = ($possibleCount === 0 && $unmatchedIn === 0 && $unmatchedOut === 0 && $missingCount === 0 && $reconciliation->transactions()->count() > 0) ? 'reconciled' : 'needs_review';
        $reconciliation->update(['status' => $status, 'matched_count' => $matchedCount, 'unmatched_in_count' => $unmatchedIn, 'unmatched_out_count' => $unmatchedOut, 'missing_count' => $missingCount]);

        return view('finance.bank-reconciliation.show', [
            'reconciliation' => $reconciliation->fresh(['transactions', 'bankAccount']),
            'missingFromStatement' => $missingFromStatement,
            'customers' => Customer::orderBy('name')->limit(200)->get(),
        ]);
    }

    /** Money received, missing in ERP — creates a new, unallocated Payment (Customer Deposits) that staff can later allocate to a specific invoice. Never creates a duplicate for an already-matched line. */
    public function createPayment(Request $request, BankStatementTransaction $txn, NumberingService $numbers, AccountingService $accounting)
    {
        abort_unless(auth()->user()->hasPermission('accounting.manage'), 403);
        abort_if($txn->matched_id, 422, 'This bank line is already linked to a record.');
        $data = $request->validate(['customer_id' => 'required|exists:customers,id']);

        $amount = abs((float) $txn->amount);
        DB::transaction(function () use ($txn, $data, $numbers, $accounting, $amount) {
            $payment = Payment::create([
                'payment_number' => $numbers->next('payment'), 'customer_id' => $data['customer_id'], 'method' => 'bank_transfer',
                'amount' => $amount, 'payment_date' => $txn->txn_date, 'reference_number' => $txn->bank_reference, 'received_by' => auth()->id(),
            ]);
            $accounting->post($payment, "Bank deposit {$payment->payment_number} (unallocated)", [['account' => '1010', 'debit' => $amount, 'credit' => 0], ['account' => '2200', 'debit' => 0, 'credit' => $amount]], $txn->txn_date->toDateString());
            $txn->update(['match_status' => 'matched', 'matched_type' => 'payment', 'matched_id' => $payment->id]);
        });

        return back()->with('success', 'Payment created and linked.');
    }

    public function linkPayment(Request $request, BankStatementTransaction $txn)
    {
        abort_unless(auth()->user()->hasPermission('accounting.manage'), 403);
        $data = $request->validate(['payment_id' => 'required|exists:payments,id']);
        $txn->update(['match_status' => 'matched', 'matched_type' => 'payment', 'matched_id' => $data['payment_id']]);
        return back()->with('success', 'Linked to existing payment.');
    }

    /** Money spent, missing in ERP — creates a new Expense (payment_method=bank), automatically posted to the ledger, with the bank reference preserved for future reconciliations. */
    public function createExpense(Request $request, BankStatementTransaction $txn, NumberingService $numbers, AccountingService $accounting)
    {
        abort_unless(auth()->user()->hasPermission('accounting.manage'), 403);
        abort_if($txn->matched_id, 422, 'This bank line is already linked to a record.');
        $data = $request->validate(['category' => 'required|string|max:100', 'payee' => 'nullable|string|max:190']);

        $amount = abs((float) $txn->amount);
        DB::transaction(function () use ($txn, $data, $numbers, $accounting, $amount) {
            $expense = Expense::create([
                'expense_number' => $numbers->next('expense'), 'expense_date' => $txn->txn_date, 'category' => $data['category'], 'payee' => $data['payee'] ?? null,
                'payment_method' => 'bank', 'amount_ex_tax' => $amount, 'tax_amount' => 0, 'total_amount' => $amount, 'reference' => $txn->bank_reference,
                'description' => $txn->description, 'created_by' => auth()->id(),
            ]);
            $accounting->post($expense, "Bank expense {$expense->expense_number}", [['account' => '5100', 'debit' => $amount, 'credit' => 0], ['account' => '1010', 'debit' => 0, 'credit' => $amount]], $txn->txn_date->toDateString());
            $txn->update(['match_status' => 'matched', 'matched_type' => 'expense', 'matched_id' => $expense->id]);
        });

        return back()->with('success', 'Expense created and linked.');
    }

    public function linkExpense(Request $request, BankStatementTransaction $txn)
    {
        abort_unless(auth()->user()->hasPermission('accounting.manage'), 403);
        $data = $request->validate(['expense_id' => 'required|exists:expenses,id']);
        $txn->update(['match_status' => 'matched', 'matched_type' => 'expense', 'matched_id' => $data['expense_id']]);
        return back()->with('success', 'Linked to existing expense.');
    }

    /** The uploaded statement file itself — never publicly reachable, same secure-serving pattern as every other document in this app. */
    public function downloadStatement(BankReconciliation $reconciliation)
    {
        abort_unless(auth()->user()->hasPermission('accounting.view'), 403);
        abort_unless($reconciliation->statement_file_path, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($reconciliation->statement_file_path), 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($reconciliation->statement_file_path, $reconciliation->statement_original_name);
    }
}
