<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if($request->expectsJson())
        {
            return null;
        }
        else
        {
            return response()->json(['status'=>false,'message'=>'Token Not Verified']);
        }
        // dd($request);
        // return $request->expectsJson() ? null : route('login');
    }
}
