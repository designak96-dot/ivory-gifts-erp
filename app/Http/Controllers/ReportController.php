<?php
namespace App\Http\Controllers;
use App\Models\{Customer,Expense,Invoice,SalesOrder};
use Illuminate\Support\Facades\DB;
class ReportController extends Controller
{
    public function index(){ $from=request('from',now()->startOfMonth()->toDateString());$to=request('to',today()->toDateString());$sales=Invoice::whereBetween('invoice_date',[$from,$to])->whereNotIn('status',['cancelled'])->sum('subtotal');$vat=Invoice::whereBetween('invoice_date',[$from,$to])->whereNotIn('status',['cancelled'])->sum('tax_total');$expenses=Expense::whereBetween('expense_date',[$from,$to])->sum('amount_ex_tax');$ageing=Invoice::with('customer')->where('outstanding_amount','>',0)->orderBy('due_date')->get();$byEmirate=SalesOrder::select('emirate',DB::raw('COUNT(*) total_orders'),DB::raw('SUM(grand_total) sales'))->where('is_legacy_delivery_import',false)->whereBetween('order_date',[$from,$to])->groupBy('emirate')->get();return view('reports.index',compact('from','to','sales','vat','expenses','ageing','byEmirate'));}
}
