<?php

namespace App\Console\Commands;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDadosCliente;
use App\Modules\Core\Models\OperationClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOperacaoDadosClientesCommand extends Command
{
    protected $signature = 'operacao-dados-clientes:backfill
                            {--dry-run : Apenas simula, não grava}
                            {--refresh : Atualiza linhas existentes a partir de clientes (sobrescreve)}';

    protected $description = 'Preenche operacao_dados_clientes a partir de operation_clients + colunas da tabela clientes';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $refresh = (bool) $this->option('refresh');

        $query = OperationClient::query()->orderBy('id');
        $total = $query->count();

        if ($total === 0) {
            $this->warn('Nenhum vínculo ativo em operation_clients.');

            return self::SUCCESS;
        }

        $this->info("Vínculos a processar: {$total}".($dryRun ? ' (dry-run)' : '').($refresh ? ' [refresh]' : ''));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $missingCliente = 0;
        $missingOperacao = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($query->cursor() as $oc) {
            $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)
                ->whereNull('deleted_at')
                ->find($oc->cliente_id);

            if (! $cliente) {
                $missingCliente++;
                $bar->advance();

                continue;
            }

            $operacao = Operacao::withoutGlobalScope(EmpresaScope::class)
                ->whereNull('deleted_at')
                ->find($oc->operacao_id);

            if (! $operacao) {
                $missingOperacao++;
                $bar->advance();

                continue;
            }

            $payload = $this->payloadFromCliente($cliente, $operacao->empresa_id);

            $existing = OperacaoDadosCliente::query()
                ->where('cliente_id', $oc->cliente_id)
                ->where('operacao_id', $oc->operacao_id)
                ->first();

            if ($existing && ! $refresh) {
                $skipped++;
                $bar->advance();

                continue;
            }

            if ($dryRun) {
                if ($existing && $refresh) {
                    $updated++;
                } elseif (! $existing) {
                    $created++;
                }
                $bar->advance();

                continue;
            }

            DB::transaction(function () use ($existing, $oc, $payload, &$created, &$updated, $refresh) {
                if ($existing && $refresh) {
                    $existing->update($payload);
                    $updated++;

                    return;
                }

                if (! $existing) {
                    OperacaoDadosCliente::create(array_merge(
                        [
                            'cliente_id' => $oc->cliente_id,
                            'operacao_id' => $oc->operacao_id,
                        ],
                        $payload
                    ));
                    $created++;
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Criados', $created],
                ['Atualizados (refresh)', $updated],
                ['Ignorados (já existia)', $skipped],
                ['Cliente não encontrado', $missingCliente],
                ['Operação não encontrada', $missingOperacao],
            ]
        );

        if ($dryRun) {
            $this->warn('Dry-run: nenhuma alteração foi gravada.');
        }

        return self::SUCCESS;
    }

    /**
     * Usa atributos brutos de clientes (sem accessors de ClienteDadosEmpresa).
     *
     * @return array<string, mixed>
     */
    private function payloadFromCliente(Cliente $cliente, ?int $empresaIdOperacao): array
    {
        $a = $cliente->getAttributes();

        return [
            'empresa_id' => $empresaIdOperacao,
            'nome' => $a['nome'] ?? '',
            'telefone' => $a['telefone'] ?? null,
            'email' => $a['email'] ?? null,
            'data_nascimento' => $a['data_nascimento'] ?? null,
            'responsavel_nome' => $a['responsavel_nome'] ?? null,
            'responsavel_cpf' => $a['responsavel_cpf'] ?? null,
            'responsavel_rg' => $a['responsavel_rg'] ?? null,
            'responsavel_cnh' => $a['responsavel_cnh'] ?? null,
            'responsavel_cargo' => $a['responsavel_cargo'] ?? null,
            'endereco' => $a['endereco'] ?? null,
            'numero' => $a['numero'] ?? null,
            'cidade' => $a['cidade'] ?? null,
            'estado' => $a['estado'] ?? null,
            'cep' => $a['cep'] ?? null,
            'observacoes' => $a['observacoes'] ?? null,
        ];
    }
}
