<?php
namespace App\Http\Controllers;
use App\Models\{Product,PurchaseOrder,StockItem,Supplier,Warehouse};
use App\Services\{DocumentTotals,NumberingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PurchaseOrderController extends Controller
{
    public function index(){return view('purchases.index',['orders'=>PurchaseOrder::with('supplier')->latest()->paginate(25),'suppliers'=>Supplier::where('status','active')->get(),'products'=>Product::where('is_active',true)->get()]);}
    public function show(PurchaseOrder $purchaseOrder){$purchaseOrder->load('supplier','items.product');return view('purchases.show',['po'=>$purchaseOrder]);}
    public function store(Request $r,NumberingService $n,DocumentTotals $totals){$d=$r->validate(['supplier_id'=>'required|exists:suppliers,id','order_date'=>'required|date','expected_delivery_date'=>'nullable|date','notes'=>'nullable|string','items'=>'required|array|min:1','items.*.product_id'=>'required|exists:products,id','items.*.qty'=>'required|numeric|min:0.001','items.*.unit_cost'=>'required|numeric|min:0','items.*.tax_rate'=>'nullable|numeric|min:0|max:100']);$mapped=array_map(fn($i)=>$i+['unit_price'=>$i['unit_cost']],$d['items']);$calc=$totals->calculate($mapped);$po=DB::transaction(function()use($d,$calc,$n){$po=PurchaseOrder::create(['purchase_order_number'=>$n->next('purchase_order'),'supplier_id'=>$d['supplier_id'],'order_date'=>$d['order_date'],'expected_delivery_date'=>$d['expected_delivery_date']??null,'status'=>'draft','subtotal'=>$calc['subtotal'],'tax_total'=>$calc['tax_total'],'grand_total'=>$calc['grand_total'],'notes'=>$d['notes']??null]);foreach($calc['items'] as $i){$p=Product::find($i['product_id']);$po->items()->create(['product_id'=>$p->id,'description'=>$p->name_en,'qty'=>$i['qty'],'unit_cost'=>$i['unit_price'],'tax_amount'=>$i['tax_amount'],'line_total'=>$i['line_total']]);}return $po;});return back()->with('success',"Purchase order {$po->purchase_order_number} created.");}

    /** draft -> approved */
    public function approve(PurchaseOrder $purchaseOrder){abort_unless($purchaseOrder->status==='draft',422,'Only a draft purchase order can be approved.');$purchaseOrder->update(['status'=>'approved']);return back()->with('success','Purchase order approved.');}

    /** approved -> ordered (sent to the supplier) */
    public function markOrdered(PurchaseOrder $purchaseOrder){abort_unless($purchaseOrder->status==='approved',422,'Only an approved purchase order can be marked as ordered.');$purchaseOrder->update(['status'=>'ordered']);return back()->with('success','Purchase order marked as ordered.');}

    /**
     * Receives stock — supports partial receiving. Only the NEWLY
     * received delta (this call's amount, not the item's full ordered
     * qty) is added to inventory each time, so calling this multiple
     * times across several partial deliveries never double-counts stock.
     * Status becomes 'partially_received' until every item's
     * qty_received reaches its ordered qty, then 'received'.
     */
    public function receive(Request $r,PurchaseOrder $purchaseOrder){
        abort_if($purchaseOrder->status==='received',422,'Already fully received.');
        abort_unless(in_array($purchaseOrder->status,['ordered','partially_received']),422,'Purchase order must be marked as ordered before it can be received.');
        $d=$r->validate(['items'=>'required|array','items.*.item_id'=>'required|exists:purchase_order_items,id','items.*.qty_received_now'=>'required|numeric|min:0']);
        DB::transaction(function()use($d,$purchaseOrder){
            $warehouse=Warehouse::firstOrCreate(['name'=>'Main Warehouse'],['is_active'=>true]);
            foreach($d['items'] as $row){
                $item=$purchaseOrder->items()->findOrFail($row['item_id']);
                $delta=min((float)$row['qty_received_now'],(float)$item->qty-(float)$item->qty_received);
                if($delta<=0)continue;
                $stock=StockItem::firstOrCreate(['product_id'=>$item->product_id,'warehouse_id'=>$warehouse->id]);
                $oldQty=(float)$stock->quantity_on_hand;$newQty=$oldQty+$delta;
                $stock->weighted_average_cost=$newQty>0?(($oldQty*(float)$stock->weighted_average_cost)+($delta*(float)$item->unit_cost))/$newQty:(float)$item->unit_cost;
                $stock->quantity_on_hand=$newQty;$stock->save();
                $stock->movements()->create(['type'=>'receipt','quantity_delta'=>$delta,'unit_cost'=>$item->unit_cost,'reference_type'=>PurchaseOrder::class,'reference_id'=>$purchaseOrder->id,'movement_date'=>now(),'created_by'=>auth()->id()]);
                $item->increment('qty_received',$delta);
            }
            $purchaseOrder->refresh();
            $allReceived=$purchaseOrder->items->every(fn($i)=>(float)$i->qty_received>=(float)$i->qty);
            $anyReceived=$purchaseOrder->items->contains(fn($i)=>(float)$i->qty_received>0);
            $purchaseOrder->update(['status'=>$allReceived?'received':($anyReceived?'partially_received':$purchaseOrder->status)]);
        });
        return back()->with('success','Stock received and inventory updated.');
    }
}
