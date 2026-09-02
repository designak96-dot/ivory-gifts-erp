<?php
namespace App\Http\Controllers;
use App\Models\{Backup,SyncLog};
use Illuminate\Support\Facades\DB;
class SystemHealthController extends Controller
{
    public function __invoke(){ $checks=[];try{DB::select('select 1');$checks['Database']=['ok','Connected'];}catch(\Throwable $e){$checks['Database']=['error',$e->getMessage()];}$checks['Storage']=is_writable(storage_path())?['ok','Writable']:['error','Not writable'];$checks['Application key']=config('app.key')?['ok','Configured']:['error','Missing'];$checks['Debug mode']=config('app.debug')?['warning','Enabled — disable in production']:['ok','Disabled'];$last=Backup::latest('completed_at')->first();$checks['Latest backup']=$last?[$last->status==='completed'?'ok':'error',($last->completed_at?->diffForHumans()??$last->status)]:['warning','No backup yet'];$failed=SyncLog::where('status','failed')->count();$checks['Integration sync']=$failed?['warning',"{$failed} failed item(s)"]:['ok','No failed sync items'];
        // Real, live values read directly from the running PHP process —
        // not assumed from a config file that may or may not actually be
        // in effect. This is the only reliable way to confirm whether a
        // .user.ini upload-limit fix has actually taken hold on this
        // specific server, versus still being blocked by a lower value.
        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        $uploadMaxBytes = $this->toBytes($uploadMax);
        $postMaxBytes = $this->toBytes($postMax);
        $effectiveBytes = min($uploadMaxBytes, $postMaxBytes);
        $checks['PHP upload limit (upload_max_filesize)'] = [$uploadMaxBytes >= 20*1024*1024 ? 'ok' : 'warning', $uploadMax." — the largest single file PHP will accept right now"];
        $checks['PHP upload limit (post_max_size)'] = [$postMaxBytes >= 20*1024*1024 ? 'ok' : 'warning', $postMax." — the largest total request PHP will accept right now"];
        $checks['Effective bulk-upload ceiling'] = [$effectiveBytes >= 20*1024*1024 ? 'ok' : 'error', $this->formatBytes($effectiveBytes)." — the real limit is whichever of the two above is smaller"];
        return view('system.health',compact('checks','last'));
    }

    private function toBytes(string $val): int
    {
        $val = trim($val);
        $unit = strtolower(substr($val, -1));
        $num = (float) $val;
        return match ($unit) {
            'g' => (int) ($num * 1024 * 1024 * 1024),
            'm' => (int) ($num * 1024 * 1024),
            'k' => (int) ($num * 1024),
            default => (int) $num,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024*1024*1024) return round($bytes/1024/1024/1024, 1).'GB';
        if ($bytes >= 1024*1024) return round($bytes/1024/1024, 1).'MB';
        return round($bytes/1024, 1).'KB';
    }
}
