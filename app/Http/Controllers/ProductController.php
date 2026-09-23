<?php
namespace App\Http\Controllers;
use App\Models\{Product,ProductCategory,TaxRate};
use App\Services\ProductService;
use Illuminate\Http\Request;
class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}
    public function index(){ $q=Product::with('category');if($s=request('q'))$q->where(fn($x)=>$x->where('sku','like',"%$s%")->orWhere('name_en','like',"%$s%")->orWhere('name_ar','like',"%$s%"));if(request()->boolean('low_stock')){$q->where('is_active',true)->where('reorder_level','>',0)->whereRaw('(SELECT COALESCE(SUM(quantity_on_hand),0) FROM stock_items WHERE stock_items.product_id = products.id) <= products.reorder_level');}return view('products.index',['products'=>$q->latest()->paginate(25)->withQueryString()]);}
    public function create(){abort_unless(auth()->user()->hasPermission('products.manage'),403);return view('products.form',$this->data(new Product));}
    public function store(Request $r){abort_unless(auth()->user()->hasPermission('products.manage'),403);$data=$r->validate(ProductService::validationRules());$product=$this->products->create($data,$r->file('image'));return redirect()->route('products.edit',$product)->with('success','Product created.');}
    public function edit(Product $product){abort_unless(auth()->user()->hasPermission('products.manage'),403);return view('products.form',$this->data($product));}
    public function update(Request $r,Product $product){abort_unless(auth()->user()->hasPermission('products.manage'),403);$data=$r->validate(ProductService::validationRules($product));$this->products->update($product,$data,$r->file('image'));return redirect()->route('products.edit',$product)->with('success','Product updated.');}
    private function data(Product $product):array{
        return compact('product')+['categories'=>ProductCategory::orderBy('name_en')->get(),'taxRates'=>TaxRate::where('is_active',true)->get()];
    }
}
