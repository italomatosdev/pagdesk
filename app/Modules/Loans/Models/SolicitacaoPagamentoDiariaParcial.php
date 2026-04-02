<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoPagamentoDiariaParcial extends Model
{
    protected $table = 'solicitacao_pagamento_diaria_parcial';

    protected $fillable = [
        'parcela_id',
        'emprestimo_id',
        'consultor_id',
        'valor_recebido',
        'valor_esperado',
        'faltante',
        'metodo',
        'data_pagamento',
        'comprovante_path',
        'observacoes',
        'pagamento_id',
        'status',
        'aprovado_por_id',
        'aprovado_em',
        'rejeitado_por_id',
        'rejeitado_em',
        'empresa_id',
    ];

    protected $casts = [
        'valor_recebido' => 'decimal:2',
        'valor_esperado' => 'decimal:2',
        'faltante' => 'decimal:2',
        'data_pagamento' => 'date',
        'aprovado_em' => 'datetime',
        'rejeitado_em' => 'datetime',
    ];

    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'parcela_id');
    }

    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id');
    }

    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    public function pagamento()
    {
        return $this->belongsTo(Pagamento::class, 'pagamento_id');
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
