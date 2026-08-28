<?php
namespace App\Http\Controllers;
use App\Models\{Customer,Product,Quotation};
use App\Services\{DocumentTotals,NumberingService,SalesWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class QuotationController extends Controller
{
    public function index(){ $q=Quotation::with('customer');if($s=request('q'))$q->where(fn($x)=>$x->where('quotation_number','like',"%$s%")->orWhereHas('customer',fn($c)=>$c->where('name','like',"%$s%")));if(request('status'))$q->where('status',request('status'));return view('quotations.index',['quotations'=>$q->latest()->paginate(25)]);}
    public function create(){abort_unless(auth()->user()->hasPermission('quotations.manage'),403);return view('quotations.form',$this->formData(new Quotation(['quotation_date'=>today(),'valid_until'=>today()->addDays(14)])));}

    public function store(Request $r,NumberingService $numbers,DocumentTotals $totals){
        abort_unless(auth()->user()->hasPermission('quotations.manage'),403);
        $d=$this->validated($r);
        $calc=$totals->calculate($d['items']);
        $q=DB::transaction(function()use($d,$calc,$numbers){
            $q=Quotation::create(['quotation_number'=>$numbers->next('quotation'),'customer_id'=>$d['customer_id'],'quotation_date'=>$d['quotation_date'],'valid_until'=>$d['valid_until']??null,'salesperson_id'=>auth()->id(),'status'=>$d['status'],'subtotal'=>$calc['subtotal'],'discount_total'=>$calc['discount_total'],'tax_total'=>$calc['tax_total'],'grand_total'=>$calc['grand_total'],'notes'=>$d['notes']??null]);
            $this->replaceItems($q,$calc['items']);
            $q->versions()->create(['version_number'=>1,'snapshot'=>$q->load('items')->toArray(),'created_by'=>auth()->id()]);
            return $q;
        });
        return redirect()->route('quotations.show',$q)->with('success','Quotation created.');
    }

    public function edit(Quotation $quotation){
        abort_unless(auth()->user()->hasPermission('quotations.manage'),403);
        abort_if($quotation->status==='converted',403,'A converted quotation cannot be edited.');
        return view('quotations.form',$this->formData($quotation->load('items.product')));
    }

    public function update(Request $r,Quotation $quotation,DocumentTotals $totals){
        abort_unless(auth()->user()->hasPermission('quotations.manage'),403);
        abort_if($quotation->status==='converted',403,'A converted quotation cannot be edited.');
        $d=$this->validated($r);
        $calc=$totals->calculate($d['items']);
        DB::transaction(function()use($quotation,$d,$calc){
            $quotation->update(['customer_id'=>$d['customer_id'],'quotation_date'=>$d['quotation_date'],'valid_until'=>$d['valid_until']??null,'status'=>$d['status'],'subtotal'=>$calc['subtotal'],'discount_total'=>$calc['discount_total'],'tax_total'=>$calc['tax_total'],'grand_total'=>$calc['grand_total'],'notes'=>$d['notes']??null]);
            $this->replaceItems($quotation,$calc['items']);
            $quotation->versions()->create(['version_number'=>$quotation->versions()->max('version_number')+1,'snapshot'=>$quotation->load('items')->toArray(),'created_by'=>auth()->id()]);
        });
        return redirect()->route('quotations.show',$quotation)->with('success','Quotation updated.');
    }

    private function replaceItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();
        foreach ($items as $i) {
            $product = !empty($i['product_id']) ? Product::find($i['product_id']) : null;
            $quotation->items()->create([
                'product_id' => $product?->id,
                'description' => $i['description'] ?? $product?->name_en ?? 'Item',
                'qty' => $i['qty'], 'unit_price' => $i['unit_price'], 'discount' => $i['discount'],
                'tax_rate' => $i['tax_rate'], 'tax_amount' => $i['tax_amount'], 'line_total' => $i['line_total'],
            ]);
        }
    }

    public function show(Quotation $quotation){$quotation->load('customer','items.product','versions');return view('quotations.show',compact('quotation'));}
    public function status(Request $r,Quotation $quotation){$d=$r->validate(['status'=>'required|in:draft,sent,viewed,approved,rejected,expired']);$quotation->update($d);return back()->with('success','Quotation status updated.');}

    /**
     * Converts an approved quotation into a Sales Order, using the same
     * MANUAL-MMYY numbering and duplicate-checking as creating an order
     * directly — collected via the modal on the quotation page rather than
     * auto-generated, so the Owner/Sales user explicitly sets the Order
     * Number, Order Date, Delivery Date, and Priority at conversion time.
     * Every quotation line (product-linked or manual) carries over as-is.
     */
    public function convert(Request $r, Quotation $quotation, SalesWorkflow $workflow, NumberingService $numbers)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);

        $d = $r->validate([
            'manual_reference' => 'required|string|max:10|regex:/^[A-Za-z0-9]+$/',
            'order_date' => 'required|date',
            'delivery_date' => 'required|date',
            'priority' => 'required|in:normal,urgent,high',
        ]);

        $orderDate = \Carbon\Carbon::parse($d['order_date']);
        $orderNumber = $numbers->formatSalesOrderNumber($d['manual_reference'], $orderDate);

        if (\App\Models\SalesOrder::where('order_number', $orderNumber)->exists()) {
            return back()->withErrors(['manual_reference' => "Sales Order {$orderNumber} already exists. Please enter another Order Number."])->withInput();
        }

        try {
            $order = $workflow->quotationToOrder(
                $quotation->load('items', 'customer'),
                manualReference: $d['manual_reference'],
                orderDate: $orderDate,
                deliveryDate: \Carbon\Carbon::parse($d['delivery_date']),
                priority: $d['priority'],
            );
            return redirect()->route('orders.show', $order)->with('success', 'Sales order and production job created.');
        } catch (\Throwable $e) {
            return back()->withErrors(['convert' => $e->getMessage()]);
        }
    }

    private function formData(Quotation $quotation):array{return compact('quotation')+['customers'=>Customer::where('status','active')->orderBy('name')->get(),'products'=>Product::where('is_active',true)->orderBy('name_en')->get(),'locations'=>SalesOrderController::LOCATIONS,'productCategories'=>\App\Models\ProductCategory::orderBy('name_en')->get(),'productTaxRates'=>\App\Models\TaxRate::where('is_active',true)->get()];}

    private function validated(Request $r):array{
        return $r->validate([
            'customer_id'=>'required|exists:customers,id','quotation_date'=>'required|date',
            'valid_until'=>'nullable|date|after_or_equal:quotation_date','status'=>'required|in:draft,sent,approved',
            'notes'=>'nullable|string','items'=>'required|array|min:1',
            'items.*.product_id'=>'nullable|exists:products,id',
            'items.*.description'=>'required_without:items.*.product_id|nullable|string|max:255',
            'items.*.qty'=>'required|numeric|min:0.001','items.*.unit_price'=>'required|numeric|min:0',
            'items.*.discount'=>'nullable|numeric|min:0','items.*.tax_rate'=>'nullable|numeric|min:0|max:100',
        ]);
    }
}
