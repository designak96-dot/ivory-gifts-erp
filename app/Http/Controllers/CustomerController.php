<?php
namespace App\Http\Controllers;
use App\Models\Customer;
use App\Services\{NumberingService,PhoneNormalizer};
use Illuminate\Http\Request;
class CustomerController extends Controller
{
    public function index(){ $q=Customer::query(); if($s=request('q'))$q->where(fn($x)=>$x->where('name','like',"%$s%")->orWhere('company_name','like',"%$s%")->orWhere('phone','like',"%$s%")->orWhere('customer_code','like',"%$s%"));return view('customers.index',['customers'=>$q->latest()->paginate(25)]);}
    public function create(){abort_unless(auth()->user()->hasPermission('customers.manage'),403);return view('customers.form',['customer'=>new Customer]);}
    public function store(Request $request,NumberingService $numbers,PhoneNormalizer $phones){
        abort_unless(auth()->user()->hasPermission('customers.manage'),403);
        $data=$this->validated($request,$phones);

        // Phone match is checked first and separately from email, per the
        // explicit requirement that duplicate prevention is phone-based —
        // the message names the actual match reason rather than a generic
        // "possible duplicate".
        $duplicate = null;
        $matchedBy = null;
        if (!empty($data['phone']) && $byPhone = Customer::where('phone', $data['phone'])->first()) {
            $duplicate = $byPhone; $matchedBy = 'phone number';
        } elseif (!empty($data['email']) && $byEmail = Customer::where('email', $data['email'])->first()) {
            $duplicate = $byEmail; $matchedBy = 'email address';
        }
        if ($duplicate) {
            return back()->withErrors([
                'phone' => "Existing customer found with this {$matchedBy}.",
            ])->withInput()->with('duplicate_customer', [
                'id' => $duplicate->id, 'name' => $duplicate->name, 'code' => $duplicate->customer_code,
            ]);
        }

        $data['customer_code']=$numbers->next('customer');$data['created_by']=auth()->id();$data['updated_by']=auth()->id();$customer=Customer::create($data);return redirect()->route('customers.show',$customer)->with('success','Customer created.');
    }
    public function show(Customer $customer){return view('customers.show',compact('customer'));}
    public function edit(Customer $customer){abort_unless(auth()->user()->hasPermission('customers.manage'),403);return view('customers.form',compact('customer'));}
    public function update(Request $request,Customer $customer,PhoneNormalizer $phones){abort_unless(auth()->user()->hasPermission('customers.manage'),403);$data=$this->validated($request,$phones);$data['updated_by']=auth()->id();$customer->update($data);return redirect()->route('customers.show',$customer)->with('success','Customer updated.');}

    private function validated(Request $r,PhoneNormalizer $phones):array{$d=$r->validate(['name'=>'required|string|max:190','company_name'=>'nullable|string|max:190','phone'=>'nullable|string|max:30','whatsapp'=>'nullable|string|max:30','email'=>'nullable|email|max:190','trn'=>'nullable|string|max:30','billing_address'=>'nullable|string','delivery_address'=>'nullable|string','emirate'=>'nullable|string|max:50','area'=>'nullable|string|max:100','preferred_language'=>'required|in:en,ar','customer_type'=>'required|in:retail,corporate','source'=>'nullable|string|max:100','credit_limit'=>'nullable|numeric|min:0','payment_terms_days'=>'nullable|integer|min:0|max:365','status'=>'required|in:active,inactive,blocked','notes'=>'nullable|string']);try{$d['phone']=$phones->normalize($d['phone']??null);$d['whatsapp']=$phones->normalize($d['whatsapp']??$d['phone']??null);}catch(\InvalidArgumentException $e){throw \Illuminate\Validation\ValidationException::withMessages(['phone'=>$e->getMessage()]);}return $d;}
}
