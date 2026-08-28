<?php
namespace App\Http\Controllers;
use App\Models\{ChartOfAccount,JournalEntry,JournalLine};
use Illuminate\Support\Facades\DB;
class AccountingController extends Controller
{
    public function index(){return view('accounting.index',['entries'=>JournalEntry::with('lines.account')->latest('entry_date')->paginate(20)]);}
    public function trialBalance(){ $rows=ChartOfAccount::leftJoin('journal_lines','chart_of_accounts.id','=','journal_lines.account_id')->leftJoin('journal_entries',fn($j)=>$j->on('journal_lines.journal_entry_id','=','journal_entries.id')->where('journal_entries.status','posted'))->groupBy('chart_of_accounts.id','chart_of_accounts.code','chart_of_accounts.name','chart_of_accounts.type')->orderBy('chart_of_accounts.code')->get(['chart_of_accounts.code','chart_of_accounts.name','chart_of_accounts.type',DB::raw('COALESCE(SUM(journal_lines.debit),0) as debit'),DB::raw('COALESCE(SUM(journal_lines.credit),0) as credit')]);return view('accounting.trial-balance',compact('rows'));}
}
