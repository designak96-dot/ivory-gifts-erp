<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Jobs\ImportWooCommerceOrder;
use App\Models\SyncLog;
use Illuminate\Http\Request;
class WooCommerceWebhookController extends Controller
{
    public function __invoke(Request $request){$expected=(string)config('services.woocommerce.webhook_token');$provided=(string)$request->bearerToken();abort_if(!$expected||!hash_equals($expected,$provided),401,'Invalid integration token.');$payload=$request->validate(['id'=>'required','billing'=>'required|array','line_items'=>'required|array','status'=>'nullable|string','date_created'=>'nullable|string']);$ref=(string)$payload['id'];$hash=hash('sha256',json_encode($payload));$log=SyncLog::firstOrCreate(['source'=>'woocommerce','direction'=>'in','external_reference'=>$ref],['payload_hash'=>$hash,'status'=>'queued','payload'=>$payload]);if(!$log->wasRecentlyCreated)return response()->json(['status'=>'duplicate','reference'=>$ref]);ImportWooCommerceOrder::dispatch($log->id);return response()->json(['status'=>'accepted','reference'=>$ref],202);}
}
