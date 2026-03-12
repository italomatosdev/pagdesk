<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteDadosEmpresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cliente_dados_empresa';

    protected $fillable = [
        'cliente_id',
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

    /**
     * Relacionamento: Dados pertencem a um cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Relacionamento: Dados pertencem a uma empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
