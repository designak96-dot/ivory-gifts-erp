<?php
namespace App\Http\Controllers;
use App\Models\Backup;
use App\Services\BackupService;
class BackupController extends Controller
{
    public function index(){return view('system.backups',['backups'=>Backup::latest()->paginate(25)]);}
    public function store(BackupService $service){try{$b=$service->run('manual');return back()->with('success',"Backup completed: {$b->checksum}");}catch(\Throwable $e){return back()->withErrors(['backup'=>$e->getMessage()]);}}
    public function download(Backup $backup){abort_unless($backup->status==='completed'&&is_file($backup->file_path),404);return response()->download($backup->file_path,basename($backup->file_path));}
}
