<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class AdminAccess
{
    /**
     * Allow admins through, and let other users act only on their own
     * account when the route carries a {user} parameter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $routeUser = $request->route('user');

        $isOwnAccount = $routeUser instanceof User && $routeUser->is($user);

        if ($user->isAdmin() || $isOwnAccount) {
            return $next($request);
        }

        abort(403);
    }
}
