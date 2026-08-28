<?php
namespace App\Http\Controllers;
use App\Models\{Customer,DeliveryNote,Invoice,SalesOrder};
class RecoverySnapshotController extends Controller
{
    public function __invoke(){return response()->streamDownload(function(){echo json_encode(['format'=>'ivory-erp-recovery-v1','generated_at'=>now()->toIso8601String(),'customers'=>Customer::withTrashed()->get(),'orders'=>SalesOrder::withTrashed()->with('items')->get(),'invoices'=>Invoice::withTrashed()->with('items','allocations')->get(),'deliveries'=>DeliveryNote::all()],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);},'ivory-erp-recovery-'.now()->format('Y-m-d_His').'.json',['Content-Type'=>'application/json']);}
}
