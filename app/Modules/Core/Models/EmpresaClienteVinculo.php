<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmpresaClienteVinculo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empresa_cliente_vinculos';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'vinculado_por',
    ];

    protected $casts = [
        'vinculado_em' => 'datetime',
    ];

    /**
     * Relacionamento: Vínculo pertence a uma empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Relacionamento: Vínculo pertence a um cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Relacionamento: Usuário que criou o vínculo
     */
    public function vinculadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'vinculado_por');
    }
}
