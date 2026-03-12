<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RenovacaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Relatório de renovações por cliente
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = Emprestimo::with(['cliente', 'operacao', 'consultor', 'emprestimoOrigem', 'renovacoes'])
            ->where(function ($q) {
                $q->whereNotNull('emprestimo_origem_id') // Empréstimos que são renovações
                  ->orWhereHas('renovacoes'); // Ou que têm renovações
            });

        // Aplicar filtro de operações do usuário (exceto administradores)
        if (!$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Filtros
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('operacao_id')) {
            if ($user->temAcessoOperacao($request->operacao_id)) {
                $query->where('operacao_id', $request->operacao_id);
            }
        }

        $emprestimos = $query->orderBy('created_at', 'desc')->paginate(20);

        // Agrupar por cliente para mostrar histórico
        $renovacoesPorCliente = [];
        foreach ($emprestimos as $emprestimo) {
            $clienteId = $emprestimo->cliente_id;
            if (!isset($renovacoesPorCliente[$clienteId])) {
                $renovacoesPorCliente[$clienteId] = [
                    'cliente' => $emprestimo->cliente,
                    'historico' => [],
                ];
            }
            
            // Buscar histórico completo de renovações
            $historico = $emprestimo->getHistoricoRenovacoes();
            $renovacoesPorCliente[$clienteId]['historico'] = $historico->unique('id')->values();
        }

        // Filtrar operações disponíveis
        if ($user->hasRole('administrador')) {
            $operacoes = \App\Modules\Core\Models\Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? \App\Modules\Core\Models\Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        return view('renovacoes.index', compact('emprestimos', 'renovacoesPorCliente', 'operacoes'));
    }

    /**
     * Mostrar histórico de renovações de um cliente específico
     */
    public function showCliente(int $clienteId): View
    {
        $cliente = Cliente::findOrFail($clienteId);
        $user = auth()->user();

        // Buscar todos os empréstimos do cliente que são renovações ou têm renovações
        $query = Emprestimo::with(['operacao', 'consultor', 'emprestimoOrigem', 'renovacoes', 'parcelas'])
            ->where('cliente_id', $clienteId)
            ->where(function ($q) {
                $q->whereNotNull('emprestimo_origem_id')
                  ->orWhereHas('renovacoes');
            });

        // Aplicar filtro de operações
        if (!$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $emprestimos = $query->orderBy('data_inicio')->get();

        // Agrupar em cadeias de renovações
        $cadeiasRenovacao = [];
        $processados = [];

        foreach ($emprestimos as $emprestimo) {
            if (in_array($emprestimo->id, $processados)) {
                continue;
            }

            // Buscar empréstimo original (primeiro da cadeia)
            $original = $emprestimo;
            while ($original->emprestimoOrigem) {
                $original = $original->emprestimoOrigem;
            }

            // Se já processamos este original, pular
            if (in_array($original->id, $processados)) {
                continue;
            }

            // Buscar toda a cadeia
            $cadeia = $original->getHistoricoRenovacoes();
            $cadeiasRenovacao[] = [
                'original' => $original,
                'cadeia' => $cadeia,
                'total_renovacoes' => $cadeia->count() - 1, // Menos o original
            ];

            // Marcar todos como processados
            foreach ($cadeia as $emp) {
                $processados[] = $emp->id;
            }
        }

        return view('renovacoes.show-cliente', compact('cliente', 'cadeiasRenovacao'));
    }
}
