<?php
namespace App\Http\Controllers;
use App\Models\{Role,User};
use Illuminate\Http\Request;
class UserController extends Controller
{
    public function index(){return view('users.index',['users'=>User::with('roles')->paginate(25),'roles'=>Role::orderBy('label')->get()]);}
    public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:190|unique:users','password'=>'required|string|min:12','role_id'=>'required|exists:roles,id','phone'=>'nullable|string|max:30']);$role=$d['role_id'];unset($d['role_id']);$u=User::create($d+['is_active'=>true]);$u->roles()->sync([$role]);return back()->with('success','User created.');}
    public function update(Request $r,User $user){abort_if($user->id===auth()->id()&&$r->boolean('is_active')===false,422,'You cannot deactivate your own account.');$d=$r->validate(['name'=>'required|string|max:100','role_id'=>'required|exists:roles,id','is_active'=>'required|boolean','password'=>'nullable|string|min:12']);$role=$d['role_id'];unset($d['role_id']);if(empty($d['password']))unset($d['password']);$user->update($d);$user->roles()->sync([$role]);return back()->with('success',isset($d['password'])?'User updated and password changed.':'User updated.');}
    public function destroy(User $user){abort_unless(auth()->user()->hasPermission('users.delete'),403);abort_if($user->id===auth()->id(),422,'You cannot delete your own account.');$user->delete();return back()->with('success','User deleted.');}
}
