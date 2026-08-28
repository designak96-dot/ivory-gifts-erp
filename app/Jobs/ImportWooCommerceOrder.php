<?php
namespace App\Jobs;
use App\Models\{Customer,Product,SalesOrder,SyncLog};
use App\Services\{NumberingService,PhoneNormalizer};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
class ImportWooCommerceOrder implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=3; public array $backoff=[60,300,900];
    public function __construct(public int $syncLogId){}
    public function handle(NumberingService $numbers,PhoneNormalizer $phones):void{$log=SyncLog::findOrFail($this->syncLogId);$p=$log->payload;DB::transaction(function()use($log,$p,$numbers,$phones){$billing=$p['billing'];$phone=$phones->normalize($billing['phone']??null);$customer=$phone?Customer::where('phone',$phone)->first():null;if(!$customer&&!empty($billing['email']))$customer=Customer::where('email',$billing['email'])->first();$customer??=Customer::create(['customer_code'=>$numbers->next('customer'),'name'=>trim(($billing['first_name']??'').' '.($billing['last_name']??''))?:'WooCommerce Customer','phone'=>$phone,'whatsapp'=>$phone,'email'=>$billing['email']??null,'billing_address'=>trim(implode(', ',array_filter([$billing['address_1']??null,$billing['address_2']??null,$billing['city']??null]))),'delivery_address'=>trim(implode(', ',array_filter([$billing['address_1']??null,$billing['address_2']??null,$billing['city']??null]))),'source'=>'woocommerce','status'=>'active']);$order=SalesOrder::create(['order_number'=>$numbers->next('sales_order'),'order_month'=>today()->startOfMonth(),'customer_id'=>$customer->id,'order_date'=>today(),'confirmation_status'=>'waiting','design_status'=>'need_design','production_status'=>'waiting','delivery_status'=>'not_scheduled','payment_status'=>'unpaid','subtotal'=>0,'tax_total'=>0,'grand_total'=>0,'notes'=>'WooCommerce order #'.$p['id']]);$subtotal=0;$tax=0;foreach($p['line_items'] as $line){$product=Product::where('sku',$line['sku']??'')->first();$qty=(float)($line['quantity']??1);$total=(float)($line['total']??0);$lineTax=(float)($line['total_tax']??0);$order->items()->create(['product_id'=>$product?->id,'description'=>$line['name']??'WooCommerce item','qty'=>$qty,'unit_price'=>$qty?$total/$qty:$total,'tax_amount'=>$lineTax,'line_total'=>$total+$lineTax]);$subtotal+=$total;$tax+=$lineTax;}$order->update(['subtotal'=>$subtotal,'tax_total'=>$tax,'grand_total'=>$subtotal+$tax]);\App\Models\ProductionJob::create(['job_number'=>$numbers->next('production_job'),'sales_order_id'=>$order->id,'stage'=>'waiting_for_design','sale_value'=>$order->grand_total,'estimated_profit'=>$order->grand_total]);$log->update(['status'=>'success','synced_at'=>now()]);});}
    public function failed(\Throwable $e):void{SyncLog::whereKey($this->syncLogId)->update(['status'=>'failed','last_error'=>$e->getMessage(),'retry_count'=>$this->tries]);}
}
