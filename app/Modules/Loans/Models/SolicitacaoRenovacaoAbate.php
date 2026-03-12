<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoRenovacaoAbate extends Model
{
    protected $table = 'solicitacao_renovacao_abate';

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
}
