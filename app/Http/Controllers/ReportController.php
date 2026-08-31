<?php
namespace App\Http\Controllers;
use App\Models\{Customer,Expense,Invoice,SalesOrder};
use Illuminate\Support\Facades\DB;
class ReportController extends Controller
{
    public function index(){ $from=request('from',now()->startOfMonth()->toDateString());$to=request('to',today()->toDateString());$sales=Invoice::whereBetween('invoice_date',[$from,$to])->whereNotIn('status',['cancelled'])->sum('subtotal');$vat=Invoice::whereBetween('invoice_date',[$from,$to])->whereNotIn('status',['cancelled'])->sum('tax_total');$expenses=Expense::whereBetween('expense_date',[$from,$to])->sum('amount_ex_tax');$ageing=Invoice::with('customer','salesOrder')->where('outstanding_amount','>',0)->orderBy('due_date')->get();$byEmirate=SalesOrder::select('emirate',DB::raw('COUNT(*) total_orders'),DB::raw('SUM(grand_total) sales'))->where('is_legacy_delivery_import',false)->whereBetween('order_date',[$from,$to])->groupBy('emirate')->get();

        // Aging buckets — grouped from the SAME $ageing collection already
        // fetched above, no additional queries. Age is days past the due
        // date (0 or negative for not-yet-due, per the existing display
        // logic this report already used).
        $agingBuckets = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
        foreach ($ageing as $inv) {
            $days = $inv->due_date ? max(0, $inv->due_date->diffInDays(today(), false)) : 0;
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
            $agingBuckets[$bucket] += (float) $inv->outstanding_amount;
        }

        $productProfitability = app(\App\Services\ProfitCalculatorService::class)->productProfitability()->take(15);

        return view('reports.index',compact('from','to','sales','vat','expenses','ageing','byEmirate','agingBuckets','productProfitability'));}

    /**
     * Owner-only, read-only identification of existing duplicate customers
     * (grouped by matching normalized phone number). Deliberately does NOT
     * delete, merge, or modify anything — per the explicit requirement that
     * existing duplicates may already be linked to orders/payments/invoices
     * and must never be destroyed automatically. Each group shows related-
     * record counts so the Owner can judge which one to keep manually.
     */
    public function duplicateCustomers()
    {
        abort_unless(auth()->user()->hasPermission('customers.manage'), 403);

        $groups = Customer::select('phone')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        $duplicates = $groups->map(function ($phone) {
            return Customer::where('phone', $phone)
                ->withCount(['orders', 'invoices'])
                ->orderBy('created_at')
                ->get();
        });

        return view('reports.duplicate-customers', compact('duplicates'));
    }
}
