<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if(!$request->user()?->is_active){Auth::logout(); return redirect()->route('login')->withErrors(['email'=>'This account is inactive.']);}
        return $next($request);
    }
}
