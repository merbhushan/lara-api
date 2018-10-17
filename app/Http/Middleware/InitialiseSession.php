<?php

namespace App\Http\Middleware;

use Closure;

class InitialiseSession
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

        session([
            'user' => $objUser,
            'user_id' => $objUser->id,
            'scope' => explode('.', $request->route()->getName())[0]
        ]);
        
        return $next($request);
    }
}
