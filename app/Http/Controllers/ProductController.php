<?php
namespace App\Http\Controllers;
use App\Models\{Product,ProductCategory,TaxRate};
use App\Services\ProductService;
use Illuminate\Http\Request;
class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}
    public function index(){ $q=Product::with('category');if($s=request('q'))$q->where(fn($x)=>$x->where('sku','like',"%$s%")->orWhere('name_en','like',"%$s%")->orWhere('name_ar','like',"%$s%"));return view('products.index',['products'=>$q->latest()->paginate(25)]);}
    public function create(){abort_unless(auth()->user()->hasPermission('products.manage'),403);return view('products.form',$this->data(new Product));}
    public function store(Request $r){abort_unless(auth()->user()->hasPermission('products.manage'),403);$data=$r->validate(ProductService::validationRules());$product=$this->products->create($data,$r->file('image'));return redirect()->route('products.edit',$product)->with('success','Product created.');}
    public function edit(Product $product){abort_unless(auth()->user()->hasPermission('products.manage'),403);return view('products.form',$this->data($product));}
    public function update(Request $r,Product $product){abort_unless(auth()->user()->hasPermission('products.manage'),403);$data=$r->validate(ProductService::validationRules($product));$this->products->update($product,$data,$r->file('image'));return redirect()->route('products.edit',$product)->with('success','Product updated.');}
    private function data(Product $product):array{
        $priceHistory = $product->exists ? \App\Models\PurchaseOrderItem::with('purchaseOrder.supplier')
            ->where('product_id', $product->id)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['ordered', 'partially_received', 'received']))
            ->get()
            ->groupBy(fn ($item) => $item->purchaseOrder->supplier_id)
            ->map(function ($items) {
                $sorted = $items->sortBy(fn ($i) => $i->purchaseOrder->order_date);
                return [
                    'supplier' => $sorted->first()->purchaseOrder->supplier,
                    'previous_price' => $sorted->count() > 1 ? $sorted->slice(-2, 1)->first()->unit_cost : null,
                    'latest_price' => $sorted->last()->unit_cost,
                    'lowest_price' => $sorted->min('unit_cost'),
                    'latest_date' => $sorted->last()->purchaseOrder->order_date,
                ];
            })->values() : collect();
        return compact('product','priceHistory')+['categories'=>ProductCategory::orderBy('name_en')->get(),'taxRates'=>TaxRate::where('is_active',true)->get()];
    }
}
