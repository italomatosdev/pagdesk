<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    /**
     * Handle an incoming request.
     * Aplica escopo de tenant (empresa) automaticamente em todas as queries
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Super Admin não precisa de escopo
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        // Para outros usuários, o escopo será aplicado via Global Scope nos models
        return $next($request);
    }
}
