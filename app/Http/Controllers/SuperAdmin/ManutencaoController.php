<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckManutencaoSistema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class ManutencaoController extends Controller
{
    public function toggle(): RedirectResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Apenas Super Admin pode ativar ou desativar o modo manutenção.');
        }

        $ativo = Cache::get(CheckManutencaoSistema::CACHE_KEY, false);
        if ($ativo) {
            Cache::forget(CheckManutencaoSistema::CACHE_KEY);
            return redirect()->back()->with('success', 'Modo manutenção desativado. Sistema disponível.');
        }
        Cache::put(CheckManutencaoSistema::CACHE_KEY, true);
        return redirect()->back()->with('success', 'Modo manutenção ativado. Apenas Super Admin pode acessar.');
    }
}
