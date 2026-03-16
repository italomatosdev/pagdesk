<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckManutencaoSistema;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

class ConfiguracoesSistemaController extends Controller
{
    public function index(): View
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Apenas Super Admin pode acessar as configurações do sistema.');
        }

        $manutencaoAtiva = Cache::get(CheckManutencaoSistema::CACHE_KEY, false);

        return view('super-admin.configuracoes.index', compact('manutencaoAtiva'));
    }
}
