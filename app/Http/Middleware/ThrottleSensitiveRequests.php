<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica rate limit apenas em requisições que alteram dados (POST, PUT, PATCH, DELETE).
 * Usa o limiter nomeado "sensitive" (40 req/min por usuário).
 */
class ThrottleSensitiveRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, 'sensitive');
    }
}
