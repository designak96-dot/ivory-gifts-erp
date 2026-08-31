<?php

namespace App\Services;

use App\Models\ChartOfAccount;

/**
 * Real account balances from the actual double-entry ledger
 * (chart_of_accounts / journal_entries / journal_lines) — every figure
 * here is debits-minus-credits (or credits-minus-debits for
 * liability accounts) summed from genuinely posted journal lines, never
 * estimated or hardcoded.
 */
class FinancialSummaryService
{
    public function summary(): array
    {
        return [
            'bank_balance' => $this->assetBalance('1010'),
            'cash_in_hand' => $this->assetBalance('1000'),
            'inventory_value' => $this->assetBalance('1200'),
            'receivables' => $this->assetBalance('1100'),
            'payables' => $this->liabilityBalance('2000'),
        ];
    }

    /**
     * Cashflow view across every real bank/cash/card/petty-cash account
     * (not just the two original hardcoded codes), for a given period.
     * "Cash In"/"Cash Out" are period-scoped; the balances shown are the
     * accounts' real current balances (opening + all-time activity),
     * matching how the Bank & Cash Accounts page itself computes them.
     */
    public function cashflow(?string $from = null, ?string $to = null): array
    {
        $accounts = ChartOfAccount::whereIn('account_subtype', ['bank', 'cash', 'card', 'petty_cash'])->where('is_active', true)->get();

        $cashIn = 0.0;
        $cashOut = 0.0;
        $bankBalance = 0.0;
        $cashBalance = 0.0;

        foreach ($accounts as $account) {
            $periodLines = $account->lines()->whereHas('entry', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to) $q->whereDate('entry_date', '<=', $to);
            });
            $cashIn += (float) (clone $periodLines)->sum('debit');
            $cashOut += (float) (clone $periodLines)->sum('credit');

            $balance = (float) $account->opening_balance + (float) $account->lines()->sum('debit') - (float) $account->lines()->sum('credit');
            if ($account->account_subtype === 'bank') {
                $bankBalance += $balance;
            } else {
                $cashBalance += $balance;
            }
        }

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cashflow' => $cashIn - $cashOut,
            'receivables' => $this->assetBalance('1100'),
            'payables' => $this->liabilityBalance('2000'),
            'bank_balance' => $bankBalance,
            'cash_balance' => $cashBalance,
        ];
    }

    private function assetBalance(string $code): float
    {
        $account = ChartOfAccount::where('code', $code)->first();
        if (!$account) return 0.0;
        return (float) $account->lines()->sum('debit') - (float) $account->lines()->sum('credit');
    }

    private function liabilityBalance(string $code): float
    {
        $account = ChartOfAccount::where('code', $code)->first();
        if (!$account) return 0.0;
        return (float) $account->lines()->sum('credit') - (float) $account->lines()->sum('debit');
    }
}
