<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'nome',
        'razao_social',
        'cnpj',
        'email_contato',
        'telefone',
        'status',
        'plano',
        'data_ativacao',
        'data_expiracao',
        'configuracoes',
    ];

    protected $casts = [
        'data_ativacao' => 'date',
        'data_expiracao' => 'date',
        'configuracoes' => 'array',
    ];

    /**
     * Relacionamento: Uma empresa tem muitas operações
     */
    public function operacoes()
    {
        return $this->hasMany(Operacao::class, 'empresa_id');
    }

    /**
     * Relacionamento: Uma empresa tem muitos usuários
     */
    public function usuarios()
    {
        return $this->hasMany(\App\Models\User::class, 'empresa_id');
    }

    /**
     * Relacionamento: Uma empresa tem muitos clientes (apenas criados pela empresa)
     */
    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'empresa_id');
    }

    /**
     * Obter todos os clientes (criados + vinculados)
     * Use este método quando precisar contar ou listar todos os clientes da empresa
     */
    public function todosClientes()
    {
        return Cliente::where('empresa_id', $this->id)
            ->orWhereHas('empresasVinculadas', function ($q) {
                $q->where('empresa_id', $this->id);
            });
    }

    /**
     * Contar total de clientes (criados + vinculados)
     */
    public function getTotalClientesAttribute(): int
    {
        return $this->todosClientes()->count();
    }

    /**
     * Relacionamento: Uma empresa tem muitos empréstimos
     */
    public function emprestimos()
    {
        return $this->hasMany(\App\Modules\Loans\Models\Emprestimo::class, 'empresa_id');
    }

    /**
     * Verificar se a empresa está ativa
     */
    public function isAtiva(): bool
    {
        return $this->status === 'ativa';
    }

    /**
     * Verificar se a empresa está suspensa
     */
    public function isSuspensa(): bool
    {
        return $this->status === 'suspensa';
    }

    /**
     * Verificar se a empresa está cancelada
     */
    public function isCancelada(): bool
    {
        return $this->status === 'cancelada';
    }

    /**
     * Obter configuração específica
     */
    public function getConfiguracao(string $key, $default = null)
    {
        if (empty($this->configuracoes) || !is_array($this->configuracoes)) {
            return $default;
        }
        return data_get($this->configuracoes, $key, $default);
    }

    /**
     * Definir configuração específica
     */
    public function setConfiguracao(string $key, $value): void
    {
        $configuracoes = $this->configuracoes ?? [];
        data_set($configuracoes, $key, $value);
        $this->configuracoes = $configuracoes;
    }

    /**
     * Verificar se requer aprovação (baseado nas configurações)
     */
    public function requerAprovacao(): bool
    {
        return $this->getConfiguracao('workflow.requer_aprovacao', true);
    }

    /**
     * Verificar se requer liberação (baseado nas configurações)
     */
    public function requerLiberacao(): bool
    {
        return $this->getConfiguracao('workflow.requer_liberacao', true);
    }

    /**
     * Obter valor máximo para aprovação automática
     */
    public function getValorAprovacaoAutomatica(): float
    {
        return (float) $this->getConfiguracao('workflow.aprovacao_automatica_valor_max', 0);
    }

    /**
     * Verificar se permite múltiplas operações
     */
    public function permiteMultiplasOperacoes(): bool
    {
        return $this->getConfiguracao('operacoes.permite_multiplas_operacoes', true);
    }
}
