<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venda extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vendas';

    protected $fillable = [
        'cliente_id',
        'operacao_id',
        'user_id',
        'empresa_id',
        'data_venda',
        'status',
        'valor_total_bruto',
        'valor_desconto',
        'valor_total_final',
        'observacoes',
    ];

    protected $casts = [
        'data_venda' => 'date',
        'valor_total_bruto' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_total_final' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function operacao()
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function itens()
    {
        return $this->hasMany(VendaItem::class, 'venda_id');
    }

    public function formasPagamento()
    {
        return $this->hasMany(FormaPagamentoVenda::class, 'venda_id');
    }

    /**
     * Retorna o empréstimo gerado pela forma de pagamento crediário (se houver).
     */
    public function emprestimoCrediario()
    {
        $forma = $this->formasPagamento()->where('forma', FormaPagamentoVenda::FORMA_CREDIARIO)->first();
        return $forma ? $forma->emprestimo : null;
    }
}
