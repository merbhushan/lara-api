<?php

namespace App\Http\Middleware;

use Closure;

class ValidateUserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $objUser = $request->user();

        // Validate User Status.
        if(empty($objUser->is_active)){
            return redirect('api/error/INACTIVE_USER');
        }

        session([
            'user' => $objUser,
            'user_id' => $objUser->id
        ]);

        return $next($request);
    }
}
