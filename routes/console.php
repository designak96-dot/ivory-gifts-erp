<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\{Artisan,Schedule};

Artisan::command('ivory:backup {--type=manual}', function (BackupService $service) {
    $backup=$service->run((string)$this->option('type'));
    $this->info("Backup completed: {$backup->file_path}");
})->purpose('Create a safe Ivory ERP database backup');

Artisan::command('ivory:db-test', function () {
    try { \Illuminate\Support\Facades\DB::connection()->getPdo(); $this->info('Database connection successful.'); return 0; }
    catch (Throwable $e) { $this->error('Database connection failed: '.$e->getMessage()); return 1; }
})->purpose('Test the configured database without changing data');

Artisan::command('ivory:verify', function () {
    $checks=['Database'=>fn()=>\Illuminate\Support\Facades\DB::select('select 1'),'Storage'=>fn()=>is_writable(storage_path())?:throw new RuntimeException('Storage is not writable'),'Application key'=>fn()=>config('app.key')?:throw new RuntimeException('APP_KEY missing'),'Migrations'=>fn()=>\Illuminate\Support\Facades\Schema::hasTable('users')?:throw new RuntimeException('Database not migrated')];
    $failed=false;foreach($checks as $name=>$check){try{$check();$this->info("PASS  {$name}");}catch(Throwable $e){$failed=true;$this->error("FAIL  {$name}: {$e->getMessage()}");}}return $failed?1:0;
})->purpose('Verify the installed Ivory ERP application');

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(2);
Schedule::command('ivory:backup --type=daily')->dailyAt('02:15')->withoutOverlapping(180);
Schedule::call(fn()=>app(BackupService::class)->prune((int)config('app.backup_retention',30)))->name('ivory-backup-prune')->dailyAt('03:15')->withoutOverlapping(60);
