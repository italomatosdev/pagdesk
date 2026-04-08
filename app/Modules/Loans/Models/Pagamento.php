<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagamento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pagamentos';

    public const METODO_PRODUTO_OBJETO = 'produto_objeto';

    protected $fillable = [
        'parcela_id',
        'consultor_id',
        'valor',
        'metodo',
        'data_pagamento',
        'comprovante_path',
        'observacoes',
        'tipo_juros',
        'taxa_juros_aplicada',
        'valor_juros',
        'aceite_gestor_id',
        'aceite_gestor_em',
        'produto_nome',
        'produto_descricao',
        'produto_valor',
        'produto_imagens',
        'rejeitado_por_id',
        'rejeitado_em',
        'lote_id',
        'aguardando_aprovacao_diaria_parcial',
        'quitacao_grupo_id',
        'estornado_em',
        'estornado_por_user_id',
        'estorno_motivo',
        'estorno_cash_ledger_entry_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_pagamento' => 'date',
        'taxa_juros_aplicada' => 'decimal:2',
        'valor_juros' => 'decimal:2',
        'aceite_gestor_em' => 'datetime',
        'produto_valor' => 'decimal:2',
        'produto_imagens' => 'array',
        'rejeitado_em' => 'datetime',
        'aguardando_aprovacao_diaria_parcial' => 'boolean',
        'estornado_em' => 'datetime',
    ];

    /**
     * Pagamento em produto/objeto não gera caixa e requer aceite de gestor/adm.
     */
    public function isProdutoObjeto(): bool
    {
        return $this->metodo === self::METODO_PRODUTO_OBJETO;
    }

    public function isEstornado(): bool
    {
        return $this->estornado_em !== null;
    }

    public function estornadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'estornado_por_user_id');
    }

    public function estornoCashLedgerEntry()
    {
        return $this->belongsTo(\App\Modules\Cash\Models\CashLedgerEntry::class, 'estorno_cash_ledger_entry_id');
    }

    /**
     * Se for produto/objeto, está pendente até gestor/adm aceitar (não rejeitado e não aceito).
     */
    public function isPendenteAceite(): bool
    {
        return $this->isProdutoObjeto() && empty($this->aceite_gestor_id) && empty($this->rejeitado_por_id);
    }

    /**
     * Se foi rejeitado pelo gestor/adm (pagamento continua pendente, parcela não creditada).
     */
    public function isRejeitado(): bool
    {
        return ! empty($this->rejeitado_por_id);
    }

    /**
     * Relacionamento: quem aceitou (gestor ou administrador).
     */
    public function aceiteGestor()
    {
        return $this->belongsTo(\App\Models\User::class, 'aceite_gestor_id');
    }

    /**
     * Relacionamento: quem rejeitou (gestor ou administrador).
     */
    public function rejeitadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'rejeitado_por_id');
    }

    /**
     * Itens de produto/objeto (1 pagamento = N itens).
     */
    public function produtoObjetoItens()
    {
        return $this->hasMany(PagamentoProdutoObjetoItem::class, 'pagamento_id')->orderBy('ordem');
    }

    /**
     * Se tem itens cadastrados (fluxo novo); senão usa dados antigos no próprio pagamento.
     */
    public function hasProdutoObjetoItens(): bool
    {
        return $this->produtoObjetoItens()->exists();
    }

    /**
     * URLs das imagens do produto/objeto (para exibição – fluxo antigo, único bloco).
     */
    public function getProdutoImagensUrlsAttribute(): array
    {
        $paths = $this->produto_imagens ?? [];

        return array_map(fn ($path) => asset('storage/'.$path), $paths);
    }

    /**
     * Relacionamento: Pagamento pertence a uma parcela
     */
    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'parcela_id');
    }

    /**
     * Relacionamento: Pagamento foi recebido por um consultor
     */
    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    /**
     * Relacionamento: Um pagamento pode gerar uma movimentação de caixa
     */
    public function cashLedgerEntry()
    {
        return $this->hasOne(\App\Modules\Cash\Models\CashLedgerEntry::class, 'pagamento_id');
    }

    public function comprovanteAnexos()
    {
        return $this->morphMany(\App\Models\ComprovanteAnexo::class, 'anexavel')->orderBy('id');
    }

    /**
     * Obter URL do comprovante
     */
    public function getComprovanteUrlAttribute(): ?string
    {
        return $this->comprovante_path
            ? asset('storage/'.$this->comprovante_path)
            : null;
    }

    /**
     * Verificar se o comprovante é uma imagem
     */
    public function isComprovanteImagem(): bool
    {
        if (! $this->comprovante_path) {
            return false;
        }

        $extension = strtolower(pathinfo($this->comprovante_path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }

    /**
     * Verificar se tem comprovante
     */
    public function hasComprovante(): bool
    {
        return ! empty($this->comprovante_path);
    }

    /**
     * Verificar se tem juros aplicados
     */
    public function hasJuros(): bool
    {
        return ! empty($this->tipo_juros) && $this->tipo_juros !== 'nenhum' && $this->valor_juros > 0;
    }

    /**
     * Obter descrição do tipo de juros
     */
    public function getDescricaoTipoJurosAttribute(): string
    {
        return match ($this->tipo_juros) {
            'automatico' => 'Juros Automático',
            'manual' => 'Juros Manual',
            'fixo' => 'Valor Fixo Manual',
            default => 'Sem juros'
        };
    }
}
