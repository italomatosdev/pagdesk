<?php

namespace App\Modules\Core\Services;

use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Cash\Models\Settlement;
use App\Models\User;
use App\Modules\Core\Models\Operacao;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KanbanService
{
    /**
     * Buscar todas as pendências para o Kanban Board
     */
    public function buscarPendencias(?int $operacaoId = null, ?User $user = null): array
    {
        if (!$user) {
            $user = auth()->user();
        }

        $operacoesIds = $user->getOperacoesIds();

        // Helper para aplicar filtro de operação (Super Admin vê todas; demais só das operações vinculadas)
        $aplicarFiltroOperacao = function ($query) use ($operacaoId, $operacoesIds, $user) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (!$user->isSuperAdmin() && !empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } elseif (!$user->isSuperAdmin()) {
                $query->whereRaw('1 = 0');
            }
        };

        $pendencias = [
            'aprovacoes' => $this->buscarAprovacoesPendentes($user, $aplicarFiltroOperacao),
            'liberacoes' => $this->buscarLiberacoesPendentes($user, $aplicarFiltroOperacao),
            'em_acao' => $this->buscarEmAcao($user, $aplicarFiltroOperacao),
            'aguardando' => $this->buscarAguardandoConfirmacao($user, $aplicarFiltroOperacao),
            'urgentes' => $this->buscarUrgentes($user, $aplicarFiltroOperacao),
        ];

        return $pendencias;
    }

    /**
     * Buscar aprovações pendentes
     */
    protected function buscarAprovacoesPendentes(User $user, callable $aplicarFiltroOperacao): Collection
    {
        $items = collect([]);

        // Empréstimos pendentes (apenas Admin)
        if ($user->hasRole('administrador')) {
            $emprestimos = Emprestimo::with(['cliente', 'consultor', 'operacao'])
                ->where('status', 'pendente')
                ->whereDoesntHave('aprovacao')
                ->when(true, $aplicarFiltroOperacao)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($emprestimos as $emprestimo) {
                $items->push([
                    'tipo' => 'emprestimo',
                    'id' => $emprestimo->id,
                    'titulo' => 'Empréstimo #' . $emprestimo->id,
                    'cliente' => $emprestimo->cliente->nome,
                    'consultor' => $emprestimo->consultor->name ?? '-',
                    'valor' => $emprestimo->valor_total,
                    'operacao' => $emprestimo->operacao->nome,
                    'data' => $emprestimo->created_at,
                    'dias_pendente' => floor($emprestimo->created_at->diffInDays(now())),
                    'url' => route('emprestimos.show', $emprestimo->id),
                    'acoes' => [
                        'aprovar' => route('aprovacoes.aprovar', ['emprestimoId' => $emprestimo->id]),
                        'rejeitar' => route('aprovacoes.rejeitar', ['emprestimoId' => $emprestimo->id]),
                    ],
                ]);
            }
        }

        // Prestações pendentes (Gestor e Admin)
        if ($user->hasAnyRole(['gestor', 'administrador'])) {
            $prestacoes = Settlement::with(['consultor', 'operacao'])
                ->where('status', 'pendente')
                ->when(true, function ($query) use ($aplicarFiltroOperacao) {
                    $aplicarFiltroOperacao($query);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($prestacoes as $prestacao) {
                $items->push([
                    'tipo' => 'prestacao',
                    'id' => $prestacao->id,
                    'titulo' => 'Prestação #' . $prestacao->id,
                    'consultor' => $prestacao->consultor->name,
                    'valor' => $prestacao->valor_total,
                    'operacao' => $prestacao->operacao->nome,
                    'periodo' => $prestacao->data_inicio->format('d/m/Y') . ' até ' . $prestacao->data_fim->format('d/m/Y'),
                    'data' => $prestacao->created_at,
                    'dias_pendente' => floor($prestacao->created_at->diffInDays(now())),
                    'url' => route('prestacoes.show', $prestacao->id),
                    'acoes' => [
                        'aprovar' => route('prestacoes.aprovar', $prestacao->id),
                        'rejeitar' => route('prestacoes.rejeitar', $prestacao->id),
                    ],
                ]);
            }
        }

        return $items;
    }

    /**
     * Buscar liberações pendentes
     */
    protected function buscarLiberacoesPendentes(User $user, callable $aplicarFiltroOperacao): Collection
    {
        $items = collect([]);

        // Liberações aguardando (Gestor e Admin)
        if ($user->hasAnyRole(['gestor', 'administrador'])) {
            $liberacoes = LiberacaoEmprestimo::with(['emprestimo.cliente', 'consultor', 'emprestimo.operacao'])
                ->where('status', 'aguardando')
                ->when(true, function ($query) use ($aplicarFiltroOperacao) {
                    $query->whereHas('emprestimo', $aplicarFiltroOperacao);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($liberacoes as $liberacao) {
                $items->push([
                    'tipo' => 'liberacao',
                    'id' => $liberacao->id,
                    'titulo' => 'Liberação #' . $liberacao->id,
                    'cliente' => $liberacao->emprestimo->cliente->nome,
                    'consultor' => $liberacao->consultor->name,
                    'valor' => $liberacao->valor_liberado,
                    'operacao' => $liberacao->emprestimo->operacao->nome,
                    'emprestimo_id' => $liberacao->emprestimo_id,
                    'data' => $liberacao->created_at,
                    'dias_pendente' => floor($liberacao->created_at->diffInDays(now())),
                    'url' => route('liberacoes.show', $liberacao->id),
                ]);
            }
        }

        // Liberações liberadas aguardando pagamento ao cliente (Consultor)
        if ($user->hasRole('consultor')) {
            $liberacoes = LiberacaoEmprestimo::with(['emprestimo.cliente', 'emprestimo.operacao'])
                ->where('consultor_id', $user->id)
                ->where('status', 'liberado')
                ->when(true, function ($query) use ($aplicarFiltroOperacao) {
                    $query->whereHas('emprestimo', $aplicarFiltroOperacao);
                })
                ->orderBy('liberado_em', 'desc')
                ->get();

            foreach ($liberacoes as $liberacao) {
                $items->push([
                    'tipo' => 'liberacao_pagar',
                    'id' => $liberacao->id,
                    'titulo' => 'Pagar Cliente - Empréstimo #' . $liberacao->emprestimo_id,
                    'cliente' => $liberacao->emprestimo->cliente->nome,
                    'valor' => $liberacao->valor_liberado,
                    'operacao' => $liberacao->emprestimo->operacao->nome,
                    'emprestimo_id' => $liberacao->emprestimo_id,
                    'data' => $liberacao->liberado_em,
                    'dias_pendente' => floor($liberacao->liberado_em->diffInDays(now())),
                    'url' => route('liberacoes.show', $liberacao->id),
                    'acoes' => [
                        'confirmar' => route('liberacoes.confirmar-pagamento', $liberacao->id),
                    ],
                ]);
            }
        }

        return $items;
    }

    /**
     * Buscar itens em ação (cobranças do dia, prestações aguardando comprovante)
     */
    protected function buscarEmAcao(User $user, callable $aplicarFiltroOperacao): Collection
    {
        $items = collect([]);

        // Cobranças do dia (Consultor)
        if ($user->hasRole('consultor')) {
            $parcelas = Parcela::with(['emprestimo.cliente', 'emprestimo.operacao'])
                ->whereHas('emprestimo', function ($query) use ($user, $aplicarFiltroOperacao) {
                    $query->where('consultor_id', $user->id);
                    $aplicarFiltroOperacao($query);
                })
                ->where('status', '!=', 'paga')
                ->whereDate('data_vencimento', today())
                ->orderBy('data_vencimento', 'asc')
                ->get();

            foreach ($parcelas as $parcela) {
                $items->push([
                    'tipo' => 'cobranca',
                    'id' => $parcela->id,
                    'titulo' => 'Cobrança - Parcela ' . $parcela->numero . '/' . $parcela->emprestimo->numero_parcelas,
                    'cliente' => $parcela->emprestimo->cliente->nome,
                    'valor' => $parcela->valor - $parcela->valor_pago,
                    'operacao' => $parcela->emprestimo->operacao->nome,
                    'emprestimo_id' => $parcela->emprestimo_id,
                    'data' => $parcela->data_vencimento,
                    'url' => route('pagamentos.create', ['parcela_id' => $parcela->id]),
                    'acoes' => [
                        'pagar' => route('pagamentos.create', ['parcela_id' => $parcela->id]),
                    ],
                ]);
            }
        }

        // Prestações aprovadas aguardando comprovante (Gestor e Admin)
        if ($user->hasAnyRole(['gestor', 'administrador'])) {
            $prestacoes = Settlement::with(['consultor', 'operacao'])
                ->where('status', 'aprovado')
                ->when(true, function ($query) use ($aplicarFiltroOperacao) {
                    $aplicarFiltroOperacao($query);
                })
                ->orderBy('conferido_em', 'desc')
                ->get();

            foreach ($prestacoes as $prestacao) {
                $items->push([
                    'tipo' => 'prestacao_comprovante',
                    'id' => $prestacao->id,
                    'titulo' => 'Prestação #' . $prestacao->id . ' - Aguardando Comprovante',
                    'consultor' => $prestacao->consultor->name,
                    'valor' => $prestacao->valor_total,
                    'operacao' => $prestacao->operacao->nome,
                    'periodo' => $prestacao->data_inicio->format('d/m/Y') . ' até ' . $prestacao->data_fim->format('d/m/Y'),
                    'data' => $prestacao->conferido_em,
                    'dias_pendente' => floor($prestacao->conferido_em->diffInDays(now())),
                    'url' => route('prestacoes.show', $prestacao->id),
                ]);
            }
        }

        return $items;
    }

    /**
     * Buscar itens aguardando confirmação
     */
    protected function buscarAguardandoConfirmacao(User $user, callable $aplicarFiltroOperacao): Collection
    {
        $items = collect([]);

        // Prestações enviadas aguardando confirmação de recebimento (Gestor e Admin)
        if ($user->hasAnyRole(['gestor', 'administrador'])) {
            $prestacoes = Settlement::with(['consultor', 'operacao'])
                ->where('status', 'enviado')
                ->when(true, function ($query) use ($aplicarFiltroOperacao) {
                    $aplicarFiltroOperacao($query);
                })
                ->orderBy('enviado_em', 'desc')
                ->get();

            foreach ($prestacoes as $prestacao) {
                $items->push([
                    'tipo' => 'prestacao_recebimento',
                    'id' => $prestacao->id,
                    'titulo' => 'Prestação #' . $prestacao->id . ' - Confirmar Recebimento',
                    'consultor' => $prestacao->consultor->name,
                    'valor' => $prestacao->valor_total,
                    'operacao' => $prestacao->operacao->nome,
                    'periodo' => $prestacao->data_inicio->format('d/m/Y') . ' até ' . $prestacao->data_fim->format('d/m/Y'),
                    'data' => $prestacao->enviado_em,
                    'dias_pendente' => floor($prestacao->enviado_em->diffInDays(now())),
                    'url' => route('prestacoes.show', $prestacao->id),
                    'acoes' => [
                        'confirmar' => route('prestacoes.confirmar-recebimento', $prestacao->id),
                    ],
                ]);
            }
        }

        return $items;
    }

    /**
     * Buscar itens urgentes/atrasados
     */
    protected function buscarUrgentes(User $user, callable $aplicarFiltroOperacao): Collection
    {
        $items = collect([]);

        // Parcelas atrasadas (Consultor)
        if ($user->hasRole('consultor')) {
            $parcelas = Parcela::with(['emprestimo.cliente', 'emprestimo.operacao'])
                ->whereHas('emprestimo', function ($query) use ($user, $aplicarFiltroOperacao) {
                    $query->where('consultor_id', $user->id)
                          ->where('status', 'ativo'); // Apenas empréstimos ativos
                    $aplicarFiltroOperacao($query);
                })
                ->where('status', 'atrasada')
                ->orderBy('dias_atraso', 'desc')
                ->limit(20)
                ->get();

            foreach ($parcelas as $parcela) {
                $items->push([
                    'tipo' => 'parcela_atrasada',
                    'id' => $parcela->id,
                    'titulo' => 'Atrasada - Parcela ' . $parcela->numero . '/' . $parcela->emprestimo->numero_parcelas,
                    'cliente' => $parcela->emprestimo->cliente->nome,
                    'valor' => $parcela->valor - $parcela->valor_pago,
                    'operacao' => $parcela->emprestimo->operacao->nome,
                    'emprestimo_id' => $parcela->emprestimo_id,
                    'dias_atraso' => $parcela->dias_atraso ?? $parcela->calcularDiasAtraso(),
                    'data_vencimento' => $parcela->data_vencimento,
                    'url' => route('pagamentos.create', ['parcela_id' => $parcela->id]),
                    'acoes' => [
                        'pagar' => route('pagamentos.create', ['parcela_id' => $parcela->id]),
                    ],
                ]);
            }
        }

        // Parcelas atrasadas (Gestor e Admin - todas)
        if ($user->hasAnyRole(['gestor', 'administrador'])) {
            $parcelas = Parcela::with(['emprestimo.cliente', 'emprestimo.operacao', 'emprestimo.consultor'])
                ->whereHas('emprestimo', function ($q) use ($aplicarFiltroOperacao) {
                    $aplicarFiltroOperacao($q);
                    $q->where('status', 'ativo'); // Apenas empréstimos ativos
                })
                ->where('status', 'atrasada')
                ->orderBy('dias_atraso', 'desc')
                ->limit(20)
                ->get();

            foreach ($parcelas as $parcela) {
                $items->push([
                    'tipo' => 'parcela_atrasada',
                    'id' => $parcela->id,
                    'titulo' => 'Atrasada - Parcela ' . $parcela->numero . '/' . $parcela->emprestimo->numero_parcelas,
                    'cliente' => $parcela->emprestimo->cliente->nome,
                    'consultor' => $parcela->emprestimo->consultor->name ?? '-',
                    'valor' => $parcela->valor - $parcela->valor_pago,
                    'operacao' => $parcela->emprestimo->operacao->nome,
                    'emprestimo_id' => $parcela->emprestimo_id,
                    'dias_atraso' => $parcela->dias_atraso ?? $parcela->calcularDiasAtraso(),
                    'data_vencimento' => $parcela->data_vencimento,
                    'url' => route('emprestimos.show', $parcela->emprestimo_id),
                ]);
            }
        }

        return $items;
    }

    /**
     * Contar total de pendências
     */
    public function contarPendencias(?int $operacaoId = null, ?User $user = null): array
    {
        $pendencias = $this->buscarPendencias($operacaoId, $user);

        return [
            'aprovacoes' => $pendencias['aprovacoes']->count(),
            'liberacoes' => $pendencias['liberacoes']->count(),
            'em_acao' => $pendencias['em_acao']->count(),
            'aguardando' => $pendencias['aguardando']->count(),
            'urgentes' => $pendencias['urgentes']->count(),
            'total' => collect($pendencias)->sum(fn($collection) => $collection->count()),
        ];
    }
}
