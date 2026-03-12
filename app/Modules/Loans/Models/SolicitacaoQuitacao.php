<?php

namespace App\Modules\Loans\Models;

use App\Models\Scopes\EmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitacaoQuitacao extends Model
{
    protected $table = 'solicitacoes_quitacao';

    protected $fillable = [
        'emprestimo_id',
        'solicitante_id',
        'saldo_devedor',
        'valor_solicitado',
        'metodo',
        'data_pagamento',
        'comprovante_path',
        'observacoes',
        'motivo_desconto',
        'status',
        'aprovado_por',
        'aprovado_em',
        'motivo_rejeicao',
        'empresa_id',
    ];

    protected $casts = [
        'saldo_devedor' => 'decimal:2',
        'valor_solicitado' => 'decimal:2',
        'data_pagamento' => 'date',
        'aprovado_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaScope);
    }

    public function emprestimo(): BelongsTo
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'solicitante_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'aprovado_por');
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

    public function temDesconto(): bool
    {
        return $this->valor_solicitado < $this->saldo_devedor;
    }

    public function getValorDescontoAttribute(): float
    {
        return (float) ($this->saldo_devedor - $this->valor_solicitado);
    }
}
