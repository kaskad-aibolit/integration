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
        $hashedToken = hash('sha256', $token);
        if ($hashedToken && $hashedToken == '5bcab169940b4e000a4b8be4ed4c3258fe10399f513ef72a5ff2ec1c58d3f836') {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
