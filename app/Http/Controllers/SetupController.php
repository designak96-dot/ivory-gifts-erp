<?php
namespace App\Http\Controllers;
use App\Models\{Role,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class SetupController extends Controller
{
    private function available(): bool { try{return User::count()===0;}catch(\Throwable){return false;} }
    public function create(){abort_unless($this->available(),404);return view('auth.setup');}
    public function store(Request $request){abort_unless($this->available(),404);$data=$request->validate(['name'=>'required|string|max:100','email'=>'required|email|max:190|unique:users','password'=>'required|string|min:12|confirmed']);$user=DB::transaction(function()use($data){$u=User::create($data+['is_active'=>true]);$u->roles()->attach(Role::where('name','owner')->firstOrFail());return $u;});Auth::login($user);$request->session()->regenerate();return redirect()->route('dashboard')->with('success','Owner account created. Ivory Gifts ERP is ready.');}
}
