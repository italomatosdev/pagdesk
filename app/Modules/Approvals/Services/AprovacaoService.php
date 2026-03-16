<?php

namespace App\Modules\Approvals\Services;

use App\Modules\Approvals\Models\Aprovacao;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Services\EmprestimoService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AprovacaoService
{
    use Auditable;

    /**
     * Listar empréstimos pendentes de aprovação
     *
     * @param int|null $operacaoId Filtrar por operação
     * @param \App\Models\User|null $user
     * @return Collection
     */
    public function listarPendentes(?int $operacaoId = null, ?\App\Models\User $user = null): Collection
    {
        $query = Emprestimo::with(['cliente', 'operacao', 'consultor', 'parcelas'])
            ->where('status', 'pendente');

        // Aplicar filtro: Super Admin vê todas; demais só operações onde tem papel administrador
        if ($user && !$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIdsOndeTemPapel(['administrador']);
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }

        return $query->orderBy('created_at', 'asc')->get();
    }

    /**
     * Aprovar empréstimo
     *
     * @param int $emprestimoId
     * @param int $aprovadorId
     * @param string|null $motivo
     * @return Emprestimo
     */
    public function aprovar(int $emprestimoId, int $aprovadorId, ?string $motivo = null, ?\App\Models\User $aprovador = null): Emprestimo
    {
        $aprovador = $aprovador ?? \App\Models\User::find($aprovadorId);
        // VALIDAÇÃO: Verificar se empréstimo está pendente
        $emprestimo = Emprestimo::with('liberacao')->findOrFail($emprestimoId);

        if ($aprovador && !$aprovador->isSuperAdmin()) {
            if (!$aprovador->temPapelNaOperacao($emprestimo->operacao_id, 'administrador')) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Você não tem permissão para aprovar empréstimos desta operação.',
                ]);
            }
        }

        if ($emprestimo->status !== 'pendente') {
            $mensagem = match($emprestimo->status) {
                'aprovado' => 'Este empréstimo já foi aprovado.',
                'ativo' => 'Este empréstimo já está ativo.',
                'rejeitado' => 'Este empréstimo foi rejeitado e não pode ser aprovado.',
                default => 'Apenas empréstimos pendentes podem ser aprovados. Status atual: ' . $emprestimo->status
            };
            throw \Illuminate\Validation\ValidationException::withMessages([
                'emprestimo' => $mensagem
            ]);
        }

        // VALIDAÇÃO: Verificar se já existe liberação (não deveria, mas por segurança)
        if ($emprestimo->liberacao && $emprestimo->liberacao->status !== 'aguardando') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'emprestimo' => 'Este empréstimo já possui uma liberação processada.'
            ]);
        }

        $emprestimoService = new EmprestimoService();
        $emprestimo = $emprestimoService->aprovar($emprestimoId, $aprovadorId, $motivo);

        // Criar registro de aprovação
        Aprovacao::create([
            'emprestimo_id' => $emprestimo->id,
            'aprovado_por' => $aprovadorId,
            'decisao' => 'aprovado',
            'motivo' => $motivo,
            'empresa_id' => $emprestimo->empresa_id,
        ]);

        return $emprestimo;
    }

    /**
     * Rejeitar empréstimo
     *
     * @param int $emprestimoId
     * @param int $aprovadorId
     * @param string $motivoRejeicao
     * @return Emprestimo
     */
    public function rejeitar(int $emprestimoId, int $aprovadorId, string $motivoRejeicao, ?\App\Models\User $aprovador = null): Emprestimo
    {
        $aprovador = $aprovador ?? \App\Models\User::find($aprovadorId);
        // VALIDAÇÃO: Verificar se empréstimo está pendente
        $emprestimo = Emprestimo::with('liberacao')->findOrFail($emprestimoId);

        if ($aprovador && !$aprovador->isSuperAdmin()) {
            $operacoesIds = $aprovador->getOperacoesIds();
            if (empty($operacoesIds) || !in_array((int) $emprestimo->operacao_id, $operacoesIds, true)) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Você não tem permissão para rejeitar empréstimos desta operação.',
                ]);
            }
        }

        if ($emprestimo->status !== 'pendente') {
            $mensagem = match($emprestimo->status) {
                'aprovado' => 'Não é possível rejeitar. Este empréstimo já foi aprovado. Se necessário, cancele a liberação primeiro.',
                'ativo' => 'Não é possível rejeitar. Este empréstimo já está ativo e possui pagamentos registrados.',
                'rejeitado' => 'Este empréstimo já foi rejeitado.',
                default => 'Apenas empréstimos pendentes podem ser rejeitados. Status atual: ' . $emprestimo->status
            };
            throw \Illuminate\Validation\ValidationException::withMessages([
                'emprestimo' => $mensagem
            ]);
        }

        // VALIDAÇÃO: Verificar se já existe liberação (não pode rejeitar se já liberou)
        if ($emprestimo->liberacao) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'emprestimo' => 'Não é possível rejeitar. Este empréstimo já possui uma liberação criada.'
            ]);
        }

        $emprestimoService = new EmprestimoService();
        $emprestimo = $emprestimoService->rejeitar($emprestimoId, $aprovadorId, $motivoRejeicao);

        // Criar registro de aprovação (rejeição)
        Aprovacao::create([
            'emprestimo_id' => $emprestimo->id,
            'aprovado_por' => $aprovadorId,
            'decisao' => 'rejeitado',
            'motivo' => $motivoRejeicao,
            'empresa_id' => $emprestimo->empresa_id,
        ]);

        return $emprestimo;
    }
}

