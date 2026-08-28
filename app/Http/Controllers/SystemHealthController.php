<?php
namespace App\Http\Controllers;
use App\Models\{Backup,SyncLog};
use Illuminate\Support\Facades\DB;
class SystemHealthController extends Controller
{
    public function __invoke(){ $checks=[];try{DB::select('select 1');$checks['Database']=['ok','Connected'];}catch(\Throwable $e){$checks['Database']=['error',$e->getMessage()];}$checks['Storage']=is_writable(storage_path())?['ok','Writable']:['error','Not writable'];$checks['Application key']=config('app.key')?['ok','Configured']:['error','Missing'];$checks['Debug mode']=config('app.debug')?['warning','Enabled — disable in production']:['ok','Disabled'];$last=Backup::latest('completed_at')->first();$checks['Latest backup']=$last?[$last->status==='completed'?'ok':'error',($last->completed_at?->diffForHumans()??$last->status)]:['warning','No backup yet'];$failed=SyncLog::where('status','failed')->count();$checks['Integration sync']=$failed?['warning',"{$failed} failed item(s)"]:['ok','No failed sync items'];return view('system.health',compact('checks','last'));}
}
