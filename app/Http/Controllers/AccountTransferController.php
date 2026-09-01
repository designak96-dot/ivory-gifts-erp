<?php

namespace App\Http\Controllers;

use App\Models\{AccountTransfer, ChartOfAccount};
use App\Services\{AccountingService, NumberingService, ProofUploadService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountTransferController extends Controller
{
    /** Transfers are only ever between real money accounts — never card (which nets against a payable, not cash-in-hand). */
    private const ALLOWED_SUBTYPES = ['cash', 'bank', 'petty_cash'];

    private function transferAccounts()
    {
        return ChartOfAccount::whereIn('account_subtype', self::ALLOWED_SUBTYPES)->where('is_active', true)->orderBy('code')->get();
    }

    public function index()
    {
        $transfers = AccountTransfer::with('fromAccount', 'toAccount', 'creator')->latest('transfer_date')->latest('id')->limit(30)->get();

        return view('finance.account-transfer', [
            'accounts' => $this->transferAccounts(),
            'transfers' => $transfers,
        ]);
    }

    /**
     * Moves money between two Cash/Bank/Petty Cash accounts via a single
     * balanced journal entry — credit the From account, debit the To
     * account (e.g. Cash → Bank deposit AED 1,500: Cash −1,500, Bank
     * +1,500). Both sides are always asset accounts, so this can never
     * create income, never create an expense, and never affects profit —
     * it only ever relocates funds already on the books. Because Cash
     * Reconciliation and Bank Reconciliation both compute directly off
     * the account's real ledger lines, a posted transfer feeds both
     * automatically, with no separate/parallel calculation to keep in
     * sync. Full audit trail via the standard Auditable/AuditLog trail
     * (BusinessModel) plus created_by + timestamps.
     */
    public function store(Request $request, NumberingService $numbers, ProofUploadService $proofs, AccountingService $accounting)
    {
        abort_unless(auth()->user()->hasPermission('accounting.manage'), 403);

        $data = $request->validate([
            'from_account_id' => 'required|exists:chart_of_accounts,id',
            'to_account_id' => 'required|different:from_account_id|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'reference' => 'nullable|string|max:190',
            'notes' => 'nullable|string',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ], [
            'to_account_id.different' => 'From and To account cannot be the same.',
        ]);

        $allowedIds = $this->transferAccounts()->pluck('id');
        if (!$allowedIds->contains((int) $data['from_account_id']) || !$allowedIds->contains((int) $data['to_account_id'])) {
            // Surfaced the same way as every other validation failure on
            // this page (via $errors->any() in the shared layout), rather
            // than a session('error') key the layout doesn't render.
            return back()->withInput()->withErrors(['from_account_id' => 'Transfers are only allowed between Cash, Bank and Petty Cash accounts.']);
        }

        $fromAccount = ChartOfAccount::findOrFail($data['from_account_id']);
        $toAccount = ChartOfAccount::findOrFail($data['to_account_id']);

        $proofFields = [];
        if ($request->hasFile('proof')) {
            $proofFields = $proofs->store($request->file('proof'), 'account-transfer-proofs');
        }

        $transferNumber = DB::transaction(function () use ($data, $numbers, $accounting, $fromAccount, $toAccount, $proofFields) {
            $transferNumber = $numbers->next('account_transfer');
            $amount = (float) $data['amount'];

            $transfer = AccountTransfer::create([
                'transfer_number' => $transferNumber,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'amount' => $amount,
                'transfer_date' => $data['transfer_date'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ] + $proofFields);

            // Pure asset-to-asset movement: credit reduces the From
            // account, debit increases the To account. No income or
            // expense account is ever touched.
            $accounting->post(
                $transfer,
                "Account transfer {$transferNumber}: {$fromAccount->name} to {$toAccount->name}",
                [
                    ['account' => $fromAccount->code, 'debit' => 0, 'credit' => $amount],
                    ['account' => $toAccount->code, 'debit' => $amount, 'credit' => 0],
                ],
                $data['transfer_date']
            );

            return $transferNumber;
        });

        return redirect()->route('finance.account-transfer')->with('success', "Transfer {$transferNumber} recorded.");
    }
}
