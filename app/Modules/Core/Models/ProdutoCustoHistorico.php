<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoCustoHistorico extends Model
{
    protected $table = 'produto_custo_historicos';

    protected $fillable = [
        'produto_id',
        'custo_unitario',
        'valido_de',
        'valido_ate',
        'user_id',
        'observacao',
    ];

    protected $casts = [
        'custo_unitario' => 'decimal:2',
        'valido_de' => 'datetime',
        'valido_ate' => 'datetime',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
