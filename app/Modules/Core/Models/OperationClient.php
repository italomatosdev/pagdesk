<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationClient extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'operation_clients';

    protected $fillable = [
        'operacao_id',
        'cliente_id',
        'limite_credito',
        'status',
        'notas_internas',
        'consultor_id',
    ];

    protected $casts = [
        'limite_credito' => 'decimal:2',
    ];

    /**
     * Relacionamento: Vínculo pertence a uma operação
     */
    public function operacao()
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }

    /**
     * Relacionamento: Vínculo pertence a um cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Relacionamento: Consultor responsável
     */
    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    /**
     * Verificar se está ativo
     */
    public function isAtivo(): bool
    {
        return $this->status === 'ativo';
    }
}

