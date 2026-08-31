<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
class AuditController extends Controller
{
    private const FINANCIAL_MODELS = ['Invoice', 'Payment', 'Expense', 'SalesOrder', 'JournalEntry', 'CreditNote'];

    public function index(){ $q=AuditLog::with('user')->latest();if(request('action'))$q->where('action',request('action'));if(request('model'))$q->where('auditable_type','like','%'.request('model').'%');if(request('financial'))$q->where(function($x){foreach(self::FINANCIAL_MODELS as $m)$x->orWhere('auditable_type','like',"%{$m}");});return view('system.audit',['logs'=>$q->paginate(50)]); }
}
