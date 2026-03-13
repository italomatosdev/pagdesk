<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operacao extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'operacoes';

    protected $fillable = [
        'nome',
        'codigo',
        'descricao',
        'ativo',
        'valor_aprovacao_automatica',
        'requer_aprovacao',
        'requer_liberacao',
        'requer_autorizacao_pagamento_produto',
        'permite_emprestimo_retroativo',
        'taxa_juros_atraso',
        'tipo_calculo_juros',
        'empresa_id', // Empresa da operação
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'requer_aprovacao' => 'boolean',
        'requer_liberacao' => 'boolean',
        'requer_autorizacao_pagamento_produto' => 'boolean',
        'permite_emprestimo_retroativo' => 'boolean',
        'valor_aprovacao_automatica' => 'decimal:2',
        'taxa_juros_atraso' => 'decimal:2',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Uma operação tem muitos vínculos com clientes
     */
    public function operationClients()
    {
        return $this->hasMany(OperationClient::class, 'operacao_id');
    }

    /**
     * Relacionamento: Uma operação tem muitos empréstimos
     */
    public function emprestimos()
    {
        return $this->hasMany(\App\Modules\Loans\Models\Emprestimo::class, 'operacao_id');
    }

    /**
     * Relacionamento: Uma operação tem muitos usuários (consultores e gestores)
     * Many-to-many: um usuário pode estar em várias operações
     */
    public function usuarios()
    {
        return $this->belongsToMany(\App\Models\User::class, 'operacao_user', 'operacao_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Relacionamento: Operação pertence a uma empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Documentos obrigatórios na criação de cliente nesta operação
     */
    public function documentosObrigatorios()
    {
        return $this->hasMany(OperacaoDocumentoObrigatorio::class, 'operacao_id');
    }

    /**
     * Sincroniza os tipos de documento obrigatórios para esta operação.
     *
     * @param array $tipos Array de chaves (ex: ['documento_cliente', 'selfie_documento'])
     */
    public function syncDocumentosObrigatorios(array $tipos): void
    {
        $validos = array_keys(OperacaoDocumentoObrigatorio::tiposDisponiveis());
        $tipos = array_values(array_intersect($tipos, $validos));

        $this->documentosObrigatorios()->delete();
        foreach ($tipos as $tipo) {
            $this->documentosObrigatorios()->create(['tipo_documento' => $tipo]);
        }
    }

    /**
     * Verificar se requer aprovação
     */
    public function requerAprovacao(): bool
    {
        return $this->requer_aprovacao ?? true;
    }

    /**
     * Verificar se requer liberação
     */
    public function requerLiberacao(): bool
    {
        return $this->requer_liberacao ?? true;
    }

    /**
     * Obter valor máximo para aprovação automática
     */
    public function getValorAprovacaoAutomatica(): float
    {
        return (float) ($this->valor_aprovacao_automatica ?? 0);
    }
}

