<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoPagamentoJurosContratoReduzido extends Model
{
    protected $table = 'solicitacao_pagamento_juros_contrato_reduzido';

    protected $fillable = [
        'parcela_id',
        'consultor_id',
        'valor',
        'valor_principal',
        'valor_parcela_total',
        'metodo',
        'data_pagamento',
        'comprovante_path',
        'observacoes',
        'status',
        'aprovado_por_id',
        'aprovado_em',
        'rejeitado_por_id',
        'rejeitado_em',
        'empresa_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'valor_principal' => 'decimal:2',
        'valor_parcela_total' => 'decimal:2',
        'data_pagamento' => 'date',
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
     * Converte os dados da solicitação para o array esperado por PagamentoService::registrar().
     * Registra com tipo_juros fixo e valor_juros_fixo = valor - principal (juros do contrato reduzido).
     */
    public function toDadosPagamento(): array
    {
        $dataPagamento = $this->data_pagamento;
        if ($dataPagamento instanceof \Carbon\Carbon) {
            $dataPagamento = $dataPagamento->format('Y-m-d');
        }
        $valorJuros = (float) $this->valor - (float) $this->valor_principal;
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
            'valor_juros_fixo' => round($valorJuros, 2),
            'encerra_parcela_valor_inferior' => true, // Pagamento aprovado com valor inferior = parcela quitada
        ];
    }
}
