<?php

namespace App\Http\Controllers;

use App\Services\FinancialSummaryService;
use Illuminate\Http\Request;

class CashflowController extends Controller
{
    public function index(Request $request, FinancialSummaryService $service)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $cashflow = $service->cashflow($from, $to);
        return view('finance.cashflow', compact('cashflow', 'from', 'to'));
    }
}
