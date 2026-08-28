<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user()?->hasPermission($permission), 403, 'You do not have permission for this action.');
        return $next($request);
    }
}
