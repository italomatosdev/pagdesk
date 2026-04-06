<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settlement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'settlements';

    protected $fillable = [
        'operacao_id',
        'consultor_id',
        'criado_por',
        'data_inicio',
        'data_fim',
        'valor_total',
        'status',
        'conferido_por',
        'conferido_em',
        'validado_por',
        'validado_em',
        'observacoes',
        'motivo_rejeicao',
        'comprovante_path',
        'enviado_em',
        'recebido_por',
        'recebido_em',
        'empresa_id',
    ];

    protected $casts = [
        'valor_total' => 'decimal:2',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'conferido_em' => 'datetime',
        'validado_em' => 'datetime',
        'enviado_em' => 'datetime',
        'recebido_em' => 'datetime',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Settlement pertence a uma operação
     */
    public function operacao()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Operacao::class, 'operacao_id');
    }

    /**
     * Relacionamento: Settlement pertence a um consultor (usuário que terá o caixa fechado)
     */
    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    /**
     * Relacionamento: Quem criou o fechamento (pode ser o próprio consultor ou gestor/admin)
     */
    public function criador()
    {
        return $this->belongsTo(\App\Models\User::class, 'criado_por');
    }

    /**
     * Verificar se foi fechamento iniciado pelo gestor/admin (não pelo próprio usuário)
     */
    public function isFechamentoPorGestor(): bool
    {
        return $this->criado_por !== null && $this->criado_por !== $this->consultor_id;
    }

    /**
     * Verificar se foi solicitação do próprio usuário
     */
    public function isSolicitacaoPropria(): bool
    {
        return $this->criado_por === null || $this->criado_por === $this->consultor_id;
    }

    /**
     * Relacionamento: Foi conferido por um gestor
     */
    public function conferidor()
    {
        return $this->belongsTo(\App\Models\User::class, 'conferido_por');
    }

    /**
     * Relacionamento: Foi validado por um administrador
     */
    public function validador()
    {
        return $this->belongsTo(\App\Models\User::class, 'validado_por');
    }

    /**
     * Relacionamento: Foi recebido por um gestor
     */
    public function recebedor()
    {
        return $this->belongsTo(\App\Models\User::class, 'recebido_por');
    }

    /**
     * Verificar se está pendente
     */
    public function isPendente(): bool
    {
        return $this->status === 'pendente';
    }

    /**
     * Verificar se está aprovado
     */
    public function isAprovado(): bool
    {
        return $this->status === 'aprovado';
    }

    /**
     * Verificar se está enviado (consultor anexou comprovante)
     */
    public function isEnviado(): bool
    {
        return $this->status === 'enviado';
    }

    /**
     * Verificar se está concluído
     */
    public function isConcluido(): bool
    {
        return $this->status === 'concluido';
    }

    /**
     * Verificar se está rejeitado
     */
    public function isRejeitado(): bool
    {
        return $this->status === 'rejeitado';
    }

    /**
     * Verificar se está conferido (legado - compatibilidade)
     */
    public function isConferido(): bool
    {
        return $this->status === 'conferido' || $this->status === 'validado' || $this->status === 'aprovado';
    }

    /**
     * Verificar se está validado (legado - compatibilidade)
     */
    public function isValidado(): bool
    {
        return $this->status === 'validado' || $this->status === 'concluido';
    }

    public function comprovanteAnexos()
    {
        return $this->morphMany(\App\Models\ComprovanteAnexo::class, 'anexavel')->orderBy('id');
    }
}
