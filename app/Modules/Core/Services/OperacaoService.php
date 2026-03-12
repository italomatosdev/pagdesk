<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Traits\Auditable;

class OperacaoService
{
    use Auditable;

    /**
     * Criar nova operação
     *
     * @param array $dados
     * @return Operacao
     */
    public function criar(array $dados): Operacao
    {
        $operacao = Operacao::create($dados);

        // Auditoria
        self::auditar('criar_operacao', $operacao, null, $operacao->toArray());

        return $operacao;
    }

    /**
     * Atualizar operação
     *
     * @param int $operacaoId
     * @param array $dados
     * @return Operacao
     */
    public function atualizar(int $operacaoId, array $dados): Operacao
    {
        $operacao = Operacao::findOrFail($operacaoId);
        
        $oldValues = $operacao->toArray();
        
        $operacao->update($dados);

        // Auditoria
        self::auditar(
            'atualizar_operacao',
            $operacao,
            $oldValues,
            $operacao->toArray()
        );

        return $operacao->fresh();
    }

    /**
     * Listar todas as operações ativas
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listarAtivas()
    {
        return Operacao::where('ativo', true)->get();
    }
}

