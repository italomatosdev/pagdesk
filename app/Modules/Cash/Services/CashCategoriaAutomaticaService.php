<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\CategoriaMovimentacao;

class CashCategoriaAutomaticaService
{
    private const MAPEAMENTO = [
        'pagamento_parcela|entrada' => ['nome' => 'Recebimento de parcela', 'categoria_tipo' => 'entrada'],
        'estorno_pagamento|saida' => ['nome' => 'Estorno de recebimento', 'categoria_tipo' => 'despesa'],
        'quitacao_emprestimo|entrada' => ['nome' => 'Quitacao de emprestimo', 'categoria_tipo' => 'entrada'],
        'cancelamento_emprestimo|saida' => ['nome' => 'Estorno cancelamento (saida consultor)', 'categoria_tipo' => 'despesa'],
        'cancelamento_emprestimo|entrada' => ['nome' => 'Estorno cancelamento (entrada gestor)', 'categoria_tipo' => 'entrada'],
        'compensacao_cheque|entrada' => ['nome' => 'Compensacao de cheque', 'categoria_tipo' => 'entrada'],
        'pagamento_cheque_devolvido|entrada' => ['nome' => 'Pagamento cheque devolvido', 'categoria_tipo' => 'entrada'],
        'venda|entrada' => ['nome' => 'Venda a vista', 'categoria_tipo' => 'entrada'],
        'settlement|saida' => ['nome' => 'Prestacao de contas (saida consultor)', 'categoria_tipo' => 'despesa'],
        'settlement|entrada' => ['nome' => 'Prestacao de contas (entrada gestor)', 'categoria_tipo' => 'entrada'],
        'liberacao_emprestimo|saida' => ['nome' => 'Liberacao — saida gestor', 'categoria_tipo' => 'despesa'],
        'liberacao_emprestimo|entrada' => ['nome' => 'Liberacao — entrada consultor', 'categoria_tipo' => 'entrada'],
        'pagamento_cliente|saida' => ['nome' => 'Pagamento ao cliente', 'categoria_tipo' => 'despesa'],
        'sangria_caixa_operacao|saida' => ['nome' => 'Sangria para o Caixa da Operacao (saida usuario)', 'categoria_tipo' => 'despesa'],
        'sangria_caixa_operacao|entrada' => ['nome' => 'Sangria para o Caixa da Operacao (entrada)', 'categoria_tipo' => 'entrada'],
        'transferencia_caixa_operacao|saida' => ['nome' => 'Transferencia do Caixa da Operacao (saida)', 'categoria_tipo' => 'despesa'],
        'transferencia_caixa_operacao|entrada' => ['nome' => 'Transferencia do Caixa da Operacao (entrada usuario)', 'categoria_tipo' => 'entrada'],
    ];

    public function resolverCategoriaId(?int $empresaId, ?string $referenciaTipo, ?string $tipoLedger): ?int
    {
        if ($empresaId === null || $referenciaTipo === null || $tipoLedger === null) {
            return null;
        }
        // Alguns registros antigos podem usar FQCN em vez do slug 'venda'
        if ($referenciaTipo === \App\Modules\Core\Models\Venda::class) {
            $referenciaTipo = 'venda';
        }
        $tipoLedger = strtolower($tipoLedger);
        if (! in_array($tipoLedger, ['entrada', 'saida'], true)) {
            return null;
        }
        $chave = $referenciaTipo.'|'.$tipoLedger;
        if (! isset(self::MAPEAMENTO[$chave])) {
            return null;
        }
        $cfg = self::MAPEAMENTO[$chave];

        return $this->obterOuCriarCategoriaId($empresaId, $cfg['nome'], $cfg['categoria_tipo']);
    }

    public function obterOuCriarCategoriaId(int $empresaId, string $nome, string $categoriaTipo): int
    {
        if (! in_array($categoriaTipo, ['entrada', 'despesa'], true)) {
            $categoriaTipo = 'entrada';
        }
        $categoria = CategoriaMovimentacao::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('nome', $nome)
            ->where('tipo', $categoriaTipo)
            ->whereNull('deleted_at')
            ->first();
        if ($categoria) {
            return (int) $categoria->id;
        }
        $maxOrdem = (int) CategoriaMovimentacao::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $categoriaTipo)
            ->max('ordem');
        $categoria = CategoriaMovimentacao::withoutGlobalScopes()->create([
            'nome' => $nome,
            'tipo' => $categoriaTipo,
            'ativo' => true,
            'ordem' => $maxOrdem + 1,
            'empresa_id' => $empresaId,
        ]);

        return (int) $categoria->id;
    }

    public static function mapeamentoCompleto(): array
    {
        return self::MAPEAMENTO;
    }
}
