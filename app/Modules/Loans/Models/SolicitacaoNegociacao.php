<?php

namespace App\Modules\Loans\Models;

use App\Models\User;
use App\Modules\Core\Models\Operacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitacaoNegociacao extends Model
{
    protected $table = 'solicitacao_negociacao';

    protected $fillable = [
        'emprestimo_id',
        'consultor_id',
        'operacao_id',
        'saldo_devedor',
        'dados_novo_emprestimo',
        'motivo',
        'status',
        'aprovado_por',
        'aprovado_em',
        'observacao_aprovador',
        'novo_emprestimo_id',
    ];

    protected $casts = [
        'dados_novo_emprestimo' => 'array',
        'saldo_devedor' => 'decimal:2',
        'aprovado_em' => 'datetime',
    ];

    public function emprestimo(): BelongsTo
    {
        return $this->belongsTo(Emprestimo::class);
    }

    public function consultor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultor_id');
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function novoEmprestimo(): BelongsTo
    {
        return $this->belongsTo(Emprestimo::class, 'novo_emprestimo_id');
    }

    public function isPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function isAprovado(): bool
    {
        return $this->status === 'aprovado';
    }

    public function isRejeitado(): bool
    {
        return $this->status === 'rejeitado';
    }

    public function getDadosFormatadosAttribute(): array
    {
        $dados = $this->dados_novo_emprestimo;
        
        $tipoLabels = [
            'dinheiro' => 'Dinheiro',
            'pix' => 'PIX',
            'cheque' => 'Cheque',
            'empenho' => 'Produto/Objeto',
            'cartao' => 'Cartão',
        ];

        $freqLabels = [
            'diaria' => 'Diária',
            'semanal' => 'Semanal',
            'quinzenal' => 'Quinzenal',
            'mensal' => 'Mensal',
        ];

        return [
            'tipo' => $tipoLabels[$dados['tipo'] ?? ''] ?? ucfirst($dados['tipo'] ?? '-'),
            'frequencia' => $freqLabels[$dados['frequencia'] ?? ''] ?? ucfirst($dados['frequencia'] ?? '-'),
            'taxa_juros' => ($dados['taxa_juros'] ?? 0) . '%',
            'numero_parcelas' => $dados['numero_parcelas'] ?? 1,
            'data_inicio' => isset($dados['data_inicio']) ? \Carbon\Carbon::parse($dados['data_inicio'])->format('d/m/Y') : '-',
        ];
    }
}
