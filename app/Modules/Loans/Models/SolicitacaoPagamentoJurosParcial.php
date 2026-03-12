<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoPagamentoJurosParcial extends Model
{
    protected $table = 'solicitacao_pagamento_juros_parcial';

    protected $fillable = [
        'parcela_id',
        'consultor_id',
        'valor',
        'metodo',
        'data_pagamento',
        'comprovante_path',
        'observacoes',
        'tipo_juros',
        'taxa_juros_aplicada',
        'valor_juros_solicitado',
        'valor_juros_devido',
        'status',
        'aprovado_por_id',
        'aprovado_em',
        'rejeitado_por_id',
        'rejeitado_em',
        'empresa_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_pagamento' => 'date',
        'taxa_juros_aplicada' => 'decimal:2',
        'valor_juros_solicitado' => 'decimal:2',
        'valor_juros_devido' => 'decimal:2',
        'aprovado_em' => 'datetime',
        'rejeitado_em' => 'datetime',
    ];

    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'parcela_id');
    }

    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    public function aprovadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'aprovado_por_id');
    }

    public function rejeitadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'rejeitado_por_id');
    }

    public function isAguardando(): bool
    {
        return $this->status === 'aguardando';
    }

    public function isAprovado(): bool
    {
        return $this->status === 'aprovado';
    }

    public function isRejeitado(): bool
    {
        return $this->status === 'rejeitado';
    }

    /**
     * Converte os dados da solicitação para o array esperado por PagamentoService::registrar()
     */
    /**
     * Converte os dados da solicitação para o array esperado por PagamentoService::registrar().
     * Ao aprovar, registramos com juros fixo = valor_juros_solicitado para não recalcular.
     */
    public function toDadosPagamento(): array
    {
        $dataPagamento = $this->data_pagamento;
        if ($dataPagamento instanceof \Carbon\Carbon) {
            $dataPagamento = $dataPagamento->format('Y-m-d');
        }
        return [
            'parcela_id' => $this->parcela_id,
            'consultor_id' => $this->consultor_id,
            'valor' => $this->valor,
            'metodo' => $this->metodo,
            'data_pagamento' => $dataPagamento,
            'comprovante_path' => $this->comprovante_path,
            'observacoes' => $this->observacoes,
            'tipo_juros' => 'fixo',
            'taxa_juros_manual' => null,
            'valor_juros_fixo' => (float) $this->valor_juros_solicitado,
        ];
    }
}
