<?php

namespace App\Http\Controllers;

use App\Models\{CashAdjustment, CashReconciliation, ChartOfAccount};
use App\Services\{CashReconciliationService, NumberingService, ProofUploadService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashReconciliationController extends Controller
{
    private function cashAccounts()
    {
        return ChartOfAccount::whereIn('account_subtype', ['cash', 'petty_cash'])->where('is_active', true)->get();
    }

    public function index(Request $request, CashReconciliationService $service)
    {
        $cashAccounts = $this->cashAccounts();
        $selectedAccountId = $request->query('cash_account_id', $cashAccounts->first()?->id);
        $selectedAccount = $cashAccounts->firstWhere('id', (int) $selectedAccountId);

        $preview = null;
        if ($selectedAccount) {
            $preview = $service->compute($selectedAccount, $request->query('reconciliation_date', today()->toDateString()));
        }

        $recentAdjustments = CashAdjustment::with('cashAccount', 'creator')->latest('adjustment_date')->limit(20)->get();
        $history = CashReconciliation::with('cashAccount', 'creator')->latest('reconciliation_date')->limit(20)->get();

        return view('finance.cash-reconciliation', [
            'cashAccounts' => $cashAccounts,
            'selectedAccount' => $selectedAccount,
            'preview' => $preview,
            'reconciliationDate' => $request->query('reconciliation_date', today()->toDateString()),
            'recentAdjustments' => $recentAdjustments,
            'history' => $history,
        ]);
    }

    /** Records the reconciliation snapshot — expected vs physical count. Never modifies any existing payment/expense/adjustment record. */
    public function store(Request $request, CashReconciliationService $service)
    {
        $data = $request->validate([
            'cash_account_id' => 'required|exists:chart_of_accounts,id',
            'reconciliation_date' => 'required|date',
            'physical_cash_count' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $account = ChartOfAccount::findOrFail($data['cash_account_id']);
        $computed = $service->compute($account, $data['reconciliation_date']);

        $difference = null;
        if (isset($data['physical_cash_count']) && $data['physical_cash_count'] !== null) {
            $difference = round((float) $data['physical_cash_count'] - $computed['expected_cash'], 2);
        }

        $reconciliation = CashReconciliation::create([
            'cash_account_id' => $account->id,
            'reconciliation_date' => $data['reconciliation_date'],
            'opening_cash' => $computed['opening_cash'],
            'cash_in' => $computed['cash_in'],
            'cash_out' => $computed['cash_out'],
            'expected_cash' => $computed['expected_cash'],
            'physical_cash_count' => $data['physical_cash_count'] ?? null,
            'difference' => $difference,
            'status' => 'reviewed',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $message = ($difference !== null && round($difference, 2) !== 0.0)
            ? "Reconciliation saved. Cash difference requires review (AED ".number_format($difference, 2).")."
            : 'Reconciliation saved.';

        return redirect()->route('finance.cash-reconciliation')->with($difference !== null && round($difference, 2) !== 0.0 ? 'warning' : 'success', $message);
    }

    /** Owner/Admin records a manual cash-in or cash-out adjustment, with an auto-generated CR-/CP- reference and optional proof. Full audit trail via the standard created_by + timestamps. */
    public function storeAdjustment(Request $request, NumberingService $numbers, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('accounting.manage'), 403);

        $data = $request->validate([
            'cash_account_id' => 'required|exists:chart_of_accounts,id',
            'type' => 'required|in:'.implode(',', array_keys(CashAdjustment::TYPES)),
            'direction' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'adjustment_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ]);

        $account = ChartOfAccount::findOrFail($data['cash_account_id']);
        $reference = $numbers->next($data['direction'] === 'in' ? 'cash_receipt' : 'cash_payment');

        $proofFields = [];
        if ($request->hasFile('proof')) {
            $proofFields = $proofs->store($request->file('proof'), 'cash-adjustment-proofs');
        }

        DB::transaction(function () use ($data, $reference, $account, $proofFields) {
            $adjustment = CashAdjustment::create([
                'reference' => $reference,
                'cash_account_id' => $account->id,
                'type' => $data['type'],
                'direction' => $data['direction'],
                'amount' => $data['amount'],
                'adjustment_date' => $data['adjustment_date'],
                'reason' => $data['reason'],
                'created_by' => auth()->id(),
            ] + $proofFields);

            // Post to the ledger so the cash account's real balance — and
            // every reconciliation computed from it — reflects this
            // adjustment. A single clearing account absorbs the other
            // side; this never touches or modifies any existing
            // transaction, it only ever adds a new one.
            $clearingCode = '3900';
            $lines = $data['direction'] === 'in'
                ? [['account' => $account->code, 'debit' => (float) $data['amount'], 'credit' => 0], ['account' => $clearingCode, 'debit' => 0, 'credit' => (float) $data['amount']]]
                : [['account' => $clearingCode, 'debit' => (float) $data['amount'], 'credit' => 0], ['account' => $account->code, 'debit' => 0, 'credit' => (float) $data['amount']]];
            app(\App\Services\AccountingService::class)->post($adjustment, "Cash adjustment {$reference}: {$data['reason']}", $lines, $data['adjustment_date']);
        });

        return back()->with('success', "Adjustment {$reference} recorded.");
    }
}
