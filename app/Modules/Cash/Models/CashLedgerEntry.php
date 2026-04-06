<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashLedgerEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cash_ledger_entries';

    protected $fillable = [
        'operacao_id',
        'consultor_id',
        'pagamento_id',
        'referencia_tipo',
        'referencia_id',
        'tipo',
        'categoria_id',
        'origem',
        'valor',
        'descricao',
        'observacoes',
        'data_movimentacao',
        'comprovante_path',
        'empresa_id', // Empresa da movimentação
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_movimentacao' => 'date',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Movimentação pertence a uma operação
     */
    public function operacao()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Operacao::class, 'operacao_id');
    }

    /**
     * Relacionamento: Movimentação pertence a um consultor
     */
    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    /**
     * Relacionamento: Movimentação pode ter uma categoria (entrada/despesa)
     */
    public function categoria()
    {
        return $this->belongsTo(CategoriaMovimentacao::class, 'categoria_id');
    }

    /**
     * Relacionamento: Movimentação pode ter origem em um pagamento
     */
    public function pagamento()
    {
        return $this->belongsTo(\App\Modules\Loans\Models\Pagamento::class, 'pagamento_id');
    }

    /**
     * Verificar se é entrada
     */
    public function isEntrada(): bool
    {
        return $this->tipo === 'entrada';
    }

    /**
     * Verificar se é saída
     */
    public function isSaida(): bool
    {
        return $this->tipo === 'saida';
    }

    /**
     * Verificar se é movimentação manual
     */
    public function isManual(): bool
    {
        return $this->origem === 'manual';
    }

    /**
     * Verificar se é movimentação automática
     */
    public function isAutomatica(): bool
    {
        return $this->origem === 'automatica';
    }

    public function comprovanteAnexos()
    {
        return $this->morphMany(\App\Models\ComprovanteAnexo::class, 'anexavel')->orderBy('id');
    }
}
