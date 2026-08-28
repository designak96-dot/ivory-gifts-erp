<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
class AuditController extends Controller
{
    public function index(){ $q=AuditLog::with('user')->latest();if(request('action'))$q->where('action',request('action'));if(request('model'))$q->where('auditable_type','like','%'.request('model').'%');return view('system.audit',['logs'=>$q->paginate(50)]); }
}
