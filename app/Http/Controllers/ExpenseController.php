<?php
namespace App\Http\Controllers;
use App\Models\Expense;
use App\Services\{AccountingService,NumberingService,ProofUploadService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ExpenseController extends Controller
{
    public function index(){return view('expenses.index',['expenses'=>Expense::latest('expense_date')->paginate(25)]);}
    public function store(Request $r,NumberingService $n,AccountingService $accounting,ProofUploadService $proofs){
        $d=$r->validate(['expense_date'=>'required|date','category'=>'required|string|max:100','payee'=>'nullable|string|max:190','payment_method'=>'required|in:cash,bank,card','amount_ex_tax'=>'required|numeric|min:0.01','tax_amount'=>'nullable|numeric|min:0','reference'=>'nullable|string|max:100','description'=>'nullable|string','proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192'],['proof.required'=>'An expense proof/receipt must be uploaded before this expense can be posted.']);
        $d['tax_amount']=$d['tax_amount']??0;$d['total_amount']=(float)$d['amount_ex_tax']+(float)$d['tax_amount'];
        $proofFields=$proofs->store($r->file('proof'),'expense-proofs');
        $e=DB::transaction(function()use($d,$n,$accounting,$proofFields){$e=Expense::create($d+$proofFields+['expense_number'=>$n->next('expense'),'created_by'=>auth()->id()]);$credit=$d['payment_method']==='cash'?'1000':'1010';$lines=[['account'=>'5100','debit'=>(float)$d['amount_ex_tax'],'credit'=>0],['account'=>$credit,'debit'=>0,'credit'=>(float)$d['total_amount']]];if((float)$d['tax_amount']>0)$lines[]=['account'=>'1300','debit'=>(float)$d['tax_amount'],'credit'=>0];$accounting->post($e,"Expense {$e->expense_number}",$lines,$d['expense_date']);return $e;});
        return back()->with('success',"Expense {$e->expense_number} posted.");
    }
}
