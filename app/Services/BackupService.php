<?php
namespace App\Services;
use App\Models\Backup;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use RuntimeException;
class BackupService
{
    public function run(string $type='manual'): Backup
    {
        $dir=storage_path('app/private/backups');File::ensureDirectoryExists($dir,0700,true);$stamp=now()->format('Ymd_His');$path="$dir/ivory_erp_{$type}_{$stamp}.sql.gz";$record=Backup::create(['type'=>$type,'file_path'=>$path,'status'=>'running','started_at'=>now()]);
        try{$driver=config('database.default');if($driver==='sqlite'){$source=config('database.connections.sqlite.database');$sql=file_get_contents($source);file_put_contents($path,gzencode($sql,9));}else{$c=config("database.connections.$driver");$defaults=tempnam(sys_get_temp_dir(),'ivory-db-');try{$escape=static fn($value)=>addcslashes((string)$value,"\\\"");file_put_contents($defaults,"[client]\nhost=\"{$escape($c['host'])}\"\nport=\"{$escape($c['port'])}\"\nuser=\"{$escape($c['username'])}\"\npassword=\"{$escape($c['password'])}\"\n");chmod($defaults,0600);$process=new Process(['mysqldump',"--defaults-extra-file=$defaults",'--single-transaction','--quick','--routines','--triggers',(string)$c['database']]);$process->setTimeout(600);$process->run();if(!$process->isSuccessful())throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());file_put_contents($path,gzencode($process->getOutput(),9));}finally{@unlink($defaults);}}if(($key=config('app.backup_key'))){$encrypted=$path.'.enc';$p=new Process(['openssl','enc','-aes-256-cbc','-pbkdf2','-salt','-in',$path,'-out',$encrypted,'-pass','env:IVORY_BACKUP_KEY'],null,['IVORY_BACKUP_KEY'=>$key]);$p->mustRun();unlink($path);$path=$encrypted;}$record->update(['file_path'=>$path,'size_bytes'=>filesize($path),'checksum'=>hash_file('sha256',$path),'status'=>'completed','completed_at'=>now()]);return $record;}catch(\Throwable $e){$record->update(['status'=>'failed','error'=>$e->getMessage(),'completed_at'=>now()]);throw $e;}
    }
    public function prune(int $keep=30): int {$old=Backup::where('status','completed')->latest('completed_at')->skip($keep)->take(500)->get();$count=0;foreach($old as $b){if(is_file($b->file_path))@unlink($b->file_path);$b->delete();$count++;}return $count;}
}
