<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckManutencaoSistema
{
    public const CACHE_KEY = 'sistema.manutencao';

    /**
     * Rotas/prefixos que continuam acessíveis durante a manutenção.
     */
    protected function urisLiberados(): array
    {
        return [
            'login',
            'manutencao',
            'health',
            'health/*',
            'cadastro/cliente*',
            'logout',
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!Cache::get(self::CACHE_KEY, false)) {
            return $next($request);
        }

        foreach ($this->urisLiberados() as $uri) {
            if ($request->is($uri)) {
                return $next($request);
            }
        }

        if ($request->user() && $request->user()->isSuperAdmin()) {
            return $next($request);
        }

        return redirect()->route('manutencao');
    }
}
