<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiberacaoEmprestimo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'emprestimo_liberacoes';

    protected $fillable = [
        'emprestimo_id',
        'consultor_id',
        'gestor_id',
        'valor_liberado',
        'status',
        'liberado_em',
        'pago_ao_cliente_em',
        'confirmado_pagamento_por_id',
        'observacoes_liberacao',
        'observacoes_pagamento',
        'comprovante_liberacao',
        'comprovante_pagamento_cliente',
        'empresa_id', // Empresa da liberação
    ];

    protected $casts = [
        'valor_liberado' => 'decimal:2',
        'liberado_em' => 'datetime',
        'pago_ao_cliente_em' => 'datetime',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Liberação pertence a um empréstimo
     */
    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Liberação pertence a um consultor
     */
    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    /**
     * Relacionamento: Liberação foi feita por um gestor
     */
    public function gestor()
    {
        return $this->belongsTo(\App\Models\User::class, 'gestor_id');
    }

    /**
     * Quem confirmou o pagamento ao cliente (consultor ou gestor/admin)
     */
    public function confirmadoPagamentoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'confirmado_pagamento_por_id');
    }

    /**
     * Verificar se está aguardando liberação
     */
    public function isAguardando(): bool
    {
        return $this->status === 'aguardando';
    }

    /**
     * Verificar se está liberado
     */
    public function isLiberado(): bool
    {
        return $this->status === 'liberado';
    }

    /**
     * Verificar se foi pago ao cliente
     */
    public function isPagoAoCliente(): bool
    {
        return $this->status === 'pago_ao_cliente';
    }

    /**
     * Verificar se está cancelado
     */
    public function isCancelado(): bool
    {
        return $this->status === 'cancelado';
    }

    /**
     * Obter URL do comprovante de liberação
     */
    public function getComprovanteLiberacaoUrlAttribute(): ?string
    {
        return $this->comprovante_liberacao 
            ? asset('storage/' . $this->comprovante_liberacao) 
            : null;
    }

    /**
     * Obter URL do comprovante de pagamento ao cliente
     */
    public function getComprovantePagamentoClienteUrlAttribute(): ?string
    {
        return $this->comprovante_pagamento_cliente 
            ? asset('storage/' . $this->comprovante_pagamento_cliente) 
            : null;
    }

    /**
     * Verificar se tem comprovante de liberação
     */
    public function hasComprovanteLiberacao(): bool
    {
        return !empty($this->comprovante_liberacao);
    }

    /**
     * Verificar se tem comprovante de pagamento ao cliente
     */
    public function hasComprovantePagamentoCliente(): bool
    {
        return !empty($this->comprovante_pagamento_cliente);
    }

    /**
     * Verificar se o comprovante de liberação é uma imagem
     */
    public function isComprovanteLiberacaoImagem(): bool
    {
        if (!$this->comprovante_liberacao) {
            return false;
        }

        $extension = strtolower(pathinfo($this->comprovante_liberacao, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }

    /**
     * Verificar se o comprovante de pagamento ao cliente é uma imagem
     */
    public function isComprovantePagamentoClienteImagem(): bool
    {
        if (!$this->comprovante_pagamento_cliente) {
            return false;
        }

        $extension = strtolower(pathinfo($this->comprovante_pagamento_cliente, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }
}
