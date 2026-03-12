<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocalePtBr
{
    /**
     * Define o locale da aplicação como pt_BR (mensagens de auth e validação em português).
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('pt_BR');
        return $next($request);
    }
}
