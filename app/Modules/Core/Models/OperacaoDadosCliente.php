<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacaoDadosCliente extends Model
{
    protected $table = 'operacao_dados_clientes';

    protected $fillable = [
        'cliente_id',
        'operacao_id',
        'empresa_id',
        'nome',
        'telefone',
        'email',
        'data_nascimento',
        'responsavel_nome',
        'responsavel_cpf',
        'responsavel_rg',
        'responsavel_cnh',
        'responsavel_cargo',
        'endereco',
        'numero',
        'cidade',
        'estado',
        'cep',
        'observacoes',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
