<?php

namespace App\Modules\Loans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parcela extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parcelas';

    protected $fillable = [
        'emprestimo_id',
        'numero',
        'valor',
        'valor_juros',
        'valor_amortizacao',
        'saldo_devedor',
        'valor_pago',
        'data_vencimento',
        'data_pagamento',
        'status',
        'dias_atraso',
        'observacoes',
        'empresa_id', // Empresa da parcela
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'valor_juros' => 'decimal:2',
        'valor_amortizacao' => 'decimal:2',
        'saldo_devedor' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Parcela pertence a um empréstimo
     */
    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Uma parcela pode ter muitos pagamentos
     */
    public function pagamentos()
    {
        return $this->hasMany(Pagamento::class, 'parcela_id');
    }

    /**
     * Relacionamento: Solicitações de pagamento com valor inferior (juros contrato reduzido)
     */
    public function solicitacoesJurosContratoReduzido()
    {
        return $this->hasMany(SolicitacaoPagamentoJurosContratoReduzido::class, 'parcela_id');
    }

    /**
     * Verificar se a parcela tem solicitação de valor inferior aguardando aprovação
     */
    public function hasSolicitacaoJurosContratoReduzidoPendente(): bool
    {
        return $this->solicitacoesJurosContratoReduzido()->where('status', 'aguardando')->exists();
    }

    /**
     * Verificar se está paga
     */
    public function isPaga(): bool
    {
        return $this->status === 'paga';
    }

    /**
     * Verificar se foi quitada por execução de garantia
     */
    public function isQuitadaGarantia(): bool
    {
        return $this->status === 'quitada_garantia';
    }

    /**
     * Verificar se está quitada (paga ou quitada por garantia)
     */
    public function isQuitada(): bool
    {
        return $this->isPaga() || $this->isQuitadaGarantia();
    }

    /**
     * Verificar se a parcela tem pagamento em produto/objeto aguardando aceite do gestor/adm
     */
    public function hasPagamentoProdutoObjetoPendente(): bool
    {
        return $this->pagamentos()->where('metodo', Pagamento::METODO_PRODUTO_OBJETO)
            ->whereNull('aceite_gestor_id')
            ->whereNull('rejeitado_por_id')
            ->exists();
    }

    /**
     * Verificar se a parcela tem pagamento em produto/objeto que foi recusado pelo gestor/adm
     */
    public function hasPagamentoProdutoObjetoRejeitado(): bool
    {
        return $this->pagamentos()->where('metodo', Pagamento::METODO_PRODUTO_OBJETO)
            ->whereNotNull('rejeitado_por_id')
            ->exists();
    }

    /**
     * Verificar se está atrasada
     */
    public function isAtrasada(): bool
    {
        if ($this->status === 'paga_parcial') {
            return false;
        }

        return $this->status === 'atrasada' ||
               ($this->status === 'pendente' && $this->data_vencimento < Carbon::today());
    }

    public function hasSolicitacaoDiariaParcialPendente(): bool
    {
        return SolicitacaoPagamentoDiariaParcial::where('parcela_id', $this->id)
            ->where('status', 'aguardando')
            ->exists();
    }

    /**
     * Verificar se vence hoje
     */
    public function venceHoje(): bool
    {
        return $this->data_vencimento->isToday();
    }

    /**
     * Calcular dias de atraso (opcional: data de referência, ex. data do pagamento).
     *
     * @param  \Carbon\Carbon|null  $dataReferencia  Se null, usa hoje.
     */
    public function calcularDiasAtraso(?\Carbon\Carbon $dataReferencia = null): int
    {
        if ($this->isQuitada()) {
            return 0;
        }

        $ref = $dataReferencia ? Carbon::parse($dataReferencia)->startOfDay() : Carbon::today();
        if ($this->data_vencimento < $ref) {
            return (int) $this->data_vencimento->diffInDays($ref);
        }

        return 0;
    }

    /**
     * Verificar se está totalmente paga
     * Considera também parcelas quitadas por garantia (mesmo com valor_pago = 0)
     */
    public function isTotalmentePaga(): bool
    {
        // Se foi quitada por garantia, considera como totalmente quitada
        if ($this->isQuitadaGarantia()) {
            return true;
        }

        return $this->valor_pago >= $this->valor;
    }

    /**
     * Valor nominal ainda em aberto na parcela (valor − valor_pago).
     * Usado em quitação em lote e pagamentos parciais.
     */
    public function faltaPagar(): float
    {
        if ($this->isQuitada()) {
            return 0.0;
        }

        return max(0.0, round((float) $this->valor - (float) ($this->valor_pago ?? 0), 2));
    }

    /**
     * Obter nome do status formatado
     */
    public function getStatusNomeAttribute(): string
    {
        return match ($this->status) {
            'pendente' => 'Pendente',
            'paga' => 'Paga',
            'paga_parcial' => 'Paga (parcial)',
            'atrasada' => 'Atrasada',
            'cancelada' => 'Cancelada',
            'quitada_garantia' => 'Quitada (Garantia)',
            default => 'Desconhecido',
        };
    }

    /**
     * Obter cor do badge do status
     */
    public function getStatusCorAttribute(): string
    {
        return match ($this->status) {
            'pendente' => 'warning',
            'paga' => 'success',
            'paga_parcial' => 'info',
            'atrasada' => 'danger',
            'cancelada' => 'secondary',
            'quitada_garantia' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Verificar se a parcela pertence a um empréstimo Price
     */
    public function isPrice(): bool
    {
        return $this->emprestimo && $this->emprestimo->isPrice();
    }

    /**
     * Obter valor dos juros da parcela (para sistema Price)
     */
    public function getJurosAttribute(): float
    {
        return $this->valor_juros ?? 0;
    }

    /**
     * Obter valor da amortização da parcela (para sistema Price)
     */
    public function getAmortizacaoAttribute(): float
    {
        return $this->valor_amortizacao ?? 0;
    }
}
