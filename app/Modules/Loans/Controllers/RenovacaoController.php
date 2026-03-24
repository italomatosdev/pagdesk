<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Emprestimo;
use App\Support\OperacaoPreferida;
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

        // Restringir por operação: Super Admin vê todas; gestor/admin vê todas da op.; consultor só as suas
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $opsGestorAdmin = $user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']);
                $opsConsultor = $user->getOperacoesIdsOndeTemPapel(['consultor']);
                $opsSoConsultor = array_values(array_diff($opsConsultor, $opsGestorAdmin));

                $query->where(function ($q) use ($opsGestorAdmin, $opsSoConsultor, $user) {
                    if (!empty($opsGestorAdmin)) {
                        $q->whereIn('operacao_id', $opsGestorAdmin);
                    }
                    if (!empty($opsSoConsultor)) {
                        $q->orWhere(function ($q2) use ($opsSoConsultor, $user) {
                            $q2->whereIn('operacao_id', $opsSoConsultor)->where('consultor_id', $user->id);
                        });
                    }
                });
            }
        }

        // Filtro por nome ou CPF do cliente
        if ($request->filled('cliente_busca')) {
            $termo = trim($request->cliente_busca);
            $digits = preg_replace('/[^0-9]/', '', $termo);
            $query->whereHas('cliente', function ($q) use ($termo, $digits) {
                $q->where(function ($q2) use ($termo, $digits) {
                    $q2->where('nome', 'like', '%' . $termo . '%');
                    if ($digits !== '') {
                        $q2->orWhere('documento', 'like', '%' . $digits . '%');
                    }
                });
            });
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $operacoesIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($operacoesIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIdsList)->orderBy('nome')->get()
                : collect([]);
        }
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId !== null && $user->temAcessoOperacao($operacaoId)) {
            $query->where('operacao_id', $operacaoId);
            if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
                $query->where('consultor_id', $user->id);
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

        return view('renovacoes.index', compact('emprestimos', 'renovacoesPorCliente', 'operacoes', 'operacaoId'));
    }

    /**
     * Mostrar histórico de renovações de um cliente específico
     */
    public function showCliente(Request $request, int $clienteId): View
    {
        $cliente = Cliente::findOrFail($clienteId);
        $user = auth()->user();

        // Mesmo critério do ClienteController::show: só ver se tem acesso ao cliente (via operação)
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds)) {
                abort(403, 'Você não tem acesso a este cliente.');
            }
            if (!$cliente->operationClients()->whereIn('operacao_id', $operacoesIds)->exists()) {
                abort(403, 'Você não tem acesso a este cliente.');
            }
        }

        // Buscar todos os empréstimos do cliente que são renovações ou têm renovações
        $query = Emprestimo::with(['operacao', 'consultor', 'emprestimoOrigem', 'renovacoes', 'parcelas'])
            ->where('cliente_id', $clienteId)
            ->where(function ($q) {
                $q->whereNotNull('emprestimo_origem_id')
                  ->orWhereHas('renovacoes');
            });

        // Restringir por operação: apenas Super Admin vê todas
        if (!$user->isSuperAdmin()) {
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

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::query()->orderBy('nome')->pluck('id')->all()
            : $user->getOperacoesIds();
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);

        return view('renovacoes.show-cliente', compact('cliente', 'cadeiasRenovacao', 'operacaoId'));
    }
}
