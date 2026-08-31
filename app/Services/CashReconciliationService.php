<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Support\Carbon;

/**
 * Computes real cash movement from the actual double-entry ledger — the
 * same source of truth every other financial figure in this app comes
 * from. Every payment, expense, and cash adjustment already posts to
 * the ledger when it's recorded, so "Opening Cash + Cash In − Cash Out"
 * is never a separate, parallel calculation that could drift out of
 * sync with the real accounts — it's read directly from the same
 * journal lines that make up the account's real balance.
 */
class CashReconciliationService
{
    /**
     * @return array{opening_cash: float, cash_in: float, cash_out: float, expected_cash: float, period_start: Carbon, period_end: Carbon}
     */
    public function compute(ChartOfAccount $cashAccount, string $reconciliationDate): array
    {
        $periodEnd = Carbon::parse($reconciliationDate)->endOfDay();
        $periodStart = $periodEnd->copy()->startOfMonth();

        $openingCash = (float) $cashAccount->opening_balance
            + (float) $cashAccount->lines()->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '<', $periodStart))->sum('debit')
            - (float) $cashAccount->lines()->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '<', $periodStart))->sum('credit');

        $periodLines = $cashAccount->lines()->whereHas('entry', function ($q) use ($periodStart, $periodEnd) {
            $q->whereDate('entry_date', '>=', $periodStart)->whereDate('entry_date', '<=', $periodEnd);
        });

        $cashIn = (float) (clone $periodLines)->sum('debit');
        $cashOut = (float) (clone $periodLines)->sum('credit');

        return [
            'opening_cash' => round($openingCash, 2),
            'cash_in' => round($cashIn, 2),
            'cash_out' => round($cashOut, 2),
            'expected_cash' => round($openingCash + $cashIn - $cashOut, 2),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }
}
