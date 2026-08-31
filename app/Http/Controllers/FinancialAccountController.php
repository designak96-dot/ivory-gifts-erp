<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class FinancialAccountController extends Controller
{
    private const SUBTYPES = ['bank' => 'Bank Account', 'cash' => 'Cash', 'card' => 'Card', 'petty_cash' => 'Petty Cash'];

    public function index(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $accounts = ChartOfAccount::whereNotNull('account_subtype')
            ->where('is_active', true)
            ->get()
            ->map(function ($account) use ($from, $to) {
                $linesQuery = $account->lines();
                if ($from) $linesQuery->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '>=', $from));
                if ($to) $linesQuery->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '<=', $to));

                $moneyIn = (float) (clone $linesQuery)->sum('debit');
                $moneyOut = (float) (clone $linesQuery)->sum('credit');

                // Current balance always reflects the account's FULL real
                // history (opening balance + every posted line ever),
                // regardless of the from/to filter — the filter only
                // scopes "money in/out for this period", matching how a
                // real bank statement works (period activity vs running
                // balance).
                $allTimeIn = (float) $account->lines()->sum('debit');
                $allTimeOut = (float) $account->lines()->sum('credit');

                return [
                    'account' => $account,
                    'money_in' => $moneyIn,
                    'money_out' => $moneyOut,
                    'current_balance' => (float) $account->opening_balance + $allTimeIn - $allTimeOut,
                ];
            });

        return view('finance.accounts', ['accounts' => $accounts, 'subtypes' => self::SUBTYPES, 'from' => $from, 'to' => $to]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'account_subtype' => 'required|in:'.implode(',', array_keys(self::SUBTYPES)),
            'opening_balance' => 'required|numeric',
        ]);

        $code = $this->nextAssetCode();
        ChartOfAccount::create([
            'code' => $code,
            'name' => $data['name'],
            'type' => 'asset',
            'account_subtype' => $data['account_subtype'],
            'opening_balance' => $data['opening_balance'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Account created.');
    }

    /** Finds the next free code in the 10xx asset range, so new accounts never collide with the seeded 1000/1010/1100/1200/1300. */
    private function nextAssetCode(): string
    {
        $max = ChartOfAccount::where('code', 'like', '1%')
            ->get()
            ->map(fn ($a) => (int) $a->code)
            ->filter(fn ($c) => $c >= 1000 && $c < 2000)
            ->max() ?? 1000;
        return (string) (max($max, 1300) + 10);
    }
}
