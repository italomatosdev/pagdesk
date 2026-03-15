<?php

namespace App\Modules\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $fillable = [
        'user_id',
        'operacao_id',
        'tipo',
        'titulo',
        'mensagem',
        'dados',
        'url',
        'lida',
        'lida_em',
    ];

    protected $casts = [
        'dados' => 'array',
        'lida' => 'boolean',
        'lida_em' => 'datetime',
    ];

    protected $appends = [
        'icone',
        'cor',
        'tempo_relativo',
    ];

    /**
     * Relacionamento com User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relacionamento com Operação (contexto da notificação)
     */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Operacao::class, 'operacao_id');
    }

    /**
     * Marcar como lida
     */
    public function marcarComoLida(): void
    {
        if (!$this->lida) {
            $this->update([
                'lida' => true,
                'lida_em' => now(),
            ]);
        }
    }

    /**
     * Obter ícone baseado no tipo
     */
    public function getIconeAttribute(): string
    {
        return match($this->tipo) {
            'emprestimo_pendente' => 'bx-money',
            'emprestimo_aprovado' => 'bx-check-circle',
            'liberacao_disponivel' => 'bx-money-withdraw',
            'parcela_vencendo' => 'bx-calendar',
            'parcela_atrasada' => 'bx-error-circle',
            'prestacao_pendente' => 'bx-file',
            'prestacao_aprovada' => 'bx-check',
            'prestacao_rejeitada' => 'bx-x-circle',
            'pagamento_registrado' => 'bx-check-double',
            'emprestimo_cancelado' => 'bx-x-circle',
            'garantia_executada' => 'bx-shield-x',
            'garantia_liberada' => 'bx-shield-check',
            default => 'bx-bell',
        };
    }

    /**
     * Obter cor baseada no tipo
     */
    public function getCorAttribute(): string
    {
        return match($this->tipo) {
            'emprestimo_pendente' => 'warning',
            'emprestimo_aprovado' => 'success',
            'liberacao_disponivel' => 'primary',
            'parcela_vencendo' => 'info',
            'parcela_atrasada' => 'danger',
            'prestacao_pendente' => 'warning',
            'prestacao_aprovada' => 'success',
            'prestacao_rejeitada' => 'danger',
            'pagamento_registrado' => 'success',
            'emprestimo_cancelado' => 'danger',
            'garantia_executada' => 'danger',
            'garantia_liberada' => 'success',
            default => 'primary',
        };
    }

    /**
     * Obter tempo relativo em português
     */
    public function getTempoRelativoAttribute(): string
    {
        $carbon = Carbon::parse($this->created_at);
        $diff = $carbon->diffInSeconds(now());
        
        if ($diff < 60) {
            return 'há alguns segundos';
        } elseif ($diff < 3600) {
            $minutos = floor($diff / 60);
            return $minutos == 1 ? 'há 1 minuto' : "há {$minutos} minutos";
        } elseif ($diff < 86400) {
            $horas = floor($diff / 3600);
            return $horas == 1 ? 'há 1 hora' : "há {$horas} horas";
        } elseif ($diff < 2592000) {
            $dias = floor($diff / 86400);
            return $dias == 1 ? 'há 1 dia' : "há {$dias} dias";
        } elseif ($diff < 31536000) {
            $meses = floor($diff / 2592000);
            return $meses == 1 ? 'há 1 mês' : "há {$meses} meses";
        } else {
            $anos = floor($diff / 31536000);
            return $anos == 1 ? 'há 1 ano' : "há {$anos} anos";
        }
    }
}
