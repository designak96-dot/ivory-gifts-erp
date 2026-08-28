<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
class SyncVersionController extends Controller
{
    public function __invoke(){ $tables=['sales_orders','production_jobs','delivery_notes','invoices','payments'];$latest='';foreach($tables as $table){$value=DB::table($table)->max('updated_at');if($value>$latest)$latest=$value;}return response()->json(['version'=>$latest,'server_time'=>now()->toIso8601String()]); }
}
