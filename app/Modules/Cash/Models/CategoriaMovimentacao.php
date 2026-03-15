<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaMovimentacao extends Model
{
    use SoftDeletes;

    protected $table = 'categoria_movimentacao';

    protected $fillable = [
        'nome',
        'tipo',
        'ativo',
        'ordem',
        'empresa_id',
        'operacao_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    public function isEntrada(): bool
    {
        return $this->tipo === 'entrada';
    }

    public function isDespesa(): bool
    {
        return $this->tipo === 'despesa';
    }

    public function operacao()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Operacao::class, 'operacao_id');
    }

    public function movimentacoes()
    {
        return $this->hasMany(CashLedgerEntry::class, 'categoria_id');
    }
}
