<?php

namespace App\Modules\Core\Controllers;

use App\Helpers\RefEncoder;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\ClienteDadosEmpresa;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperationClient;
use App\Modules\Core\Services\ClienteService;
use App\Modules\Core\Services\OperacaoDadosClienteService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cadastro de cliente via link público (sem login).
 * O link contém ref codificado com operacao_id e consultor_id.
 */
class CadastroClienteController extends Controller
{
    public function __construct(
        protected ClienteService $clienteService,
        protected OperacaoDadosClienteService $operacaoDadosClienteService
    ) {
        $this->middleware('throttle:10,1')->only(['showForm', 'store']);
    }

    /**
     * Resolve e valida ref; retorna [Operacao, User consultor] ou abort.
     */
    private function resolveRef(string $ref): array
    {
        try {
            [$operacaoId, $consultorId] = RefEncoder::decode($ref);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with('documentosObrigatorios')
            ->where('ativo', true)
            ->find($operacaoId);

        if (!$operacao) {
            abort(404, 'Operação não encontrada ou inativa.');
        }

        $consultor = User::find($consultorId);
        if (!$consultor || !$consultor->temAcessoOperacao($operacaoId)) {
            abort(404, 'Link inválido.');
        }

        return [$operacao, $consultor];
    }

    /**
     * Exibe o formulário de cadastro (página pública).
     */
    public function showForm(Request $request): View|RedirectResponse
    {
        $ref = $request->query('ref');
        if (!$ref) {
            return redirect()->route('home')->with('error', 'Link de cadastro inválido. Solicite um novo link ao seu consultor.');
        }

        [$operacao, $consultor] = $this->resolveRef($ref);

        $documentosObrigatorios = $operacao->documentosObrigatorios->pluck('tipo_documento')->toArray();

        return view('cadastro-cliente.form', [
            'ref' => $ref,
            'operacao' => $operacao,
            'documentosObrigatorios' => $documentosObrigatorios,
        ]);
    }

    /**
     * Processa o cadastro enviado pelo cliente (POST).
     */
    public function store(Request $request): RedirectResponse
    {
        $ref = $request->input('ref');
        if (!$ref) {
            return back()->with('error', 'Link de cadastro inválido.')->withInput();
        }

        [$operacao, $consultor] = $this->resolveRef($ref);

        $docsObrigatorios = $operacao->documentosObrigatorios->pluck('tipo_documento')->toArray();
        $documentoFile = $request->file('documento_cliente');
        $selfieFile = $request->file('selfie_documento');

        if (in_array('documento_cliente', $docsObrigatorios) && !$documentoFile) {
            return back()->with('error', 'O documento do cliente é obrigatório para esta operação.')->withInput();
        }
        if (in_array('selfie_documento', $docsObrigatorios) && !$selfieFile) {
            return back()->with('error', 'A selfie com documento é obrigatória para esta operação.')->withInput();
        }

        $regrasDoc = in_array('documento_cliente', $docsObrigatorios)
            ? 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'
            : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        $regrasSelfie = in_array('selfie_documento', $docsObrigatorios)
            ? 'required|file|mimes:jpg,jpeg,png|max:5120'
            : 'nullable|file|mimes:jpg,jpeg,png|max:5120';

        $validated = $request->validate([
            'ref' => 'required|string',
            'tipo_pessoa' => 'required|in:fisica,juridica',
            'documento' => 'required|string|min:11|max:18',
            'nome' => 'required|string|max:255',
            'telefone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'data_nascimento' => 'nullable|date',
            'responsavel_nome' => 'nullable|string|max:255',
            'responsavel_cpf' => 'nullable|string|min:11|max:14',
            'responsavel_rg' => 'nullable|string|max:20',
            'responsavel_cnh' => 'nullable|string|max:20',
            'responsavel_cargo' => 'nullable|string|max:100',
            'endereco' => 'required|string|max:255',
            'numero' => 'required|string|max:20',
            'cidade' => 'required|string|max:255',
            'estado' => 'required|string|size:2',
            'cep' => 'required|string|max:10',
            'observacoes' => 'nullable|string',
            'documento_cliente' => $regrasDoc,
            'selfie_documento' => $regrasSelfie,
            'anexos.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $documentoLimpo = preg_replace('/[^0-9]/', '', $validated['documento']);
        $clienteExistente = Cliente::buscarPorDocumento($documentoLimpo);

        try {
            if ($clienteExistente) {
                // Cliente já existe: verificar se já está vinculado a esta operação
                $jaVinculadoOperacao = OperationClient::where('cliente_id', $clienteExistente->id)
                    ->where('operacao_id', $operacao->id)
                    ->exists();

                if ($jaVinculadoOperacao) {
                    // Link só cria cadastro: se já está nesta operação, não altera dados — só informa na página final
                    return redirect()->route('cadastro-cliente.concluido')
                        ->with('ja_cadastrado_nesta_operacao', true)
                        ->with('success', 'Você já está cadastrado nesta operação. Nenhum dado foi alterado.');
                }

                // CPF já cadastrado em outra operação: não alterar cadastro global; gravar dados na empresa da operação do link e criar vínculo
                $empresaId = $operacao->empresa_id;
                $dadosEmpresa = [
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
                    $dadosEmpresa['responsavel_nome'] = $validated['responsavel_nome'] ?? null;
                    $dadosEmpresa['responsavel_cpf'] = !empty($validated['responsavel_cpf'])
                        ? preg_replace('/[^0-9]/', '', $validated['responsavel_cpf'])
                        : null;
                    $dadosEmpresa['responsavel_rg'] = $validated['responsavel_rg'] ?? null;
                    $dadosEmpresa['responsavel_cnh'] = $validated['responsavel_cnh'] ?? null;
                    $dadosEmpresa['responsavel_cargo'] = $validated['responsavel_cargo'] ?? null;
                } else {
                    $dadosEmpresa['responsavel_nome'] = null;
                    $dadosEmpresa['responsavel_cpf'] = null;
                    $dadosEmpresa['responsavel_rg'] = null;
                    $dadosEmpresa['responsavel_cnh'] = null;
                    $dadosEmpresa['responsavel_cargo'] = null;
                }

                ClienteDadosEmpresa::updateOrCreate(
                    [
                        'cliente_id' => $clienteExistente->id,
                        'empresa_id' => $empresaId,
                    ],
                    $dadosEmpresa
                );

                if ($clienteExistente->empresa_id != $empresaId) {
                    $this->clienteService->vincularClienteEmpresa($clienteExistente->id, $empresaId, $consultor->id);
                }

                $this->clienteService->vincularOperacao(
                    $clienteExistente->id,
                    $operacao->id,
                    0,
                    $consultor->id,
                    null
                );

                $this->operacaoDadosClienteService->salvarOuAtualizar(
                    $clienteExistente->id,
                    $operacao->id,
                    $this->operacaoDadosClienteService->payloadFromFormularioValidado($validated),
                    $operacao->empresa_id
                );

                $this->clienteService->processarDocumentosParaOperacao(
                    $clienteExistente->id,
                    [
                        'documento_cliente' => $documentoFile,
                        'selfie_documento' => $selfieFile,
                        'anexos' => $request->file('anexos'),
                    ],
                    $operacao->id
                );

                return redirect()->route('cadastro-cliente.concluido')
                    ->with('success', 'Cadastro realizado com sucesso! O consultor entrará em contato.');
            }

            // Cliente novo: criar e vincular
            $dadosCliente = $validated;
            $dadosCliente['empresa_id'] = $operacao->empresa_id;
            $dadosCliente['operacao_id_documentos'] = $operacao->id;
            $dadosCliente['documentos'] = [
                'documento_cliente' => $documentoFile,
                'selfie_documento' => $selfieFile,
                'anexos' => $request->file('anexos'),
            ];
            unset($dadosCliente['ref'], $dadosCliente['documento_cliente'], $dadosCliente['selfie_documento'], $dadosCliente['anexos']);

            $cliente = $this->clienteService->cadastrar($dadosCliente);

            $this->clienteService->vincularOperacao(
                $cliente->id,
                $operacao->id,
                0,
                $consultor->id,
                null
            );

            $this->operacaoDadosClienteService->salvarOuAtualizar(
                $cliente->id,
                $operacao->id,
                $this->operacaoDadosClienteService->payloadFromFormularioValidado($validated),
                $operacao->empresa_id
            );

            return redirect()->route('cadastro-cliente.concluido')
                ->with('success', 'Cadastro realizado com sucesso! O consultor entrará em contato.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao cadastrar cliente via link: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Não foi possível concluir o cadastro. Tente novamente ou entre em contato com o consultor.')->withInput();
        }
    }

    /**
     * Página de confirmação após cadastro (evita reenvio ao recarregar).
     */
    public function concluido(): View
    {
        return view('cadastro-cliente.concluido');
    }
}
