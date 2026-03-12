<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = [
        'empresa_id',
        'operacao_id',
        'nome',
        'codigo',
        'preco_venda',
        'unidade',
        'estoque',
        'ativo',
    ];

    protected $casts = [
        'preco_venda' => 'decimal:2',
        'estoque' => 'decimal:3',
        'ativo' => 'boolean',
    ];

    /**
     * Verifica se há estoque suficiente para a quantidade informada.
     */
    public function temEstoque(float $quantidade): bool
    {
        return (float) $this->estoque >= $quantidade;
    }

    /**
     * Verifica se o produto pode ser vendido (tem estoque > 0).
     */
    public function podeSerVendido(): bool
    {
        return $this->ativo && (float) $this->estoque > 0;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function operacao()
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }

    public function vendaItens()
    {
        return $this->hasMany(VendaItem::class, 'produto_id');
    }

    /**
     * Fotos e anexos (documentos) do produto
     */
    public function anexos()
    {
        return $this->hasMany(ProdutoAnexo::class, 'produto_id')->orderBy('ordem')->orderBy('id');
    }

    /**
     * Apenas fotos (imagens)
     */
    public function fotos()
    {
        return $this->anexos()->where('tipo', 'imagem');
    }

    /**
     * Apenas anexos (documentos)
     */
    public function documentos()
    {
        return $this->anexos()->where('tipo', 'documento');
    }
}
