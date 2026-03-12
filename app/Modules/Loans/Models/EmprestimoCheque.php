<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class EmprestimoCheque extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'emprestimo_cheques';

    protected $fillable = [
        'emprestimo_id',
        'banco',
        'agencia',
        'conta',
        'numero_cheque',
        'data_vencimento',
        'valor_cheque',
        'dias_ate_vencimento',
        'taxa_juros',
        'valor_juros',
        'valor_liquido',
        'portador',
        'status',
        'data_deposito',
        'data_compensacao',
        'data_devolucao',
        'motivo_devolucao',
        'observacoes',
        'empresa_id',
    ];

    protected $casts = [
        'valor_cheque' => 'decimal:2',
        'taxa_juros' => 'decimal:2',
        'valor_juros' => 'decimal:2',
        'valor_liquido' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_deposito' => 'datetime',
        'data_compensacao' => 'datetime',
        'data_devolucao' => 'datetime',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Cheque pertence a um empréstimo
     */
    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id');
    }

    /**
     * Calcular dias até vencimento (se ainda não venceu)
     */
    public function calcularDiasAteVencimento(): int
    {
        if (!$this->data_vencimento) {
            return 0;
        }

        $hoje = Carbon::today();
        $vencimento = Carbon::parse($this->data_vencimento);

        if ($vencimento < $hoje) {
            return 0; // Já vencido - para atraso usamos outro método
        }

        return $hoje->diffInDays($vencimento);
    }

    /**
     * Calcular dias em atraso (se já venceu)
     */
    public function calcularDiasEmAtraso(): int
    {
        if (!$this->data_vencimento) {
            return 0;
        }

        $hoje = Carbon::today();
        $vencimento = Carbon::parse($this->data_vencimento);

        if ($vencimento >= $hoje) {
            return 0; // Ainda não venceu
        }

        return $vencimento->diffInDays($hoje);
    }

    /**
     * Calcular juros do cheque
     * Fórmula baseada na planilha: (Valor × Taxa × Dias) / (100 × 30)
     *
     * @param float $taxaJuros Taxa de juros ao mês (ex: 8.5)
     * @return float
     */
    public function calcularJuros(float $taxaJuros): float
    {
        if ($this->valor_cheque <= 0 || $taxaJuros <= 0) {
            return 0;
        }

        $dias = $this->calcularDiasAteVencimento();
        
        if ($dias <= 0) {
            return 0;
        }

        // Fórmula: (Valor × Taxa × Dias) / (100 × 30)
        // Considerando taxa ao mês e 30 dias como base
        $juros = ($this->valor_cheque * $taxaJuros * $dias) / (100 * 30);

        return round($juros, 2);
    }

    /**
     * Atualizar cálculos (dias, juros, valor líquido)
     *
     * @param float $taxaJuros Taxa de juros ao mês
     * @return void
     */
    public function atualizarCalculos(float $taxaJuros): void
    {
        $dias = $this->calcularDiasAteVencimento();
        $juros = $this->calcularJuros($taxaJuros);
        $valorLiquido = $this->valor_cheque - $juros;

        $this->update([
            'dias_ate_vencimento' => $dias,
            'taxa_juros' => $taxaJuros,
            'valor_juros' => $juros,
            'valor_liquido' => $valorLiquido,
        ]);
    }

    /**
     * Verificar se está aguardando
     */
    public function isAguardando(): bool
    {
        return $this->status === 'aguardando';
    }

    /**
     * Verificar se foi depositado
     */
    public function isDepositado(): bool
    {
        return $this->status === 'depositado';
    }

    /**
     * Verificar se foi compensado
     */
    public function isCompensado(): bool
    {
        return $this->status === 'compensado';
    }

    /**
     * Verificar se foi devolvido
     */
    public function isDevolvido(): bool
    {
        return $this->status === 'devolvido';
    }

    /**
     * Verificar se está cancelado
     */
    public function isCancelado(): bool
    {
        return $this->status === 'cancelado';
    }

    /**
     * Verificar se está vencido (data de vencimento passou)
     */
    public function isVencido(): bool
    {
        if (!$this->data_vencimento) {
            return false;
        }

        return Carbon::parse($this->data_vencimento)->isPast() 
            && $this->status === 'aguardando';
    }

    /**
     * Obter nome do status formatado
     */
    public function getStatusNomeAttribute(): string
    {
        return match($this->status) {
            'aguardando' => 'Aguardando',
            'depositado' => 'Depositado',
            'compensado' => 'Compensado',
            'devolvido' => 'Devolvido',
            'cancelado' => 'Cancelado',
            default => 'Desconhecido',
        };
    }

    /**
     * Obter cor do badge do status
     */
    public function getStatusCorAttribute(): string
    {
        return match($this->status) {
            'aguardando' => $this->isVencido() ? 'warning' : 'info',
            'depositado' => 'primary',
            'compensado' => 'success',
            'devolvido' => 'danger',
            'cancelado' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Obter valor formatado do cheque
     */
    public function getValorFormatadoAttribute(): string
    {
        return 'R$ ' . number_format($this->valor_cheque, 2, ',', '.');
    }

    /**
     * Obter valor de juros formatado
     */
    public function getJurosFormatadoAttribute(): string
    {
        return 'R$ ' . number_format($this->valor_juros, 2, ',', '.');
    }

    /**
     * Obter valor líquido formatado
     */
    public function getValorLiquidoFormatadoAttribute(): string
    {
        return 'R$ ' . number_format($this->valor_liquido, 2, ',', '.');
    }
}
