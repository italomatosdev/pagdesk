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
        'custo_unitario_vigente' => 'decimal:2',
        'custo_vigente_atualizado_em' => 'datetime',
        'estoque' => 'decimal:3',
        'ativo' => 'boolean',
    ];

    /**
     * Códigos de unidade permitidos no cadastro (select): valor gravado em `unidade`.
     *
     * @return array<string, string> codigo => rótulo
     */
    public static function unidadesParaSelect(): array
    {
        return [
            'un' => 'Un (peça)',
            'pc' => 'Peça (PC)',
            'cx' => 'Caixa',
            'dz' => 'Dúzia',
            'kg' => 'Quilograma (kg)',
            'g' => 'Grama (g)',
            'm' => 'Metro (m)',
            'm2' => 'Metro quadrado (m²)',
            'l' => 'Litro (l)',
            'ml' => 'Mililitro (ml)',
        ];
    }

    /**
     * Códigos em que o estoque / quantidade na venda são inteiros.
     *
     * @return list<string>
     */
    public static function unidadesCodigosEstoqueInteiro(): array
    {
        return ['un', 'pc', 'cx', 'dz'];
    }

    /**
     * Unidades de contagem: estoque e quantidade na venda devem ser inteiros.
     * Valores legados (texto livre antigo) ainda são reconhecidos pelo padrão anterior.
     */
    public static function estoqueExigeInteiro(?string $unidade): bool
    {
        $u = mb_strtolower(trim((string) ($unidade ?? '')), 'UTF-8');
        if ($u === '') {
            return true;
        }
        if (in_array($u, self::unidadesCodigosEstoqueInteiro(), true)) {
            return true;
        }
        if (array_key_exists($u, self::unidadesParaSelect())) {
            return false;
        }
        $compacto = preg_replace('/\s+/u', '', $u);

        return (bool) preg_match('/^(un(id(ade)?)?|und|pc|p[cç]a|pe[cç]a)$/u', $compacto);
    }

    /**
     * Rótulo amigável para exibição; códigos fora do catálogo mostram o valor bruto (legado).
     */
    public function rotuloUnidade(): string
    {
        $u = $this->unidade;
        if ($u === null || $u === '') {
            return self::unidadesParaSelect()['un'];
        }

        return self::unidadesParaSelect()[$u] ?? (string) $u;
    }

    public function unidadeContagemInteira(): bool
    {
        return self::estoqueExigeInteiro($this->unidade);
    }

    /**
     * Exibe estoque sem zeros decimais à toa; unidade de contagem só inteiro.
     */
    public function formatarQuantidadeEstoque(): string
    {
        $v = (float) $this->estoque;
        if ($this->unidadeContagemInteira()) {
            return number_format((int) round($v), 0, ',', '.');
        }
        $s = number_format($v, 3, ',', '.');
        $s = rtrim(rtrim($s, '0'), ',');

        return $s === '' ? '0' : $s;
    }

    /**
     * Formata uma quantidade arbitrária (ex.: na venda) com a mesma regra do produto.
     */
    public function formatarQuantidade(float $quantidade): string
    {
        if ($this->unidadeContagemInteira()) {
            return number_format((int) round($quantidade), 0, ',', '.');
        }
        $s = number_format($quantidade, 3, ',', '.');
        $s = rtrim(rtrim($s, '0'), ',');

        return $s === '' ? '0' : $s;
    }

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

    public function custoHistoricos()
    {
        return $this->hasMany(ProdutoCustoHistorico::class, 'produto_id')->orderByDesc('valido_de');
    }

    /**
     * True quando existe custo vigente informado (inclui 0,00 explícito).
     */
    public function temCustoVigenteDefinido(): bool
    {
        return $this->custo_unitario_vigente !== null;
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
