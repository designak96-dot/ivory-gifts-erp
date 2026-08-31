<?php
namespace App\Http\Controllers;
use App\Models\{Product,StockItem,Warehouse};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class InventoryController extends Controller
{
    public function index(){$intel=app(\App\Services\StockIntelligenceService::class);$lowStock=$intel->lowStock();$stocksQuery=StockItem::with('product','warehouse');if(request('status')==='low')$stocksQuery->whereIn('product_id',$lowStock->pluck('product.id'));return view('inventory.index',['stocks'=>$stocksQuery->paginate(30),'products'=>Product::where('is_active',true)->get(),'warehouses'=>Warehouse::where('is_active',true)->get(),'lowStock'=>$lowStock,'outOfStock'=>$intel->outOfStock(),'slowMoving'=>$intel->slowMoving()]);}
    public function adjust(Request $r){$d=$r->validate(['product_id'=>'required|exists:products,id','warehouse_id'=>'required|exists:warehouses,id','quantity_delta'=>'required|numeric|not_in:0','notes'=>'required|string|max:500']);DB::transaction(function()use($d){$stock=StockItem::lockForUpdate()->firstOrCreate(['product_id'=>$d['product_id'],'warehouse_id'=>$d['warehouse_id']]);if((float)$stock->quantity_on_hand+(float)$d['quantity_delta']<0)throw \Illuminate\Validation\ValidationException::withMessages(['quantity_delta'=>'Adjustment cannot make stock negative.']);$stock->increment('quantity_on_hand',$d['quantity_delta']);$stock->movements()->create(['type'=>'adjustment','quantity_delta'=>$d['quantity_delta'],'unit_cost'=>$stock->weighted_average_cost,'movement_date'=>now(),'notes'=>$d['notes'],'created_by'=>auth()->id()]);});return back()->with('success','Inventory adjusted with an audit movement.');}
}
