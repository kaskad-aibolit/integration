<?php

namespace App\Http\Middleware;

use Closure;
use Hash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Log;
class EnsureTokenIsValid
{
    public function handle($request, Closure $next)
    {
        $token = $request->header('Authorization');
        if ($token && $token === 'Bearer a9322698-4171-4409-a429-0b24012ad25e') {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
