<?php
namespace App\Http\Controllers;
use App\Models\Supplier;
use App\Services\NumberingService;
use Illuminate\Http\Request;
class SupplierController extends Controller
{
    public function index(){ $q=Supplier::query();if($s=request('q'))$q->where(fn($x)=>$x->where('name','like',"%$s%")->orWhere('supplier_code','like',"%$s%"));return view('suppliers.index',['suppliers'=>$q->latest()->paginate(25)]);}
    public function create(){abort_unless(auth()->user()->hasPermission('purchases.manage'),403);return view('suppliers.form',['supplier'=>new Supplier]);}
    public function store(Request $r,NumberingService $n){abort_unless(auth()->user()->hasPermission('purchases.manage'),403);$d=$this->validated($r);$d['supplier_code']=$n->next('supplier');$s=Supplier::create($d);return redirect()->route('suppliers.edit',$s)->with('success','Supplier created.');}
    public function edit(Supplier $supplier){abort_unless(auth()->user()->hasPermission('purchases.manage'),403);return view('suppliers.form',compact('supplier'));}
    public function update(Request $r,Supplier $supplier){abort_unless(auth()->user()->hasPermission('purchases.manage'),403);$supplier->update($this->validated($r));return back()->with('success','Supplier updated.');}
    private function validated(Request $r):array{return $r->validate(['name'=>'required|string|max:190','contact_person'=>'nullable|string|max:190','phone'=>'nullable|string|max:30','email'=>'nullable|email|max:190','trn'=>'nullable|string|max:30','address'=>'nullable|string','status'=>'required|in:active,inactive']);}
}
