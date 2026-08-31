<?php

namespace App\Services;

use App\Models\{BankStatementTransaction, Expense, Payment, RawMaterialPurchase};

/**
 * Matches each parsed bank statement line against real Payment/Expense/
 * RawMaterialPurchase records (bank method only — cash movements belong
 * to Cash Reconciliation, kept entirely separate). Priority order per
 * spec: 1) Bank Reference, 2) Amount, 3) Date, 4) direction,
 * 5) Customer/Payee/description — the strongest available signal
 * decides the match, never a vague average score.
 */
class BankReconciliationMatchingService
{
    private const DATE_WINDOW_DAYS = 3;

    public function match(BankStatementTransaction $txn): void
    {
        $isMoneyIn = $txn->amount > 0;

        // Priority 1: Bank Reference — exact match is decisive on its own.
        if ($txn->bank_reference) {
            if ($isMoneyIn) {
                $payment = Payment::whereNotIn('method', ['cash', 'cod'])->where('reference_number', $txn->bank_reference)->first();
                if ($payment) { $this->setMatch($txn, 'matched', 'payment', $payment->id); return; }
            } else {
                $expense = Expense::where('payment_method', 'bank')->where('reference', $txn->bank_reference)->first();
                if ($expense) { $this->setMatch($txn, 'matched', 'expense', $expense->id); return; }
                $rmPurchase = RawMaterialPurchase::where('payment_method', 'bank')->where('payment_reference', $txn->bank_reference)->first();
                if ($rmPurchase) { $this->setMatch($txn, 'matched', 'raw_material_purchase', $rmPurchase->id); return; }
            }
        }

        // Priority 2+3: Amount + Date window, correct direction — a confident but not reference-certain match.
        $absAmount = abs((float) $txn->amount);
        $dateFrom = $txn->txn_date->copy()->subDays(self::DATE_WINDOW_DAYS);
        $dateTo = $txn->txn_date->copy()->addDays(self::DATE_WINDOW_DAYS);

        if ($isMoneyIn) {
            $candidate = Payment::whereNotIn('method', ['cash', 'cod'])->whereBetween('amount', [$absAmount - 0.01, $absAmount + 0.01])
                ->whereDate('payment_date', '>=', $dateFrom)->whereDate('payment_date', '<=', $dateTo)->first();
            $candidateType = 'payment';
            $candidateDate = $candidate?->payment_date;
        } else {
            $candidate = Expense::where('payment_method', 'bank')->whereBetween('total_amount', [$absAmount - 0.01, $absAmount + 0.01])
                ->whereDate('expense_date', '>=', $dateFrom)->whereDate('expense_date', '<=', $dateTo)->first();
            $candidateType = 'expense';
            $candidateDate = $candidate?->expense_date;
            if (!$candidate) {
                $candidate = RawMaterialPurchase::where('payment_method', 'bank')->whereBetween('total_amount', [$absAmount - 0.01, $absAmount + 0.01])
                    ->whereDate('purchase_date', '>=', $dateFrom)->whereDate('purchase_date', '<=', $dateTo)->first();
                $candidateType = 'raw_material_purchase';
                $candidateDate = $candidate?->purchase_date;
            }
        }

        if ($candidate) {
            // Exact amount + same-day date is treated as Matched; amount
            // matched within the wider window but not same-day is a
            // Possible Match — real confidence, not a coin flip.
            $sameDay = $candidateDate->isSameDay($txn->txn_date);
            $status = $sameDay ? 'matched' : 'possible_match';
            $this->setMatch($txn, $status, $candidateType, $candidate->id);
            return;
        }

        // Priority 5: description/payee fuzzy match, amount-only fallback — weak signal, always Possible Match at best.
        if ($txn->description) {
            $needle = trim($txn->description);
            if ($needle !== '') {
                if ($isMoneyIn) {
                    $fuzzy = Payment::whereNotIn('method', ['cash', 'cod'])->whereBetween('amount', [$absAmount - 0.01, $absAmount + 0.01])
                        ->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$needle}%"))->first();
                    if ($fuzzy) { $this->setMatch($txn, 'possible_match', 'payment', $fuzzy->id); return; }
                } else {
                    $fuzzy = Expense::where('payment_method', 'bank')->whereBetween('total_amount', [$absAmount - 0.01, $absAmount + 0.01])
                        ->where(fn ($q) => $q->where('payee', 'like', "%{$needle}%")->orWhere('description', 'like', "%{$needle}%"))->first();
                    if ($fuzzy) { $this->setMatch($txn, 'possible_match', 'expense', $fuzzy->id); return; }
                    $fuzzyRm = RawMaterialPurchase::where('payment_method', 'bank')->whereBetween('total_amount', [$absAmount - 0.01, $absAmount + 0.01])
                        ->whereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$needle}%"))->first();
                    if ($fuzzyRm) { $this->setMatch($txn, 'possible_match', 'raw_material_purchase', $fuzzyRm->id); return; }
                }
            }
        }

        $this->setMatch($txn, 'missing_in_erp', null, null);
    }

    private function setMatch(BankStatementTransaction $txn, string $status, ?string $type, ?int $id): void
    {
        $txn->update(['match_status' => $status, 'matched_type' => $type, 'matched_id' => $id]);
    }

    /**
     * ERP-side transactions in the statement period with no corresponding
     * bank statement line at all — a real bank payment/expense/raw
     * material purchase that never showed up on the statement, which the
     * reconciliation summary counts as "missing_count".
     */
    public function findErpTransactionsMissingFromStatement(\App\Models\BankReconciliation $reconciliation): array
    {
        $start = $reconciliation->statement_month->copy()->startOfMonth();
        $end = $reconciliation->statement_month->copy()->endOfMonth();
        $matchedPaymentIds = $reconciliation->transactions()->where('matched_type', 'payment')->pluck('matched_id');
        $matchedExpenseIds = $reconciliation->transactions()->where('matched_type', 'expense')->pluck('matched_id');
        $matchedRmIds = $reconciliation->transactions()->where('matched_type', 'raw_material_purchase')->pluck('matched_id');

        $missingPayments = Payment::whereNotIn('method', ['cash', 'cod'])->whereDate('payment_date', '>=', $start)->whereDate('payment_date', '<=', $end)->whereNotIn('id', $matchedPaymentIds)->get();
        $missingExpenses = Expense::where('payment_method', 'bank')->whereDate('expense_date', '>=', $start)->whereDate('expense_date', '<=', $end)->whereNotIn('id', $matchedExpenseIds)->get();
        $missingRawMaterialPurchases = RawMaterialPurchase::where('payment_method', 'bank')->whereDate('purchase_date', '>=', $start)->whereDate('purchase_date', '<=', $end)->whereNotIn('id', $matchedRmIds)->get();

        return ['payments' => $missingPayments, 'expenses' => $missingExpenses, 'raw_material_purchases' => $missingRawMaterialPurchases];
    }
}
