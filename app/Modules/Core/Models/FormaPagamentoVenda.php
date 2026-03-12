<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormaPagamentoVenda extends Model
{
    use HasFactory;

    protected $table = 'forma_pagamento_venda';

    public const FORMA_VISTA = 'vista';
    public const FORMA_PIX = 'pix';
    public const FORMA_CARTAO = 'cartao';
    public const FORMA_CREDIARIO = 'crediario';

    protected $fillable = [
        'venda_id',
        'forma',
        'valor',
        'descricao',
        'comprovante_path',
        'numero_parcelas',
        'emprestimo_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function emprestimo()
    {
        return $this->belongsTo(\App\Modules\Loans\Models\Emprestimo::class, 'emprestimo_id');
    }

    public function isCrediario(): bool
    {
        return $this->forma === self::FORMA_CREDIARIO;
    }

    /**
     * Formas que permitem anexar comprovante (pagamento na hora)
     */
    public static function formasComComprovante(): array
    {
        return [self::FORMA_VISTA, self::FORMA_PIX, self::FORMA_CARTAO];
    }

    public function permiteComprovante(): bool
    {
        return in_array($this->forma, self::formasComComprovante(), true);
    }

    public static function formasDisponiveis(): array
    {
        return [
            self::FORMA_VISTA => 'Dinheiro',
            self::FORMA_PIX => 'PIX',
            self::FORMA_CARTAO => 'Cartão',
            self::FORMA_CREDIARIO => 'Crediário',
        ];
    }
}
