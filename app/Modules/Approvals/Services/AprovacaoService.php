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

        // Aplicar filtro de operações do usuário (exceto administradores)
        if ($user && !$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                // Se não tem operações vinculadas, retorna vazio
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
    public function aprovar(int $emprestimoId, int $aprovadorId, ?string $motivo = null): Emprestimo
    {
        // VALIDAÇÃO: Verificar se empréstimo está pendente
        $emprestimo = Emprestimo::with('liberacao')->findOrFail($emprestimoId);
        
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
    public function rejeitar(int $emprestimoId, int $aprovadorId, string $motivoRejeicao): Emprestimo
    {
        // VALIDAÇÃO: Verificar se empréstimo está pendente
        $emprestimo = Emprestimo::with('liberacao')->findOrFail($emprestimoId);
        
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

