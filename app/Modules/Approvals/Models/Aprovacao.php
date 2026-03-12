<?php

namespace App\Modules\Approvals\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aprovacao extends Model
{
    use HasFactory;

    protected $table = 'aprovacoes';

    protected $fillable = [
        'emprestimo_id',
        'aprovado_por',
        'decisao',
        'motivo',
        'empresa_id', // Empresa da aprovação
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Aprovação pertence a um empréstimo
     */
    public function emprestimo()
    {
        return $this->belongsTo(\App\Modules\Loans\Models\Emprestimo::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Aprovação foi feita por um usuário
     */
    public function aprovador()
    {
        return $this->belongsTo(\App\Models\User::class, 'aprovado_por');
    }

    /**
     * Verificar se foi aprovado
     */
    public function isAprovado(): bool
    {
        return $this->decisao === 'aprovado';
    }

    /**
     * Verificar se foi rejeitado
     */
    public function isRejeitado(): bool
    {
        return $this->decisao === 'rejeitado';
    }
}

