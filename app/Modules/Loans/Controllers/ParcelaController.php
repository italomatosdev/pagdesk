<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\ParcelaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParcelaController extends Controller
{
    protected ParcelaService $parcelaService;

    public function __construct(ParcelaService $parcelaService)
    {
        $this->middleware('auth');
        $this->parcelaService = $parcelaService;
    }

    /**
     * Cobranças do Dia
     * Lista parcelas vencendo hoje e atrasadas
     */
    public function cobrancasDoDia(Request $request): View
    {
        $user = auth()->user();
        
        // Super Admin não pode acessar Cobranças do Dia
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode acessar Cobranças do Dia.');
        }
        
        $operacaoIds = $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id');
        if ($operacaoId !== null && $operacaoId !== '') {
            $operacaoId = (int) $operacaoId;
            if (empty($operacaoIds) || !in_array($operacaoId, $operacaoIds, true)) {
                $operacaoId = null;
            }
        } else {
            $operacaoId = null;
        }

        $consultorId = empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])) ? $user->id : null;
        $cobrancas = $this->parcelaService->cobrancasDoDia($operacaoId, $consultorId, $operacaoIds);

        $operacoes = !empty($operacaoIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacaoIds)->orderBy('nome')->get()
            : collect([]);

        // Separar por status
        $vencendoHoje = $cobrancas->filter(function ($parcela) {
            return $parcela->venceHoje() && !$parcela->isAtrasada();
        });

        $atrasadas = $cobrancas->filter(function ($parcela) {
            return $parcela->isAtrasada();
        });

        $valorVencendoHoje = (float) $vencendoHoje->sum('valor');
        $valorAtrasado = (float) $atrasadas->sum('valor');

        return view('cobrancas.index', compact(
            'vencendoHoje',
            'atrasadas',
            'operacoes',
            'operacaoId',
            'valorVencendoHoje',
            'valorAtrasado'
        ));
    }

    /**
     * Parcelas Atrasadas
     * Lista todas as parcelas atrasadas com filtros e paginação
     */
    public function parcelasAtrasadas(Request $request): View
    {
        $user = auth()->user();
        
        $query = Parcela::with(['emprestimo.cliente', 'emprestimo.operacao', 'emprestimo.consultor'])
            ->where('status', 'atrasada')
            ->whereHas('emprestimo', function ($q) {
                $q->where('status', 'ativo'); // Apenas empréstimos ativos
            });

        // Aplicar filtro de operações do usuário (só Super Admin vê todas)
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereHas('emprestimo', function ($q) use ($operacoesIds) {
                    $q->whereIn('operacao_id', $operacoesIds)
                      ->where('status', 'ativo'); // Apenas empréstimos ativos
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Se for apenas consultor (sem gestor/admin em nenhuma op), filtrar apenas suas parcelas
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            $query->whereHas('emprestimo', function ($q) use ($user) {
                $q->where('consultor_id', $user->id);
            });
        }

        // Filtro por operação
        if ($request->filled('operacao_id')) {
            // Validar se o usuário tem acesso a essa operação
            if ($user->temAcessoOperacao($request->operacao_id)) {
                $query->whereHas('emprestimo', function ($q) use ($request) {
                    $q->where('operacao_id', $request->operacao_id);
                });
            }
        }

        // Filtro por consultor (apenas quem tem gestor/admin em alguma op)
        if ($request->filled('consultor_id') && !empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            $query->whereHas('emprestimo', function ($q) use ($request) {
                $q->where('consultor_id', $request->consultor_id);
            });
        }

        // Filtro por dias de atraso mínimo
        if ($request->filled('dias_atraso_min')) {
            $query->where('dias_atraso', '>=', $request->dias_atraso_min);
        }

        // Filtro por valor mínimo
        if ($request->filled('valor_min')) {
            $query->whereRaw('(valor - valor_pago) >= ?', [$request->valor_min]);
        }

        // Ordenação
        $ordenacao = $request->input('ordenacao', 'dias_atraso');
        $direcao = $request->input('direcao', 'desc');
        $query->orderBy($ordenacao, $direcao);

        // Paginação
        $parcelas = $query->paginate(15)->withQueryString();

        // Dados para filtros - filtrar operações disponíveis para o usuário
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }
        $operacoesIdsFiltro = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $consultores = empty($operacoesIdsFiltro)
            ? collect([])
            : \App\Models\User::where('ativo', true)->whereHas('operacoes', fn ($q) => $q->whereIn('operacoes.id', $operacoesIdsFiltro)->where('operacao_user.role', 'consultor'))->orderBy('name')->get();

        return view('parcelas.atrasadas', compact('parcelas', 'operacoes', 'consultores'));
    }
}
