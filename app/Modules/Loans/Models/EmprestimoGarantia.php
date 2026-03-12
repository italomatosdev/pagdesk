<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmprestimoGarantia extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'emprestimo_garantias';

    protected $fillable = [
        'emprestimo_id',
        'categoria',
        'descricao',
        'valor_avaliado',
        'localizacao',
        'observacoes',
        'status',
        'data_liberacao',
        'data_execucao',
    ];

    protected $casts = [
        'valor_avaliado' => 'decimal:2',
        'data_liberacao' => 'datetime',
        'data_execucao' => 'datetime',
    ];

    /**
     * Categorias disponíveis
     */
    public const CATEGORIAS = [
        'imovel' => 'Imóvel',
        'veiculo' => 'Veículo',
        'outros' => 'Outros',
    ];

    /**
     * Ícones por categoria
     */
    public const ICONES = [
        'imovel' => 'bx bx-building-house',
        'veiculo' => 'bx bx-car',
        'outros' => 'bx bx-package',
    ];

    /**
     * Relacionamento: Garantia pertence a um empréstimo
     */
    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Garantia tem muitos anexos
     */
    public function anexos()
    {
        return $this->hasMany(EmprestimoGarantiaAnexo::class, 'garantia_id');
    }

    /**
     * Obter apenas imagens
     */
    public function imagens()
    {
        return $this->anexos()->where('tipo', 'imagem');
    }

    /**
     * Obter apenas documentos
     */
    public function documentos()
    {
        return $this->anexos()->where('tipo', 'documento');
    }

    /**
     * Obter nome da categoria
     */
    public function getCategoriaNomeAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }

    /**
     * Obter ícone da categoria
     */
    public function getCategoriaIconeAttribute(): string
    {
        return self::ICONES[$this->categoria] ?? 'bx bx-package';
    }

    /**
     * Verificar se é imóvel
     */
    public function isImovel(): bool
    {
        return $this->categoria === 'imovel';
    }

    /**
     * Verificar se é veículo
     */
    public function isVeiculo(): bool
    {
        return $this->categoria === 'veiculo';
    }

    /**
     * Verificar se é outros
     */
    public function isOutros(): bool
    {
        return $this->categoria === 'outros';
    }

    /**
     * Obter valor formatado
     */
    public function getValorFormatadoAttribute(): string
    {
        return $this->valor_avaliado 
            ? 'R$ ' . number_format($this->valor_avaliado, 2, ',', '.') 
            : 'Não informado';
    }

    /**
     * Verificar se está ativa
     */
    public function isAtiva(): bool
    {
        return $this->status === 'ativa';
    }

    /**
     * Verificar se está liberada
     */
    public function isLiberada(): bool
    {
        return $this->status === 'liberada';
    }

    /**
     * Verificar se está executada
     */
    public function isExecutada(): bool
    {
        return $this->status === 'executada';
    }

    /**
     * Verificar se está cancelada
     */
    public function isCancelada(): bool
    {
        return $this->status === 'cancelada';
    }

    /**
     * Obter nome do status formatado
     */
    public function getStatusNomeAttribute(): string
    {
        return match($this->status) {
            'ativa' => 'Ativa',
            'liberada' => 'Liberada',
            'executada' => 'Executada',
            'cancelada' => 'Cancelada',
            default => 'Desconhecido',
        };
    }

    /**
     * Obter cor do badge do status
     */
    public function getStatusCorAttribute(): string
    {
        return match($this->status) {
            'ativa' => 'primary',
            'liberada' => 'success',
            'executada' => 'danger',
            'cancelada' => 'secondary',
            default => 'secondary',
        };
    }
}
