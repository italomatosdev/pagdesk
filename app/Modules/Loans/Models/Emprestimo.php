<?php

namespace App\Modules\Loans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Emprestimo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'emprestimos';

    protected $fillable = [
        'operacao_id',
        'cliente_id',
        'consultor_id',
        'criado_por_user_id',
        'valor_total',
        'numero_parcelas',
        'frequencia',
        'data_inicio',
        'taxa_juros',
        'status',
        'tipo',
        'observacoes',
        'is_retroativo',
        'aprovado_por',
        'aprovado_em',
        'motivo_rejeicao',
        'emprestimo_origem_id',
        'empresa_id',
        'venda_id', // Venda que originou o crediário (quando tipo = crediario)
        'sandbox', // Dados fictícios para ambiente de testes (Super Admin)
        'juros_incorporados', // Juros do empréstimo anterior incorporados ao principal (em negociações)
    ];

    protected $casts = [
        'valor_total' => 'decimal:2',
        'taxa_juros' => 'decimal:2',
        'juros_incorporados' => 'decimal:2',
        'data_inicio' => 'date',
        'aprovado_em' => 'datetime',
        'sandbox' => 'boolean',
        'is_retroativo' => 'boolean',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Relacionamento: Empréstimo pertence a uma operação
     */
    public function operacao()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Operacao::class, 'operacao_id');
    }

    /**
     * Relacionamento: Empréstimo pertence a um cliente
     */
    public function cliente()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Cliente::class, 'cliente_id');
    }

    /**
     * Relacionamento: Empréstimo foi criado por um consultor
     */
    public function consultor()
    {
        return $this->belongsTo(\App\Models\User::class, 'consultor_id');
    }

    /**
     * Relacionamento: Usuário que criou o registro (gestor ou consultor)
     */
    public function criadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'criado_por_user_id');
    }

    /**
     * Relacionamento: Empréstimo foi aprovado por um usuário
     */
    public function aprovador()
    {
        return $this->belongsTo(\App\Models\User::class, 'aprovado_por');
    }

    /**
     * Relacionamento: Um empréstimo tem muitas parcelas
     */
    public function parcelas()
    {
        return $this->hasMany(Parcela::class, 'emprestimo_id');
    }

    /**
     * Última parcela do empréstimo (maior número / último vencimento).
     * Usado em frequência diária para regra "dentro do prazo da última parcela".
     */
    public function getUltimaParcela(): ?Parcela
    {
        return $this->parcelas()->orderByDesc('numero')->orderByDesc('data_vencimento')->first();
    }

    /**
     * Data do próximo vencimento: primeira parcela ainda não paga (pendente/atrasada),
     * ordenada por data_vencimento. Empréstimo de 1 parcela = essa parcela; várias = primeira pendente.
     */
    public function getProximoVencimento(): ?Carbon
    {
        $quitados = ['paga', 'paga_parcial', 'quitada_garantia'];
        if ($this->relationLoaded('parcelas')) {
            $proxima = $this->parcelas
                ->filter(fn (Parcela $p) => ! in_array($p->status, $quitados, true))
                ->sortBy('data_vencimento')
                ->first();

            return $proxima?->data_vencimento;
        }
        $p = $this->parcelas()
            ->whereNotIn('status', $quitados)
            ->orderBy('data_vencimento')
            ->first();

        return $p?->data_vencimento;
    }

    /**
     * Verifica se o empréstimo é de frequência diária.
     */
    public function isFrequenciaDiaria(): bool
    {
        return $this->frequencia === 'diaria';
    }

    /**
     * Relacionamento: Um empréstimo pode ter uma aprovação
     */
    public function aprovacao()
    {
        return $this->hasOne(\App\Modules\Approvals\Models\Aprovacao::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Um empréstimo tem uma liberação
     */
    public function liberacao()
    {
        return $this->hasOne(LiberacaoEmprestimo::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Empréstimo tem muitas garantias (tipo empenho)
     */
    public function garantias()
    {
        return $this->hasMany(EmprestimoGarantia::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Empréstimo tem muitos cheques (tipo troca_cheque)
     */
    public function cheques()
    {
        return $this->hasMany(EmprestimoCheque::class, 'emprestimo_id');
    }

    /**
     * Relacionamento: Solicitações de quitação (com desconto) do empréstimo
     */
    public function solicitacoesQuitacao()
    {
        return $this->hasMany(SolicitacaoQuitacao::class, 'emprestimo_id');
    }

    /**
     * Verificar se é tipo empenho
     */
    public function isEmpenho(): bool
    {
        return $this->tipo === 'empenho';
    }

    /**
     * Verificar se é tipo troca de cheque
     */
    public function isTrocaCheque(): bool
    {
        return $this->tipo === 'troca_cheque';
    }

    /**
     * Verificar se tem garantias cadastradas
     */
    public function temGarantias(): bool
    {
        return $this->garantias()->exists();
    }

    /**
     * Verificar se tem cheques cadastrados
     */
    public function temCheques(): bool
    {
        return $this->cheques()->exists();
    }

    /**
     * Obter valor total das garantias
     */
    public function getValorTotalGarantiasAttribute(): float
    {
        return $this->garantias()->sum('valor_avaliado') ?? 0;
    }

    /**
     * Obter valor total dos cheques
     */
    public function getValorTotalChequesAttribute(): float
    {
        return $this->cheques()->sum('valor_cheque') ?? 0;
    }

    /**
     * Obter valor total de juros dos cheques
     */
    public function getValorTotalJurosChequesAttribute(): float
    {
        return $this->cheques()->sum('valor_juros') ?? 0;
    }

    /**
     * Obter valor líquido dos cheques (total - juros)
     */
    public function getValorLiquidoChequesAttribute(): float
    {
        $totalCheques = $this->getValorTotalChequesAttribute();
        $totalJuros = $this->getValorTotalJurosChequesAttribute();

        return $totalCheques - $totalJuros;
    }

    /**
     * Relacionamento: Empréstimo de origem (quando este é uma renovação)
     */
    public function emprestimoOrigem()
    {
        return $this->belongsTo(self::class, 'emprestimo_origem_id');
    }

    /**
     * Relacionamento: Venda que originou o crediário (quando tipo = crediario)
     */
    public function venda()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Venda::class, 'venda_id');
    }

    /**
     * Verificar se é tipo crediário (venda)
     */
    public function isCrediario(): bool
    {
        return $this->tipo === 'crediario';
    }

    /**
     * Relacionamento: Renovações geradas a partir deste empréstimo
     */
    public function renovacoes()
    {
        return $this->hasMany(self::class, 'emprestimo_origem_id');
    }

    /**
     * Solicitação de aceite quando consultor cria empréstimo retroativo (uma por empréstimo, status aguardando/aprovado/rejeitado)
     */
    public function solicitacaoRetroativo()
    {
        return $this->hasOne(SolicitacaoEmprestimoRetroativo::class, 'emprestimo_id');
    }

    /**
     * Verificar se está aguardando aceite de gestor/admin (consultor criou retroativo)
     */
    public function isAguardandoAceiteRetroativo(): bool
    {
        return $this->status === 'aguardando_aceite_retroativo';
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
        return $this->status === 'aprovado' || $this->status === 'ativo';
    }

    /**
     * Verificar se está ativo
     */
    public function isAtivo(): bool
    {
        return $this->status === 'ativo';
    }

    /**
     * Verificar se está finalizado
     */
    public function isFinalizado(): bool
    {
        return $this->status === 'finalizado';
    }

    /**
     * Verificar se o dinheiro já foi liberado (gestor liberou e/ou foi pago ao cliente).
     * Após liberado, garantias não podem mais ser editadas nem excluídas (apenas empenho).
     */
    public function foiLiberado(): bool
    {
        if (! $this->relationLoaded('liberacao')) {
            $this->load('liberacao');
        }
        $lib = $this->liberacao;

        return $lib && ($lib->isLiberado() || $lib->isPagoAoCliente());
    }

    /**
     * Verificar se está cancelado
     */
    public function isCancelado(): bool
    {
        return $this->status === 'cancelado';
    }

    /**
     * Verificar se todas as parcelas estão pagas
     */
    public function todasParcelasPagas(): bool
    {
        if (! $this->relationLoaded('parcelas')) {
            $this->load('parcelas');
        }

        $totalParcelas = $this->parcelas->count();
        if ($totalParcelas === 0) {
            return false;
        }

        // Considerar parcelas pagas OU quitadas por garantia
        $parcelasQuitadas = $this->parcelas->filter(function ($parcela) {
            return $parcela->status === 'paga' || $parcela->status === 'quitada_garantia';
        })->count();

        return $parcelasQuitadas === $totalParcelas;
    }

    /**
     * Verificar se este empréstimo é uma renovação de outro
     */
    public function isRenovacao(): bool
    {
        return ! is_null($this->emprestimo_origem_id);
    }

    /**
     * Calcular valor total com juros aplicados
     * Para Price: parcela × número de parcelas (juros compostos embutidos)
     * Para outros: principal × (1 + taxa) (juros simples)
     */
    public function calcularValorTotalComJuros(): float
    {
        if ($this->isPrice()) {
            $parcela = $this->calcularParcelaPrice();

            return round($parcela * $this->numero_parcelas, 2);
        }

        $taxaJuros = $this->taxa_juros ?? 0;
        $valorComJuros = $this->valor_total * (1 + ($taxaJuros / 100));

        return round($valorComJuros, 2);
    }

    /**
     * Calcular valor dos juros
     * Para Price: (parcela × n) - principal (juros compostos)
     * Para outros: principal × taxa (juros simples)
     */
    public function calcularValorJuros(): float
    {
        if ($this->isPrice()) {
            $totalPago = $this->calcularValorTotalComJuros();

            return round($totalPago - (float) $this->valor_total, 2);
        }

        $taxaJuros = $this->taxa_juros ?? 0;
        $valorJuros = $this->valor_total * ($taxaJuros / 100);

        return round($valorJuros, 2);
    }

    /**
     * Verificar se é tipo Price (sistema de amortização)
     */
    public function isPrice(): bool
    {
        return $this->tipo === 'price';
    }

    /**
     * Verificar se é tipo dinheiro (sistema atual)
     */
    public function isDinheiro(): bool
    {
        return $this->tipo === 'dinheiro' || is_null($this->tipo);
    }

    /**
     * Calcular valor da parcela fixa (Sistema Price)
     * Fórmula: P = PV × [i(1+i)^n] / [(1+i)^n - 1]
     */
    public function calcularParcelaPrice(): float
    {
        if (! $this->isPrice() || $this->numero_parcelas <= 0) {
            return 0;
        }

        $taxaJuros = $this->taxa_juros ?? 0;
        if ($taxaJuros <= 0) {
            return 0;
        }

        $valorPresente = $this->valor_total;
        $taxaDecimal = $taxaJuros / 100;
        $numeroParcelas = $this->numero_parcelas;

        // Fórmula Price
        $numerador = $taxaDecimal * pow(1 + $taxaDecimal, $numeroParcelas);
        $denominador = pow(1 + $taxaDecimal, $numeroParcelas) - 1;

        if ($denominador == 0) {
            return 0;
        }

        $parcelaFixa = $valorPresente * ($numerador / $denominador);

        return round($parcelaFixa, 2);
    }

    /**
     * Calcular valor da parcela (já com juros aplicados)
     * Se for tipo Price, usa cálculo Price. Senão, usa cálculo simples.
     */
    public function calcularValorParcela(): float
    {
        if ($this->numero_parcelas <= 0) {
            return 0;
        }

        // Se for tipo Price, usa cálculo Price
        if ($this->isPrice()) {
            return $this->calcularParcelaPrice();
        }

        // Senão, usa cálculo atual (juros simples)
        $valorTotalComJuros = $this->calcularValorTotalComJuros();

        return round($valorTotalComJuros / $this->numero_parcelas, 2);
    }

    /**
     * Gerar tabela de amortização completa (Sistema Price)
     * Retorna array com detalhes de cada parcela
     */
    public function gerarTabelaAmortizacaoPrice(): array
    {
        if (! $this->isPrice()) {
            return [];
        }

        $parcelaFixa = $this->calcularParcelaPrice();
        $saldoDevedor = $this->valor_total;
        $taxaDecimal = ($this->taxa_juros ?? 0) / 100;
        $tabela = [];

        for ($i = 1; $i <= $this->numero_parcelas; $i++) {
            $juros = $saldoDevedor * $taxaDecimal;
            $amortizacao = $parcelaFixa - $juros;
            $saldoDevedor = $saldoDevedor - $amortizacao;

            // Ajuste na última parcela para garantir que saldo seja zero
            if ($i === $this->numero_parcelas) {
                $saldoDevedor = 0;
                $amortizacao = $parcelaFixa - $juros;
            }

            $tabela[] = [
                'parcela' => $i,
                'valor_parcela' => round($parcelaFixa, 2),
                'juros' => round($juros, 2),
                'amortizacao' => round($amortizacao, 2),
                'saldo_devedor' => round($saldoDevedor, 2),
            ];
        }

        return $tabela;
    }

    /**
     * Verificar se os juros já foram pagos (para empréstimos com 1 parcela).
     * Só retorna true quando existe valor de juros a pagar e o valor pago na parcela já cobre esse valor.
     * Se não há juros (taxa 0), retorna false para não exibir "juros já pagos" indevidamente.
     */
    public function jurosJaForamPagos(): bool
    {
        if ($this->numero_parcelas !== 1) {
            return false;
        }

        $parcela = $this->parcelas->first();
        if (! $parcela) {
            return false;
        }

        $valorJuros = $this->calcularValorJuros();
        if ($valorJuros <= 0) {
            return false;
        }

        return $parcela->valor_pago >= $valorJuros;
    }

    /**
     * Obter histórico de renovações (cadeia completa)
     */
    public function getHistoricoRenovacoes(): \Illuminate\Support\Collection
    {
        $historico = collect([$this]);

        // Buscar empréstimo original (se este for uma renovação)
        $origem = $this;
        while ($origem->emprestimo_origem_id) {
            $origem = self::with(['cliente', 'operacao', 'consultor'])->find($origem->emprestimo_origem_id);
            if (! $origem) {
                break;
            }
            $historico->prepend($origem);
        }

        // Buscar todas as renovações deste empréstimo recursivamente
        $idsProcessados = $historico->pluck('id')->toArray();
        $renovacoes = $this->renovacoes()->with(['cliente', 'operacao', 'consultor'])->orderBy('data_inicio')->get();
        foreach ($renovacoes as $renovacao) {
            if (! in_array($renovacao->id, $idsProcessados)) {
                $historico->push($renovacao);
                $idsProcessados[] = $renovacao->id;
            }
            // Buscar renovações das renovações recursivamente
            $subRenovacoes = $renovacao->renovacoes()->with(['cliente', 'operacao', 'consultor'])->orderBy('data_inicio')->get();
            foreach ($subRenovacoes as $subRenovacao) {
                if (! in_array($subRenovacao->id, $idsProcessados)) {
                    $historico->push($subRenovacao);
                    $idsProcessados[] = $subRenovacao->id;
                }
            }
        }

        return $historico->unique('id')->values();
    }
}
