<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUsuarioAtivo
{
    /**
     * Rotas que não exigem usuário ativo (evitar loop ao redirecionar).
     */
    protected function urisLiberados(): array
    {
        return [
            'login',
            'logout',
            'conta-bloqueada',
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return $next($request);
        }

        foreach ($this->urisLiberados() as $uri) {
            if ($request->is($uri)) {
                return $next($request);
            }
        }

        if (!$request->user()->isAtivo()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('conta.bloqueada')->with('motivo', $request->user()->motivo_bloqueio);
        }

        return $next($request);
    }
}
