<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendaItem extends Model
{
    use HasFactory;

    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'produto_id',
        'descricao',
        'quantidade',
        'preco_unitario_vista',
        'preco_unitario_crediario',
        'subtotal_vista',
        'subtotal_crediario',
        'custo_unitario_aplicado',
        'custo_total_aplicado',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'preco_unitario_vista' => 'decimal:2',
        'preco_unitario_crediario' => 'decimal:2',
        'subtotal_vista' => 'decimal:2',
        'subtotal_crediario' => 'decimal:2',
        'custo_unitario_aplicado' => 'decimal:2',
        'custo_total_aplicado' => 'decimal:2',
    ];

    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
