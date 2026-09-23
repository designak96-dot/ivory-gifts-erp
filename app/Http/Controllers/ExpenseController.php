<?php
namespace App\Http\Controllers;
use App\Models\{DeliveryNote, Expense, Vehicle};
use App\Services\{AccountingService,DeliveryFinanceService,NumberingService,ProofUploadService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ExpenseController extends Controller
{
    public function index(){$q=Expense::latest('expense_date');$monthTotal=null;if(preg_match('/^\d{4}-\d{2}$/',(string)request('month'))){$m=\Carbon\Carbon::createFromFormat('Y-m-d',request('month').'-01');$q->whereBetween('expense_date',[$m->copy()->startOfMonth(),$m->copy()->endOfMonth()]);$monthTotal=(clone $q)->sum('total_amount');}return view('expenses.index',['expenses'=>$q->paginate(25)->withQueryString(),'vehicles'=>Vehicle::orderBy('name')->get(),'monthTotal'=>$monthTotal]);}
    public function store(Request $r,NumberingService $n,AccountingService $accounting,ProofUploadService $proofs){
        $d=$r->validate([
            'expense_date'=>'required|date','category'=>'required|string|max:100','payee'=>'nullable|string|max:190','payment_method'=>'required|in:cash,bank,card','amount_ex_tax'=>'required|numeric|min:0.01','tax_amount'=>'nullable|numeric|min:0','reference'=>'nullable|string|max:100','description'=>'nullable|string','proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192','invoice'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'vehicle_id'=>'nullable|exists:vehicles,id','driver_id'=>'nullable|exists:users,id',
            // "Require explicit evidence of who paid; do not guess" — mandatory the moment a vehicle is involved, never defaulted.
            'paid_by'=>'required_with:vehicle_id|nullable|in:company,driver',
            'reimbursement_status'=>'nullable|in:pending,reimbursed','delivery_day'=>'nullable|date',
        ],['proof.required'=>'A payment proof/slip must be uploaded before this expense can be posted.','paid_by.required_with'=>'Specify whether this was paid by the company or personally by the driver.']);
        $d['tax_amount']=$d['tax_amount']??0;$d['total_amount']=(float)$d['amount_ex_tax']+(float)$d['tax_amount'];
        // A driver-paid cost starts as an unreimbursed obligation — never silently marked reimbursed.
        if(($d['paid_by']??null)==='driver' && empty($d['reimbursement_status'])){$d['reimbursement_status']='pending';}
        $proofFields=$proofs->store($r->file('proof'),'expense-proofs');
        // Two entirely separate files, stored and named independently —
        // the invoice (optional) never touches or overwrites the
        // payment proof (required): its returned fields are remapped to
        // their own distinct column names before merging into the row.
        $invoiceFields=[];
        if($r->hasFile('invoice')){
            $raw=$proofs->store($r->file('invoice'),'expense-invoices');
            $invoiceFields=['invoice_path'=>$raw['proof_path'],'invoice_original_name'=>$raw['proof_original_name'],'invoice_mime'=>$raw['proof_mime'],'invoice_size'=>$raw['proof_size']];
        }
        unset($d['proof'],$d['invoice']); // these were validated file uploads, not real columns — never pass the file objects themselves into create()
        $e=DB::transaction(function()use($d,$n,$accounting,$proofFields,$invoiceFields){
            $e=Expense::create($d+$proofFields+$invoiceFields+['expense_number'=>$n->next('expense'),'created_by'=>auth()->id()]);
            $credit=$d['payment_method']==='cash'?'1000':'1010';
            $lines=[['account'=>'5100','debit'=>(float)$d['amount_ex_tax'],'credit'=>0],['account'=>$credit,'debit'=>0,'credit'=>(float)$d['total_amount']]];
            if((float)$d['tax_amount']>0)$lines[]=['account'=>'1300','debit'=>(float)$d['tax_amount'],'credit'=>0];
            $accounting->post($e,"Expense {$e->expense_number}",$lines,$d['expense_date']);
            return $e;
        });
        return back()->with('success',"Expense {$e->expense_number} posted.");
    }

    public function show(Expense $expense)
    {
        abort_unless(auth()->user()->hasPermission('expenses.view'), 403);
        $candidateDeliveries = $expense->vehicle_id
            ? DeliveryNote::where('delivery_type', 'own_company')
                ->when($expense->delivery_day, fn ($q) => $q->whereDate('delivered_at', $expense->delivery_day))
                ->with('customer')->orderByDesc('delivered_at')->limit(100)->get()
            : collect();
        $currentAllocations = \App\Models\ExpenseDeliveryAllocation::where('expense_id', $expense->id)->pluck('delivery_note_id')->all();
        return view('expenses.show', compact('expense', 'candidateDeliveries', 'currentAllocations'));
    }

    /** Analytical only — spreads this real, already-posted Expense across
     * the selected deliveries for reporting. Never creates a second
     * accounting entry; reallocating cleanly replaces the prior split
     * while preserving the exact original total (DeliveryFinanceService
     * handles the sum-not-overwrite guarantee). */
    public function allocate(Request $request, Expense $expense, DeliveryFinanceService $service)
    {
        abort_unless(auth()->user()->hasPermission('vehicle-expenses.manage'), 403);
        $data = $request->validate(['delivery_ids' => 'nullable|array', 'delivery_ids.*' => 'exists:delivery_notes,id']);
        $service->allocateExpenseToDeliveries($expense, $data['delivery_ids'] ?? []);
        return back()->with('success', 'Allocation saved — no new accounting entry created, this is for reporting only.');
    }
}
