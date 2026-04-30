<?php

namespace App\Modules\Core\Services;

use App\Helpers\ValidacaoDocumento;
use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDadosCliente;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Fonte única para leitura/escrita de dados cadastrais do cliente no contexto de uma operação.
 * Usa atributos brutos de {@see Cliente} ao hidratar fallback (sem accessors de ClienteDadosEmpresa).
 */
class OperacaoDadosClienteService
{
    /** @var list<string> Campos exibidos no formulário interno de edição de cliente. */
    private const CHAVES_FORMULARIO_EDICAO = [
        'nome',
        'telefone',
        'email',
        'data_nascimento',
        'responsavel_nome',
        'responsavel_cpf',
        'responsavel_rg',
        'responsavel_cnh',
        'responsavel_cargo',
        'endereco',
        'numero',
        'cidade',
        'estado',
        'cep',
        'observacoes',
    ];

    /** @var list<string> */
    private const CAMPOS_EDITAVEIS = [
        'empresa_id',
        'nome',
        'telefone',
        'email',
        'data_nascimento',
        'responsavel_nome',
        'responsavel_cpf',
        'responsavel_rg',
        'responsavel_cnh',
        'responsavel_cargo',
        'endereco',
        'numero',
        'cidade',
        'estado',
        'cep',
        'observacoes',
    ];

    /**
     * Registro persistido para o par (cliente, operação), ou null se ainda não existir.
     */
    public function obterParaOperacao(int $clienteId, int $operacaoId): ?OperacaoDadosCliente
    {
        return OperacaoDadosCliente::query()
            ->where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->first();
    }

    /**
     * Garante uma linha em operacao_dados_clientes: se não existir, cria a partir dos atributos brutos de clientes.
     *
     * @throws ModelNotFoundException se cliente ou operação não existir
     */
    public function garantirRegistro(int $clienteId, int $operacaoId, ?int $empresaIdOperacao = null): OperacaoDadosCliente
    {
        $existente = $this->obterParaOperacao($clienteId, $operacaoId);
        if ($existente) {
            return $existente;
        }

        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($clienteId);

        if (! $cliente) {
            throw (new ModelNotFoundException)->setModel(Cliente::class, [$clienteId]);
        }

        $operacao = Operacao::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($operacaoId);

        if (! $operacao) {
            throw (new ModelNotFoundException)->setModel(Operacao::class, [$operacaoId]);
        }

        $empresaId = $empresaIdOperacao ?? $operacao->empresa_id;
        $payload = $this->payloadBrutoFromCliente($cliente, $empresaId);

        return OperacaoDadosCliente::create(array_merge(
            [
                'cliente_id' => $clienteId,
                'operacao_id' => $operacaoId,
            ],
            $payload
        ));
    }

    /**
     * Cria ou atualiza os dados da ficha para o par (cliente, operação).
     * Apenas chaves editáveis são consideradas; demais entradas em $dados são ignoradas.
     *
     * @param  array<string, mixed>  $dados
     *
     * @throws ModelNotFoundException se cliente ou operação não existir
     */
    public function salvarOuAtualizar(
        int $clienteId,
        int $operacaoId,
        array $dados,
        ?int $empresaIdOperacao = null
    ): OperacaoDadosCliente {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($clienteId);

        if (! $cliente) {
            throw (new ModelNotFoundException)->setModel(Cliente::class, [$clienteId]);
        }

        $operacao = Operacao::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($operacaoId);

        if (! $operacao) {
            throw (new ModelNotFoundException)->setModel(Operacao::class, [$operacaoId]);
        }

        $empresaId = $empresaIdOperacao ?? $operacao->empresa_id;
        $filtrado = $this->filtrarCamposEditaveis($dados);

        $existente = $this->obterParaOperacao($clienteId, $operacaoId);

        if ($existente) {
            $existente->fill(array_merge(['empresa_id' => $empresaId], $filtrado));
            $existente->save();

            return $existente->fresh();
        }

        // Nova ficha: só o que veio em $dados (não hidratar com colunas de `clientes` — evita vazar endereço/telefone
        // de outra empresa na operação). `nome` é obrigatório na tabela; se o chamador não enviou, usa o do cliente.
        $createPayload = array_merge(['empresa_id' => $empresaId], $filtrado);
        if (! array_key_exists('nome', $createPayload) || $createPayload['nome'] === null || $createPayload['nome'] === '') {
            $attrs = $cliente->getAttributes();
            $createPayload['nome'] = (string) ($attrs['nome'] ?? '');
        }

        return OperacaoDadosCliente::create(array_merge(
            [
                'cliente_id' => $clienteId,
                'operacao_id' => $operacaoId,
            ],
            $createPayload
        ));
    }

    /**
     * Monta o payload a partir das colunas brutas de clientes (alinhado ao backfill).
     *
     * @return array<string, mixed>
     */
    public function payloadBrutoFromCliente(Cliente $cliente, ?int $empresaIdOperacao): array
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

    /**
     * Payload para salvarOuAtualizar a partir de dados validados de formulário (link público, create/edit interno).
     * $validated deve conter tipo_pessoa, nome, contato, endereço e campos de responsável (PJ) quando aplicável.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function payloadFromFormularioValidado(array $validated): array
    {
        $payload = [
            'nome' => $validated['nome'],
            'telefone' => $validated['telefone'] ?? null,
            'email' => $validated['email'] ?? null,
            'data_nascimento' => $validated['data_nascimento'] ?? null,
            'endereco' => $validated['endereco'] ?? null,
            'numero' => $validated['numero'] ?? null,
            'cidade' => $validated['cidade'] ?? null,
            'estado' => $validated['estado'] ?? null,
            'cep' => $validated['cep'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
        ];

        if (($validated['tipo_pessoa'] ?? 'fisica') === 'juridica') {
            $payload['responsavel_nome'] = $validated['responsavel_nome'] ?? null;
            $payload['responsavel_cpf'] = ! empty($validated['responsavel_cpf'])
                ? preg_replace('/[^0-9]/', '', $validated['responsavel_cpf'])
                : null;
            $payload['responsavel_rg'] = $validated['responsavel_rg'] ?? null;
            $payload['responsavel_cnh'] = $validated['responsavel_cnh'] ?? null;
            $payload['responsavel_cargo'] = $validated['responsavel_cargo'] ?? null;
        } else {
            $payload['responsavel_nome'] = null;
            $payload['responsavel_cpf'] = null;
            $payload['responsavel_rg'] = null;
            $payload['responsavel_cnh'] = null;
            $payload['responsavel_cargo'] = null;
        }

        return $payload;
    }

    /**
     * Valores para pré-preencher o formulário de edição quando há contexto de operação (?operacao_id=).
     * Se existir linha em `operacao_dados_clientes`, usa esses dados; senão, fallback alinhado ao backfill ({@see payloadBrutoFromCliente}).
     *
     * @return array<string, mixed>
     */
    public function valoresFormularioParaOperacao(Cliente $cliente, int $operacaoId, ?int $empresaIdOperacao): array
    {
        $ficha = $this->obterParaOperacao((int) $cliente->id, $operacaoId);

        if ($ficha) {
            $raw = $ficha->getAttributes();
            $out = [];
            foreach (self::CHAVES_FORMULARIO_EDICAO as $key) {
                $out[$key] = array_key_exists($key, $raw) ? $raw[$key] : null;
            }
        } else {
            $payload = $this->payloadBrutoFromCliente($cliente, $empresaIdOperacao);
            unset($payload['empresa_id']);
            $out = [];
            foreach (self::CHAVES_FORMULARIO_EDICAO as $key) {
                $out[$key] = $payload[$key] ?? null;
            }
        }

        $out['data_nascimento'] = $this->normalizarDataParaInputDate($out['data_nascimento'] ?? null);
        $out['responsavel_cpf'] = $this->formatarCpfResponsavelParaExibicao($out['responsavel_cpf'] ?? null);

        return $out;
    }

    private function normalizarDataParaInputDate(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        try {
            return \Carbon\Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatarCpfResponsavelParaExibicao(?string $cpf): ?string
    {
        if ($cpf === null || $cpf === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $cpf);
        if ($digits === '') {
            return null;
        }

        return ValidacaoDocumento::formatarCpf($digits);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function filtrarCamposEditaveis(array $dados): array
    {
        $allowed = array_flip(self::CAMPOS_EDITAVEIS);
        $out = [];
        foreach ($dados as $key => $value) {
            if (isset($allowed[$key])) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
