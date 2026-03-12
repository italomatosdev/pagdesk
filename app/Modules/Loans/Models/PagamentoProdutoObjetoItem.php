<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Model;

class PagamentoProdutoObjetoItem extends Model
{
    protected $table = 'pagamento_produto_objeto_itens';

    protected $fillable = [
        'pagamento_id',
        'nome',
        'descricao',
        'valor_estimado',
        'quantidade',
        'imagens',
        'ordem',
    ];

    protected $casts = [
        'valor_estimado' => 'decimal:2',
        'imagens' => 'array',
    ];

    public function pagamento()
    {
        return $this->belongsTo(Pagamento::class, 'pagamento_id');
    }

    /**
     * URLs das imagens para exibição.
     */
    public function getImagensUrlsAttribute(): array
    {
        $paths = $this->imagens ?? [];
        return array_map(fn ($path) => asset('storage/' . $path), $paths);
    }
}
