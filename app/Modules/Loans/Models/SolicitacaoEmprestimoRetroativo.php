<?php

namespace App\Modules\Loans\Models;

use App\Models\Scopes\EmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitacaoEmprestimoRetroativo extends Model
{
    protected $table = 'solicitacoes_emprestimo_retroativo';

    protected $fillable = [
        'emprestimo_id',
        'solicitante_id',
        'status',
        'aprovado_por',
        'aprovado_em',
        'motivo_rejeicao',
        'empresa_id',
    ];

    protected $casts = [
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
