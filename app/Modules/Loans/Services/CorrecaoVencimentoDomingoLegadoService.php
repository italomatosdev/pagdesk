<?php

namespace App\Modules\Loans\Services;

use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrecaoVencimentoDomingoLegadoService
{
    use Auditable;

    /**
     * Legado: coluna NULL (nunca escolheu switch).
     */
    public function legadoSemFlag(Emprestimo $emprestimo): bool
    {
        $v = $emprestimo->getAttributes()['deslocar_vencimento_domingo'] ?? null;

        return $v === null;
    }

    /**
     * Parcelas em aberto (não totalmente quitadas, não canceladas).
     *
     * @param  Collection<int, Parcela>  $parcelas
     * @return Collection<int, Parcela>
     */
    public function parcelasEmAberto(Collection $parcelas): Collection
    {
        return $parcelas
            ->filter(fn (Parcela $p) => $p->status !== 'cancelada')
            ->filter(fn (Parcela $p) => ! $p->isTotalmentePaga())
            ->sortBy('numero')
            ->values();
    }

    /**
     * Totalmente pagas (congeladas): não entram na remontagem.
     *
     * @param  Collection<int, Parcela>  $parcelas
     * @return Collection<int, Parcela>
     */
    public function parcelasCongeladas(Collection $parcelas): Collection
    {
        return $parcelas->filter(fn (Parcela $p) => $p->isTotalmentePaga())->values();
    }

    public function contarParcelasDomingoEmAberto(Emprestimo $emprestimo): int
    {
        $emprestimo->loadMissing('parcelas');

        return $this->parcelasEmAberto($emprestimo->parcelas)
            ->filter(fn (Parcela $p) => $this->venceEmDomingo($p))
            ->count();
    }

    public function podeExibirAviso(Emprestimo $emprestimo): bool
    {
        if (! $this->legadoSemFlag($emprestimo)) {
            return false;
        }
        if ($emprestimo->isTrocaCheque()) {
            return false;
        }
        if ($emprestimo->parcelas->isEmpty()) {
            return false;
        }

        return $this->contarParcelasDomingoEmAberto($emprestimo) > 0;
    }

    public function podeAplicar(Emprestimo $emprestimo): bool
    {
        return $this->podeExibirAviso($emprestimo);
    }

    /**
     * @return array{alteracoes: array<int, array{parcela_id: int, numero: int, data_antiga: string, data_nova: string}>, fingerprint: string}
     */
    public function previsualizar(Emprestimo $emprestimo): array
    {
        $this->garantirElegivel($emprestimo);

        $parcelas = $emprestimo->parcelas->sortBy('numero')->values();
        $congeladas = $this->parcelasCongeladas($parcelas);
        $remontaveis = $this->parcelasEmAberto($parcelas);

        if ($remontaveis->isEmpty()) {
            throw ValidationException::withMessages([
                'emprestimo' => 'Não há parcelas em aberto para remontar.',
            ]);
        }

        $cursor = $this->cursorInicial($emprestimo, $congeladas);
        $alteracoes = [];

        foreach ($remontaveis as $index => $parcela) {
            $cursor = $this->normalizarSemDomingo($cursor);
            $novaData = $cursor->copy()->startOfDay();

            if (! $parcela->data_vencimento->equalTo($novaData)) {
                $alteracoes[] = [
                    'parcela_id' => $parcela->id,
                    'numero' => (int) $parcela->numero,
                    'data_antiga' => $parcela->data_vencimento->format('Y-m-d'),
                    'data_nova' => $novaData->format('Y-m-d'),
                ];
            }

            if ($index < $remontaveis->count() - 1) {
                $cursor = $this->avancarCursor($cursor, (string) $emprestimo->frequencia);
            }
        }

        $fingerprint = $this->montarFingerprint($remontaveis);

        return [
            'alteracoes' => $alteracoes,
            'fingerprint' => $fingerprint,
        ];
    }

    public function aplicar(Emprestimo $emprestimo, string $fingerprintEnviado): void
    {
        $this->garantirElegivel($emprestimo);

        DB::transaction(function () use ($emprestimo, $fingerprintEnviado) {
            Parcela::where('emprestimo_id', $emprestimo->id)->lockForUpdate()->get();
            $emprestimoAtual = Emprestimo::lockForUpdate()->findOrFail($emprestimo->id);

            $this->garantirElegivel($emprestimoAtual);

            $parcelas = $emprestimoAtual->parcelas()->orderBy('numero')->get();
            $congeladas = $this->parcelasCongeladas($parcelas);
            $remontaveis = $this->parcelasEmAberto($parcelas);

            if ($remontaveis->isEmpty()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não há parcelas em aberto para remontar.',
                ]);
            }

            $esperado = $this->montarFingerprint($remontaveis);
            if (! hash_equals($esperado, $fingerprintEnviado)) {
                throw ValidationException::withMessages([
                    'fingerprint' => 'O cronograma foi alterado. Atualize a página e gere a prévia novamente.',
                ]);
            }

            $cursor = $this->cursorInicial($emprestimoAtual, $congeladas);
            $logAntigo = [];
            $logNovo = [];

            foreach ($remontaveis as $index => $parcela) {
                $cursor = $this->normalizarSemDomingo($cursor);
                $novaData = $cursor->copy()->startOfDay();
                $antiga = $parcela->data_vencimento->copy()->startOfDay();

                if (! $antiga->equalTo($novaData)) {
                    $logAntigo[$parcela->id] = $antiga->format('Y-m-d');
                    $logNovo[$parcela->id] = $novaData->format('Y-m-d');
                    $parcela->update(['data_vencimento' => $novaData->format('Y-m-d')]);
                }

                $parcela->refresh();
                $this->atualizarAtrasoEStatus($parcela);

                if ($index < $remontaveis->count() - 1) {
                    $cursor = $this->avancarCursor($cursor, (string) $emprestimoAtual->frequencia);
                }
            }

            $emprestimoAtual->update(['deslocar_vencimento_domingo' => true]);

            if ($logAntigo !== []) {
                self::auditar(
                    'correcao_vencimento_domingo_legado',
                    $emprestimoAtual,
                    ['parcelas_data_vencimento' => $logAntigo],
                    ['parcelas_data_vencimento' => $logNovo],
                    'Remontagem de vencimentos (legado, evitar domingo).'
                );
            }
        });
    }

    private function garantirElegivel(Emprestimo $emprestimo): void
    {
        if (! $this->legadoSemFlag($emprestimo)) {
            throw ValidationException::withMessages([
                'emprestimo' => 'Esta correção só se aplica a contratos sem preferência gravada (legado).',
            ]);
        }
        if ($emprestimo->isTrocaCheque()) {
            throw ValidationException::withMessages([
                'emprestimo' => 'Troca de cheque não possui cronograma de parcelas para esta correção.',
            ]);
        }
        if ($this->contarParcelasDomingoEmAberto($emprestimo) === 0) {
            throw ValidationException::withMessages([
                'emprestimo' => 'Não há parcelas em aberto com vencimento em domingo.',
            ]);
        }
    }

    private function venceEmDomingo(Parcela $parcela): bool
    {
        return $parcela->data_vencimento->dayOfWeek === Carbon::SUNDAY;
    }

    /**
     * @param  Collection<int, Parcela>  $remontaveis
     */
    private function montarFingerprint(Collection $remontaveis): string
    {
        $partes = $remontaveis->map(function (Parcela $p) {
            return $p->id.':'.$p->data_vencimento->format('Y-m-d');
        })->sort()->values()->all();

        return hash('sha256', implode('|', $partes));
    }

    /**
     * @param  Collection<int, Parcela>  $congeladas
     */
    private function cursorInicial(Emprestimo $emprestimo, Collection $congeladas): Carbon
    {
        if ($congeladas->isEmpty()) {
            $dataVencimento = Carbon::parse($emprestimo->data_inicio)->startOfDay();
            switch ($emprestimo->frequencia) {
                case 'diaria':
                    $dataVencimento->addDay();
                    break;
                case 'semanal':
                    $dataVencimento->addWeek();
                    break;
                case 'quinzenal':
                    $dataVencimento->addDays(15);
                    break;
                case 'mensal':
                    $dataVencimento = EmprestimoService::proximoMesMesmoDia(
                        Carbon::parse($emprestimo->data_inicio)->copy()->startOfDay(),
                        1
                    );
                    break;
                default:
                    throw new \InvalidArgumentException('Frequência de parcelas não suportada: '.$emprestimo->frequencia);
            }

            return $this->normalizarSemDomingo($dataVencimento);
        }

        $max = $congeladas->max(fn (Parcela $p) => $p->data_vencimento->timestamp);
        $H = Carbon::createFromTimestamp((int) $max)->startOfDay();
        $cursor = $H->copy()->addDay();

        return $this->normalizarSemDomingo($cursor);
    }

    private function avancarCursor(Carbon $cursor, string $frequencia): Carbon
    {
        return match ($frequencia) {
            'diaria' => $cursor->copy()->addDay(),
            'semanal' => $cursor->copy()->addWeek(),
            'quinzenal' => $cursor->copy()->addDays(15),
            'mensal' => EmprestimoService::proximoMesMesmoDia($cursor->copy(), 1),
            default => throw new \InvalidArgumentException('Frequência de parcelas não suportada: '.$frequencia),
        };
    }

    private function normalizarSemDomingo(Carbon $data): Carbon
    {
        $d = $data->copy()->startOfDay();
        if ($d->dayOfWeek === Carbon::SUNDAY) {
            $d->addDay();
        }

        return $d;
    }

    private function atualizarAtrasoEStatus(Parcela $parcela): void
    {
        if ($parcela->isTotalmentePaga() || $parcela->isQuitadaGarantia() || $parcela->status === 'cancelada') {
            return;
        }

        $dias = $parcela->calcularDiasAtraso();
        $data = ['dias_atraso' => $dias];

        if (in_array($parcela->status, ['pendente', 'atrasada'], true)) {
            $data['status'] = $parcela->data_vencimento->lt(Carbon::today()) ? 'atrasada' : 'pendente';
        }

        $parcela->update($data);
    }
}
