<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs('setup.*')) return $next($request);
        try { $installed=Schema::hasTable('users') && DB::table('users')->exists(); } catch (\Throwable) { $installed=false; }
        if(!$installed) return redirect()->route('setup.create');
        return $next($request);
    }
}
