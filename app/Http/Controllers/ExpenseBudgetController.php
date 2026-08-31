<?php

namespace App\Http\Controllers;

use App\Models\{Expense, ExpenseBudget};
use Illuminate\Http\Request;

class ExpenseBudgetController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m-01'));
        $monthStart = \Carbon\Carbon::parse($month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $categories = Expense::whereBetween('expense_date', [$monthStart, $monthEnd])
            ->distinct()
            ->pluck('category')
            ->merge(ExpenseBudget::where('month', $monthStart->toDateString())->pluck('category'))
            ->unique()
            ->sort()
            ->values();

        $rows = $categories->map(function ($category) use ($monthStart, $monthEnd) {
            $budget = ExpenseBudget::where('category', $category)->where('month', $monthStart->toDateString())->value('budget_amount') ?? 0.0;
            $actual = (float) Expense::where('category', $category)->whereBetween('expense_date', [$monthStart, $monthEnd])->sum('total_amount');
            return [
                'category' => $category,
                'budget' => (float) $budget,
                'actual' => $actual,
                'remaining' => (float) $budget - $actual,
                'over_budget' => $budget > 0 && $actual > $budget,
            ];
        });

        return view('finance.budgets', ['rows' => $rows, 'month' => $monthStart->format('Y-m')]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('expenses.manage'), 403);
        $data = $request->validate([
            'category' => 'required|string|max:100',
            'month' => 'required|date',
            'budget_amount' => 'required|numeric|min:0',
        ]);

        ExpenseBudget::updateOrCreate(
            ['category' => $data['category'], 'month' => \Carbon\Carbon::parse($data['month'])->startOfMonth()->toDateString()],
            ['budget_amount' => $data['budget_amount']]
        );

        return back()->with('success', 'Budget saved.');
    }
}
