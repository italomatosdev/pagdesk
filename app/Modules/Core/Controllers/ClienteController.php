<?php

namespace App\Modules\Core\Controllers;

use App\Helpers\RefEncoder;
use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\ClientDocument;
use App\Modules\Core\Models\ClienteDadosEmpresa;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperationClient;
use Illuminate\Support\Collection;
use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Services\ClienteService;
use App\Modules\Core\Services\ClienteConsultaService;
use App\Modules\Core\Services\OperacaoDadosClienteService;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClienteController extends Controller
{
    protected ClienteService $clienteService;

    protected ClienteConsultaService $consultaService;

    protected OperacaoDadosClienteService $operacaoDadosClienteService;

    public function __construct(
        ClienteService $clienteService,
        ClienteConsultaService $consultaService,
        OperacaoDadosClienteService $operacaoDadosClienteService
    ) {
        $this->middleware('auth');
        $this->clienteService = $clienteService;
        $this->consultaService = $consultaService;
        $this->operacaoDadosClienteService = $operacaoDadosClienteService;
    }

    /**
     * Listar clientes
     * Super Admin: todos. Demais: apenas clientes vinculados às operações do usuário.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $operacoesIds = $isSuperAdmin ? [] : $user->getOperacoesIds();
        $empresaId = $user->empresa_id ?? null;

        $query = Cliente::query();

        if ($isSuperAdmin) {
            $query->with('empresa');
        } else {
            // Admin, gestor e consultor: apenas clientes das minhas operações
            if (empty($operacoesIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                    ->whereHas('operationClients', function ($q) use ($operacoesIds) {
                        $q->whereIn('operacao_id', $operacoesIds);
                    });
            }
        }

        // Filtro por operação (lista + links externos)
        $operacaoIdRequest = $request->filled('operacao_id') ? (int) $request->operacao_id : null;
        $operacaoIdFiltro = null;
        if ($operacaoIdRequest && ($isSuperAdmin || in_array($operacaoIdRequest, $operacoesIds, true))) {
            $operacaoIdFiltro = $operacaoIdRequest;
            $query->whereHas('operationClients', fn ($q) => $q->where('operacao_id', $operacaoIdFiltro));
        }

        // Filtro por CPF
        if ($request->filled('documento')) {
            $documento = preg_replace('/[^0-9]/', '', $request->documento);
            $query->where('documento', 'like', "%{$documento}%");
        }
        
        // Manter compatibilidade com filtro 'cpf' antigo
        if ($request->filled('cpf')) {
            $documento = preg_replace('/[^0-9]/', '', $request->cpf);
            $query->where('documento', 'like', "%{$documento}%");
        }

        // Filtro por nome (com operação: também busca em operacao_dados_clientes dessa operação)
        if ($request->filled('nome')) {
            $this->aplicarFiltroNomeListagemClientes($query, $request->nome, $operacaoIdFiltro);
        }

        // Contadores (respeitam os mesmos filtros da listagem)
        $total = (clone $query)->count();
        $comEmprestimoAtivo = (clone $query)->whereHas('emprestimos', fn ($q) => $q->where('status', 'ativo'))->count();
        $comParcelaAtrasada = (clone $query)->whereHas('emprestimos.parcelas', fn ($q) => $q->where('status', 'atrasada'))->count();
        $semEmprestimoAtivo = (clone $query)->whereDoesntHave('emprestimos', fn ($q) => $q->where('status', 'ativo'))->count();
        $novosNoMes = (clone $query)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $stats = [
            'total' => $total,
            'com_emprestimo_ativo' => $comEmprestimoAtivo,
            'com_parcela_atrasada' => $comParcelaAtrasada,
            'sem_emprestimo_ativo' => $semEmprestimoAtivo,
            'novos_no_mes' => $novosNoMes,
        ];

        // Super Admin: adiciona PF/PJ (totais globais para contexto)
        if ($isSuperAdmin) {
            $stats['pessoa_fisica'] = (clone $query)->where('tipo_pessoa', 'fisica')->count();
            $stats['pessoa_juridica'] = (clone $query)->where('tipo_pessoa', 'juridica')->count();
        }

        $with = [
            'operationClients' => function ($q) use ($operacoesIds, $isSuperAdmin) {
                if (! $isSuperAdmin && ! empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
                $q->with('operacao');
            },
            'empresasVinculadas' => function ($q) use ($empresaId) {
                if ($empresaId) {
                    $q->where('empresa_id', $empresaId);
                }
            },
        ];
        if ($operacaoIdFiltro) {
            $with['operacaoDadosClientes'] = fn ($q) => $q->where('operacao_id', $operacaoIdFiltro);
        }

        $clientes = $query->with($with)
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        if ($isSuperAdmin) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
                : collect();
        }

        return view('clientes.index', compact('clientes', 'isSuperAdmin', 'stats', 'operacoes', 'operacaoIdFiltro'));
    }

    /**
     * Exportar listagem de clientes em CSV (abre no Excel).
     * Mesmo critério da listagem: Super Admin todos; demais apenas operações do usuário.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $operacoesIds = $isSuperAdmin ? [] : $user->getOperacoesIds();

        $query = Cliente::query();

        if ($isSuperAdmin) {
            $query->with('empresa');
        } else {
            if (empty($operacoesIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                    ->whereHas('operationClients', function ($q) use ($operacoesIds) {
                        $q->whereIn('operacao_id', $operacoesIds);
                    });
            }
        }

        if ($request->filled('documento')) {
            $documento = preg_replace('/[^0-9]/', '', $request->documento);
            $query->where('documento', 'like', "%{$documento}%");
        }
        if ($request->filled('cpf')) {
            $documento = preg_replace('/[^0-9]/', '', $request->cpf);
            $query->where('documento', 'like', "%{$documento}%");
        }
        $operacaoIdExport = null;
        $operacaoIdRequest = $request->filled('operacao_id') ? (int) $request->operacao_id : null;
        if ($operacaoIdRequest && ($isSuperAdmin || in_array($operacaoIdRequest, $operacoesIds, true))) {
            $operacaoIdExport = $operacaoIdRequest;
            $query->whereHas('operationClients', fn ($q) => $q->where('operacao_id', $operacaoIdRequest));
        }

        if ($request->filled('nome')) {
            $this->aplicarFiltroNomeListagemClientes($query, $request->nome, $operacaoIdExport);
        }

        $clientes = $query->orderBy('nome')->get();

        if ($operacaoIdExport) {
            $clientes->load([
                'operacaoDadosClientes' => fn ($q) => $q->where('operacao_id', $operacaoIdExport),
            ]);
        }

        $filename = 'clientes_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($clientes, $isSuperAdmin, $operacaoIdExport) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 para Excel

            $headers = ['Documento', 'Nome', 'Tipo', 'Telefone', 'Email', 'Cidade', 'Estado'];
            if ($isSuperAdmin) {
                $headers[] = 'Empresa';
            }
            fputcsv($out, $headers, ';');

            foreach ($clientes as $c) {
                $ficha = $operacaoIdExport ? $c->operacaoDadosClientes->first() : null;
                $tipo = $c->tipo_pessoa === 'fisica' ? 'PF' : 'PJ';
                $row = [
                    $c->documento,
                    $ficha?->nome ?? $c->nome,
                    $tipo,
                    $ficha?->telefone ?? $c->telefone ?? '',
                    $ficha?->email ?? $c->email ?? '',
                    $ficha?->cidade ?? $c->cidade ?? '',
                    $ficha?->estado ?? $c->estado ?? '',
                ];
                if ($isSuperAdmin && $c->relationLoaded('empresa')) {
                    $row[] = $c->empresa?->nome ?? '';
                } elseif ($isSuperAdmin) {
                    $row[] = '';
                }
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Gerar link para o cliente preencher o cadastro (página pública).
     * Exibe seletor de operação e o link com ref codificado.
     */
    public function linkCadastro(Request $request): View
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->where('ativo', true)->orderBy('nome')->get();
        } else {
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = $request->query('operacao_id');
        $linkCadastro = null;
        if ($operacaoId && ($user->isSuperAdmin() || in_array((int) $operacaoId, $operacoesIds, true))) {
            $ref = RefEncoder::encode((int) $operacaoId, $user->id);
            $linkCadastro = route('cadastro-cliente.form', ['ref' => $ref]);
        }

        return view('clientes.link-cadastro', [
            'operacoes' => $operacoes,
            'operacaoSelecionadaId' => $operacaoId ? (int) $operacaoId : null,
            'linkCadastro' => $linkCadastro,
        ]);
    }

    /**
     * Mostrar formulário de criação
     */
    public function create(): View
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->where('ativo', true)->with('documentosObrigatorios')->get();
        } else {
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->with('documentosObrigatorios')->get()
                : collect([]);
        }
        $operacaoSelecionadaId = $operacoes->count() === 1 ? $operacoes->first()->id : null;
        $documentObrigatoriosPorOperacao = [];
        foreach ($operacoes as $op) {
            $documentObrigatoriosPorOperacao[$op->id] = $op->documentosObrigatorios->pluck('tipo_documento')->values()->toArray();
        }
        return view('clientes.create', compact('operacoes', 'operacaoSelecionadaId', 'documentObrigatoriosPorOperacao'));
    }

    /**
     * Cadastrar novo cliente
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $request->validate([
            'operacao_id' => [
                'required',
                'exists:operacoes,id',
                function ($attr, $value, $fail) use ($user) {
                    if (!$value) {
                        return;
                    }
                    if ($user->isSuperAdmin()) {
                        return;
                    }
                    $ids = $user->getOperacoesIds();
                    if (empty($ids) || !in_array((int) $value, $ids, true)) {
                        $fail('Operação inválida ou sem permissão.');
                    }
                },
            ],
        ]);

        $operacao = Operacao::with('documentosObrigatorios')->findOrFail($request->operacao_id);
        $docsObrigatorios = $operacao->documentosObrigatorios->pluck('tipo_documento')->toArray();

        \Log::info('=== INÍCIO DO CADASTRO DE CLIENTE ===', [
            'method' => $request->method(),
            'operacao_id' => $request->operacao_id,
            'docs_obrigatorios' => $docsObrigatorios,
            'has_documento' => $request->hasFile('documento_cliente'),
            'has_selfie' => $request->hasFile('selfie_documento'),
        ]);

        $documentoFile = $request->file('documento_cliente');
        $selfieFile = $request->file('selfie_documento');

        if (in_array('documento_cliente', $docsObrigatorios) && !$documentoFile) {
            $errorMsg = 'O documento do cliente é obrigatório para esta operação e não foi enviado. ';
            $errorMsg .= 'Verifique: 1) Se selecionou um arquivo, 2) Se o tamanho não excede ' . ini_get('upload_max_filesize') . '.';
            return back()->with('error', $errorMsg)->withInput();
        }
        if (in_array('selfie_documento', $docsObrigatorios) && !$selfieFile) {
            $errorMsg = 'A selfie com documento é obrigatória para esta operação e não foi enviada. ';
            $errorMsg .= 'Verifique: 1) Se selecionou um arquivo, 2) Se o tamanho não excede ' . ini_get('upload_max_filesize') . '.';
            return back()->with('error', $errorMsg)->withInput();
        }

        if ($documentoFile && !$documentoFile->isValid()) {
            $error = $documentoFile->getError();
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'O documento do cliente excede upload_max_filesize (' . ini_get('upload_max_filesize') . ').',
                UPLOAD_ERR_FORM_SIZE => 'O documento do cliente excede MAX_FILE_SIZE do formulário.',
                UPLOAD_ERR_PARTIAL => 'O documento do cliente foi enviado parcialmente.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta uma pasta temporária.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão PHP parou o upload do arquivo.',
            ];
            $errorMessage = $errorMessages[$error] ?? 'Erro ao fazer upload do documento do cliente. Código de erro: ' . $error;
            return back()->with('error', $errorMessage)->withInput();
        }
        if ($selfieFile && !$selfieFile->isValid()) {
            $error = $selfieFile->getError();
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'A selfie excede upload_max_filesize (' . ini_get('upload_max_filesize') . ').',
                UPLOAD_ERR_FORM_SIZE => 'A selfie excede MAX_FILE_SIZE do formulário.',
                UPLOAD_ERR_PARTIAL => 'A selfie foi enviada parcialmente.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta uma pasta temporária.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão PHP parou o upload do arquivo.',
            ];
            $errorMessage = $errorMessages[$error] ?? 'Erro ao fazer upload da selfie com documento. Código de erro: ' . $error;
            return back()->with('error', $errorMessage)->withInput();
        }

        $regrasDoc = in_array('documento_cliente', $docsObrigatorios) ? 'required|file|mimes:pdf,jpg,jpeg,png|max:5120' : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        $regrasSelfie = in_array('selfie_documento', $docsObrigatorios) ? 'required|file|mimes:jpg,jpeg,png|max:5120' : 'nullable|file|mimes:jpg,jpeg,png|max:5120';

        $validated = $request->validate([
            'tipo_pessoa' => 'required|in:fisica,juridica',
            'documento' => 'required|string|min:11|max:18',
            'nome' => 'required|string|max:255',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'data_nascimento' => 'nullable|date',
            'responsavel_nome' => 'nullable|string|max:255',
            'responsavel_cpf' => 'nullable|string|min:11|max:14',
            'responsavel_rg' => 'nullable|string|max:20',
            'responsavel_cnh' => 'nullable|string|max:20',
            'responsavel_cargo' => 'nullable|string|max:100',
            'endereco' => 'nullable|string',
            'numero' => 'nullable|string|max:20',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'observacoes' => 'nullable|string',
            'documento_cliente' => $regrasDoc,
            'selfie_documento' => $regrasSelfie,
            'anexos.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            $documentoLimpo = preg_replace('/[^0-9]/', '', $validated['documento']);
            $clienteExistente = Cliente::buscarPorDocumento($documentoLimpo);

            $documentos = [
                'documento_cliente' => $documentoFile,
                'selfie_documento' => $selfieFile,
                'anexos' => $request->file('anexos'),
            ];

            if ($clienteExistente) {
                // Alinhado ao link público (CadastroClienteController): não criar novo registro em `clientes`.
                $jaVinculadoOperacao = OperationClient::where('cliente_id', $clienteExistente->id)
                    ->where('operacao_id', $operacao->id)
                    ->exists();

                if ($jaVinculadoOperacao) {
                    return redirect()->route('clientes.show', $clienteExistente->id)
                        ->with('info', 'Este cliente já está vinculado a esta operação. Nenhum dado foi alterado.');
                }

                $empresaId = (int) $operacao->empresa_id;
                $dadosEmpresa = $this->montarDadosEmpresaOverrideFromFormularioInterno($validated);

                ClienteDadosEmpresa::updateOrCreate(
                    [
                        'cliente_id' => $clienteExistente->id,
                        'empresa_id' => $empresaId,
                    ],
                    $dadosEmpresa
                );

                if ((int) $clienteExistente->empresa_id !== $empresaId) {
                    $this->clienteService->vincularClienteEmpresa($clienteExistente->id, $empresaId, auth()->id());
                }

                $this->clienteService->vincularOperacao(
                    $clienteExistente->id,
                    $operacao->id,
                    0,
                    auth()->id(),
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
                    $documentos,
                    $operacao->id
                );

                \Log::info('Cliente existente vinculado à operação (cadastro interno)', [
                    'cliente_id' => $clienteExistente->id,
                    'operacao_id' => $operacao->id,
                ]);

                return redirect()->route('clientes.show', $clienteExistente->id)
                    ->with('success', 'Cliente já existia no sistema. Foi vinculado a esta operação e os dados foram salvos na ficha da operação.');
            }

            $dadosCliente = $validated;
            unset($dadosCliente['documento_cliente'], $dadosCliente['selfie_documento'], $dadosCliente['anexos']);
            $dadosCliente['documentos'] = $documentos;
            $dadosCliente['operacao_id_documentos'] = (int) $operacao->id;

            $cliente = $this->clienteService->cadastrar($dadosCliente);

            $this->clienteService->vincularOperacao(
                $cliente->id,
                (int) $request->operacao_id,
                0,
                auth()->id(),
                null
            );

            $this->operacaoDadosClienteService->salvarOuAtualizar(
                $cliente->id,
                $operacao->id,
                $this->operacaoDadosClienteService->payloadFromFormularioValidado($validated),
                $operacao->empresa_id
            );

            \Log::info('Cliente cadastrado com sucesso', ['cliente_id' => $cliente->id]);

            return redirect()->route('clientes.show', $cliente->id)
                ->with('success', 'Cliente cadastrado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Erro de validação ao cadastrar cliente', ['errors' => $e->errors()]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            \Log::error('POST muito grande ao cadastrar cliente');
            return back()->with('error', 'Os arquivos enviados são muito grandes. O limite total do POST é de ' . ini_get('post_max_size') . '. Por favor, edite o php.ini e aumente post_max_size e upload_max_filesize. Veja: docs/SOLUCAO_ERRO_UPLOAD.md')->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao cadastrar cliente: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_size' => $request->header('Content-Length'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ]);
            return back()->with('error', 'Erro ao cadastrar cliente: ' . $e->getMessage() . ' (Verifique os logs para mais detalhes)')->withInput();
        }
    }

    /**
     * Mostrar detalhes do cliente
     * Super Admin: qualquer cliente. Demais: apenas se o cliente tiver vínculo com alguma das minhas operações.
     */
    public function show(int $id): View
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $operacoesIds = $isSuperAdmin ? [] : $user->getOperacoesIds();
        $empresaId = $user->empresa_id ?? null;

        $query = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->with('empresa', 'documentos');

        $cliente = $query->findOrFail($id);

        // Acesso: Super Admin ou cliente vinculado a pelo menos uma das minhas operações
        if (!$isSuperAdmin) {
            if (empty($operacoesIds)) {
                abort(403, 'Você não tem acesso a este cliente.');
            }
            $temAcesso = $cliente->operationClients()->whereIn('operacao_id', $operacoesIds)->exists();
            if (!$temAcesso) {
                abort(403, 'Você não tem acesso a este cliente.');
            }
        }

        // Filtrar documentos por empresa (quando houver)
        if (!$isSuperAdmin && $empresaId) {
            $cliente->load(['documentos' => function ($q) use ($empresaId) {
                $q->where(function ($query) use ($empresaId) {
                    $query->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
                })->orderByRaw('CASE WHEN empresa_id IS NULL THEN 1 ELSE 0 END');
            }]);
        }

        $clientePertenceEmpresaAtual = $empresaId && $cliente->empresa_id == $empresaId;

        if (!$clientePertenceEmpresaAtual && !$isSuperAdmin && $empresaId) {
            if (!$cliente->isVinculadoEmpresa($empresaId)) {
                $this->clienteService->vincularClienteEmpresa($cliente->id, $empresaId, $user->id);
            }
            $cliente->load(['dadosEmpresa' => fn ($q) => $q->where('empresa_id', $empresaId)]);
            $cliente->cachedDadosEmpresa = null;
        }

        // Carregar vínculos: Super Admin vê todos; demais apenas das minhas operações
        if ($isSuperAdmin) {
            $cliente->load([
                'operationClients.operacao.empresa',
                'emprestimos.operacao.empresa',
                'emprestimos.parcelas'
            ]);
        } else {
            $cliente->load([
                'operationClients' => fn ($q) => $q->whereIn('operacao_id', $operacoesIds)->with(['operacao', 'consultor']),
                'emprestimos' => fn ($q) => $q->whereIn('operacao_id', $operacoesIds)->with(['operacao', 'parcelas'])
            ]);
        }

        $hoje = Carbon::today();

        $emprestimosQuery = Emprestimo::query()
            ->where('cliente_id', $cliente->id)
            ->where('status', 'ativo');

        if ($isSuperAdmin) {
            $emprestimosQuery->with('operacao.empresa');
        } else {
            $emprestimosQuery->whereIn('operacao_id', $operacoesIds)->with('operacao');
        }

        $emprestimosAtivos = $emprestimosQuery->orderByDesc('data_inicio')->get();

        // Agrupar por operação
        $emprestimosPorOperacao = $emprestimosAtivos->groupBy('operacao_id')->map(function ($grupo) {
            return [
                'operacao' => $grupo->first()->operacao?->nome,
                'operacao_id' => $grupo->first()->operacao_id,
                'total' => $grupo->sum('valor_total'),
                'quantidade' => $grupo->count(),
            ];
        })->values();

        // Parcelas atrasadas (apenas empréstimos das minhas operações quando não for Super Admin)
        $parcelasQuery = Parcela::query()
            ->select('parcelas.*')
            ->whereHas('emprestimo', function ($q) use ($cliente, $operacoesIds, $isSuperAdmin) {
                $q->where('cliente_id', $cliente->id)->where('status', 'ativo');
                if (!$isSuperAdmin && !empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
            })
            ->where(function ($q) use ($hoje) {
                $q->where('parcelas.status', 'atrasada')
                  ->orWhere(function ($subQ) use ($hoje) {
                      $subQ->where('parcelas.status', 'pendente')
                           ->whereDate('parcelas.data_vencimento', '<', $hoje);
                  });
            })
            ->whereRaw('(parcelas.valor - COALESCE(parcelas.valor_pago, 0)) > 0');

        if ($isSuperAdmin) {
            $parcelasQuery->with(['emprestimo.operacao.empresa']);
        } else {
            $parcelasQuery->with(['emprestimo.operacao']);
        }

        $parcelasAtrasadas = $parcelasQuery->orderBy('parcelas.data_vencimento')->get();

        // Agrupar parcelas atrasadas por operação
        $atrasadasPorOperacao = $parcelasAtrasadas->groupBy('emprestimo.operacao_id')->map(function ($grupo) {
            $valorTotal = $grupo->sum(function ($p) {
                return max(0, (float) $p->valor - (float) $p->valor_pago);
            });
            return [
                'operacao' => $grupo->first()->emprestimo?->operacao?->nome,
                'operacao_id' => $grupo->first()->emprestimo?->operacao_id,
                'valor_total' => $valorTotal,
                'quantidade' => $grupo->count(),
            ];
        })->values();

        $totalAtrasado = $parcelasAtrasadas->sum(function ($p) {
            return max(0, (float) $p->valor - (float) $p->valor_pago);
        });

        // Totalizadores do cliente (empréstimos ativos no escopo)
        $idsAtivos = $emprestimosAtivos->pluck('id')->toArray();
        $totalEmprestado = $emprestimosAtivos->sum('valor_total');
        $totalAReceber = 0;
        $totalPago = 0;
        if (!empty($idsAtivos)) {
            $totalAReceber = (float) Parcela::whereIn('emprestimo_id', $idsAtivos)
                ->whereNotIn('status', ['paga', 'quitada_garantia'])
                ->selectRaw('SUM(valor - COALESCE(valor_pago, 0)) as total')
                ->value('total');
            $totalPago = (float) Parcela::whereIn('emprestimo_id', $idsAtivos)
                ->selectRaw('SUM(COALESCE(valor_pago, 0)) as total')
                ->value('total');
        }

        $statsCliente = [
            'total_emprestado' => $totalEmprestado,
            'total_a_receber' => $totalAReceber,
            'total_pago' => $totalPago,
        ];

        return view('clientes.show', compact(
            'cliente',
            'emprestimosPorOperacao',
            'atrasadasPorOperacao',
            'totalAtrasado',
            'statsCliente',
            'isSuperAdmin'
        ));
    }

    /**
     * Vincular cliente à empresa atual
     * Usado quando empresa clica em "Usar cadastro" de um cliente de outra empresa
     */
    public function vincular(int $id): RedirectResponse
    {
        try {
            $user = auth()->user();
            $empresaId = $user->empresa_id;
            
            if (!$empresaId) {
                return back()->with('error', 'Empresa não identificada.');
            }
            
            // Buscar cliente (pode ser de outra empresa)
            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->findOrFail($id);
            
            // Verificar se já é da empresa atual
            if ($cliente->empresa_id == $empresaId) {
                return redirect()->route('clientes.show', $cliente->id)
                    ->with('info', 'Este cliente já pertence à sua empresa.');
            }
            
            // Criar vínculo
            $this->clienteService->vincularClienteEmpresa($cliente->id, $empresaId, $user->id);
            
            return redirect()->route('clientes.show', $cliente->id)
                ->with('success', 'Cliente vinculado à sua empresa com sucesso! Agora ele aparecerá na sua lista de clientes.');
        } catch (\Exception $e) {
            \Log::error('Erro ao vincular cliente: ' . $e->getMessage());
            return back()->with('error', 'Erro ao vincular cliente: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar formulário de edição — sempre no contexto de uma operação (?operacao_id=).
     * Sem operação: redireciona se houver só uma opção; senão exibe escolha de operação.
     */
    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $operacoesIds = $isSuperAdmin ? [] : $user->getOperacoesIds();
        $empresaId = $user->empresa_id ?? null;

        $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with('documentos', 'empresa')
            ->findOrFail($id);

        if (! $isSuperAdmin) {
            if (empty($operacoesIds)) {
                abort(403, 'Você não tem acesso a este cliente.');
            }
            if (! $cliente->operationClients()->whereIn('operacao_id', $operacoesIds)->exists()) {
                abort(403, 'Você não tem acesso a este cliente.');
            }
        }

        if (! $request->filled('operacao_id')) {
            $opcoes = $this->operacoesVinculadasParaEdicaoFicha($cliente, $user);

            if ($opcoes->isEmpty()) {
                return redirect()->route('clientes.show', $id)
                    ->with('error', 'Este cliente não está vinculado a nenhuma operação à qual você tenha acesso. Vincule-o a uma operação antes de editar a ficha.');
            }

            if ($opcoes->count() === 1) {
                $only = $opcoes->first();

                return redirect()->route('clientes.edit', [
                    'id' => $id,
                    'operacao_id' => $only['operacao_id'],
                ]);
            }

            return view('clientes.edit-escolher-operacao', [
                'cliente' => $cliente,
                'opcoes' => $opcoes,
                'isSuperAdmin' => $isSuperAdmin,
            ]);
        }

        $oid = (int) $request->query('operacao_id');
        $temVinculo = $cliente->operationClients()->where('operacao_id', $oid)->exists();
        $pode = $isSuperAdmin || (! empty($operacoesIds) && in_array($oid, $operacoesIds, true));

        if (! $temVinculo || ! $pode) {
            return redirect()->route('clientes.edit', $id)
                ->with('error', 'Operação inválida, sem permissão ou sem vínculo com este cliente.');
        }

        $isEmpresaCriadora = $empresaId && $cliente->empresa_id == $empresaId;
        $dadosEmpresa = null;
        if (! $isEmpresaCriadora && ! $isSuperAdmin && $empresaId) {
            $dadosEmpresa = $cliente->dadosPorEmpresa($empresaId);
        }

        $operacaoParaFichaId = $oid;
        $operacaoContexto = Operacao::withoutGlobalScope(EmpresaScope::class)
            ->whereKey($oid)
            ->first(['id', 'nome', 'empresa_id']);
        $operacaoParaFichaNome = $operacaoContexto?->nome;
        $formDefaultsOperacao = $this->operacaoDadosClienteService->valoresFormularioParaOperacao(
            $cliente,
            $oid,
            $operacaoContexto?->empresa_id
        );

        $documentosOperacaoFicha = $this->documentosClienteNaOperacao($cliente->id, $oid);
        $documentosLegado = $this->documentosClienteLegadoVisivel(
            $cliente->id,
            $user->empresa_id ?? null,
            $isSuperAdmin
        );
        $mostrarDocumentosLegado = $this->temAlgumDocumentoNaLista($documentosLegado);

        return view('clientes.edit', compact(
            'cliente',
            'isSuperAdmin',
            'isEmpresaCriadora',
            'dadosEmpresa',
            'operacaoParaFichaId',
            'operacaoParaFichaNome',
            'formDefaultsOperacao',
            'documentosOperacaoFicha',
            'documentosLegado',
            'mostrarDocumentosLegado'
        ));
    }

    /**
     * Atualizar cliente
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'data_nascimento' => 'nullable|date',
            'responsavel_nome' => 'nullable|string|max:255',
            'responsavel_cpf' => 'nullable|string|min:11|max:14',
            'responsavel_rg' => 'nullable|string|max:20',
            'responsavel_cnh' => 'nullable|string|max:20',
            'responsavel_cargo' => 'nullable|string|max:100',
            'endereco' => 'nullable|string',
            'numero' => 'nullable|string|max:20',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'observacoes' => 'nullable|string',
            'documento_cliente' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
            'selfie_documento' => 'nullable|file|mimes:jpg,jpeg,png|max:5120', // 5MB
            'anexos.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB cada
            'operacao_para_ficha_id' => 'required|integer|exists:operacoes,id',
        ]);

        try {
            $user = auth()->user();
            $isSuperAdmin = $user->isSuperAdmin();
            $operacoesIds = $isSuperAdmin ? [] : $user->getOperacoesIds();

            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->findOrFail($id);

            if (! $isSuperAdmin) {
                if (empty($operacoesIds)) {
                    abort(403, 'Você não tem acesso a este cliente.');
                }
                if (! $cliente->operationClients()->whereIn('operacao_id', $operacoesIds)->exists()) {
                    abort(403, 'Você não tem acesso a este cliente.');
                }
            }

            $operacaoParaFichaId = (int) $request->input('operacao_para_ficha_id');

            if (! $isSuperAdmin && (empty($operacoesIds) || ! in_array($operacaoParaFichaId, $operacoesIds, true))) {
                return back()->with('error', 'Operação inválida ou sem permissão.')->withInput();
            }
            if (! $cliente->operationClients()->where('operacao_id', $operacaoParaFichaId)->exists()) {
                return back()->with('error', 'Cliente não está vinculado a esta operação.')->withInput();
            }

            // Cadastro editável por operação: não gravar nome/contato/endereço em `clientes` nem em `cliente_dados_empresa`
            // neste fluxo — apenas em `operacao_dados_clientes` (+ documentos com operacao_id).

            $documentos = [
                'documento_cliente' => $request->file('documento_cliente'),
                'selfie_documento' => $request->file('selfie_documento'),
                'anexos' => $request->file('anexos'),
            ];

            $hasNewDocs = $documentos['documento_cliente']
                || $documentos['selfie_documento']
                || ($documentos['anexos'] && count(array_filter($documentos['anexos'])));

            $documentosParaOperacao = $hasNewDocs ? $documentos : null;

            $operacaoFicha = Operacao::withoutGlobalScope(EmpresaScope::class)->findOrFail($operacaoParaFichaId);
            $this->operacaoDadosClienteService->salvarOuAtualizar(
                $cliente->id,
                $operacaoParaFichaId,
                $this->operacaoDadosClienteService->payloadFromFormularioValidado(array_merge($validated, [
                    'tipo_pessoa' => $cliente->tipo_pessoa,
                ])),
                $operacaoFicha->empresa_id
            );
            if ($documentosParaOperacao) {
                $this->clienteService->processarDocumentosParaOperacao(
                    $cliente->id,
                    $documentosParaOperacao,
                    $operacaoParaFichaId
                );
            }

            return redirect()->route('clientes.show', $cliente->id)
                ->with('success', 'Ficha da operação atualizada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar a ficha da operação: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Buscar cliente por CPF (AJAX) - Busca Global (Serasa Interno)
     * Busca em TODAS as empresas primeiro, depois verifica se é da empresa atual ou consulta cruzada
     */
    public function buscarPorCpf(Request $request)
    {
        try {
            $documento = $request->input('cpf') ?? $request->input('documento');
            
            if (!$documento) {
                return response()->json(['error' => 'Documento não informado'], 400);
            }

            // Busca GLOBAL primeiro (em todas as empresas) - "Serasa Interno"
            $cliente = Cliente::buscarPorDocumento($documento);
            
            if (!$cliente) {
                // Não encontrado em nenhuma empresa
                return response()->json(['existe' => false]);
            }

            // Verificar se o cliente pertence à empresa atual
            $empresaId = auth()->check() && !auth()->user()->isSuperAdmin() ? auth()->user()->empresa_id : null;
            $isClienteDaEmpresaAtual = $empresaId && $cliente->empresa_id == $empresaId;

        if ($isClienteDaEmpresaAtual) {
            // Cliente existe na empresa atual, retornar dados completos
            $hoje = Carbon::today();

            // Empréstimos ativos GLOBAIS (em todas as empresas) - "Serasa Interno"
            $emprestimosAtivos = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->with('operacao.empresa')
                ->where('cliente_id', $cliente->id)
                ->where('status', 'ativo')
                ->orderByDesc('data_inicio')
                ->get()
                ->map(function ($e) {
                    return [
                        'id' => $e->id,
                        'operacao_id' => $e->operacao_id,
                        'operacao' => $e->operacao?->nome,
                        'valor_total' => (float) $e->valor_total,
                        'data_inicio' => $e->data_inicio?->format('d/m/Y'),
                    ];
                });

            $operacoesAtivas = $emprestimosAtivos->pluck('operacao_id')->unique()->values();

            // Pendências GLOBAIS (em todas as empresas) - "Serasa Interno"
            // Buscar parcelas pendentes/atrasadas que vencem hoje ou já venceram E que têm valor em aberto
            $parcelasPendentes = Parcela::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->select('parcelas.*')
                ->with([
                    'emprestimo' => function ($q) {
                        $q->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                          ->with(['operacao.empresa']);
                    }
                ])
                ->whereHas('emprestimo', function ($q) use ($cliente) {
                    $q->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                      ->where('cliente_id', $cliente->id)
                      ->where('status', 'ativo'); // Apenas empréstimos ativos
                })
                ->where(function ($q) use ($hoje) {
                    // Apenas parcelas atrasadas (status atrasada OU pendente com vencimento passado, mas não as que vencem hoje)
                    $q->where('parcelas.status', 'atrasada')
                      ->orWhere(function ($subQ) use ($hoje) {
                          $subQ->where('parcelas.status', 'pendente')
                               ->whereDate('parcelas.data_vencimento', '<', $hoje); // < ao invés de <= para excluir as que vencem hoje
                      });
                })
                ->whereRaw('(parcelas.valor - COALESCE(parcelas.valor_pago, 0)) > 0') // Apenas se ainda tem valor em aberto
                ->orderBy('parcelas.data_vencimento')
                ->get();

            $parcelasPendentes = $parcelasPendentes->map(function ($p) {
                    $valorEmAberto = max(0, (float) $p->valor - (float) ($p->valor_pago ?? 0));
                    // Todas as parcelas já são atrasadas (a query já filtra apenas atrasadas)
                    return [
                        'parcela_id' => $p->id,
                        'emprestimo_id' => $p->emprestimo_id,
                        'operacao_id' => $p->emprestimo?->operacao_id,
                        'operacao' => $p->emprestimo?->operacao?->nome,
                        'empresa_id' => $p->emprestimo?->operacao?->empresa_id,
                        'empresa' => $p->emprestimo?->operacao?->empresa?->nome,
                        'numero' => $p->numero,
                        'vencimento' => $p->data_vencimento?->format('d/m/Y'),
                        'status' => 'atrasada',
                        'valor_em_aberto' => $valorEmAberto,
                    ];
                })
                ->filter(function ($p) {
                    return $p['valor_em_aberto'] > 0; // Filtrar apenas as que têm valor em aberto
                });

            $pendenciasPorOperacao = $parcelasPendentes
                ->groupBy(function ($item) {
                    // Agrupar por operacao_id, usando 'sem_operacao' se for null
                    return $item['operacao_id'] ?? 'sem_operacao';
                })
                ->map(function ($items) {
                    $primeiraParcela = $items->first();
                    $operacao = $primeiraParcela['operacao'] ?? 'Operação não identificada';
                    $empresaId = $primeiraParcela['empresa_id'] ?? null;
                    $empresa = $primeiraParcela['empresa'] ?? null;
                    // Todas as parcelas já são atrasadas (a query já filtra apenas atrasadas)
                    $atrasadas = $items;
                    
                    $totalEmAberto = (float) $items->sum('valor_em_aberto');

                    return [
                        'operacao' => $operacao,
                        'empresa_id' => $empresaId,
                        'empresa' => $empresa,
                        'total_em_aberto' => $totalEmAberto,
                        'atrasadas_qtd' => $atrasadas->count(),
                        'atrasadas_total' => (float) $atrasadas->sum('valor_em_aberto'),
                        'vence_hoje_qtd' => 0, // Sempre zero, pois não buscamos as que vencem hoje
                        'vence_hoje_total' => 0, // Sempre zero, pois não buscamos as que vencem hoje
                    ];
                })
                ->filter(function ($item) {
                    // Filtrar apenas itens com valor em aberto > 0
                    return $item['total_em_aberto'] > 0;
                })
                ->values();

            $ativosPorOperacao = $emprestimosAtivos
                ->groupBy('operacao_id')
                ->map(function ($items) {
                    $primeiroEmprestimo = $items->first();
                    $operacao = $primeiroEmprestimo['operacao'] ?? null;
                    $empresaId = $primeiroEmprestimo['empresa_id'] ?? null;
                    $empresa = $primeiroEmprestimo['empresa'] ?? null;
                    return [
                        'operacao' => $operacao,
                        'empresa_id' => $empresaId,
                        'empresa' => $empresa,
                        'total_ativo' => (float) $items->sum('valor_total'),
                        'qtd' => $items->count(),
                    ];
                })
                ->values();

            // Agrupar empréstimos por empresa (para mostrar histórico completo)
            $emprestimosPorEmpresa = $emprestimosAtivos->groupBy(function($e) {
                return $e->operacao?->empresa_id ?? 'sem_empresa';
            });
            
            $temAtivoEmOutraEmpresa = $emprestimosPorEmpresa->keys()->filter(function($empId) use ($empresaId) {
                return $empId != $empresaId && $empId != 'sem_empresa';
            })->isNotEmpty();
            
            // Calcular valor total de empréstimos ativos
            $valorTotalAtivos = $emprestimosAtivos->sum('valor_total');
            
            $ficha = [
                'emprestimos_ativos_total' => $emprestimosAtivos->count(),
                'emprestimos_ativos_valor_total' => (float) $valorTotalAtivos,
                'emprestimos_ativos_operacoes_distintas' => $operacoesAtivas->count(),
                'tem_ativo_em_outra_operacao' => $operacoesAtivas->count() > 1,
                'tem_ativo_em_outra_empresa' => $temAtivoEmOutraEmpresa,
                'ativos_por_operacao' => $ativosPorOperacao,
                'pendencias_total_em_aberto' => (float) $parcelasPendentes->sum('valor_em_aberto'),
                'pendencias_por_operacao' => $pendenciasPorOperacao,
                'tem_pendencias' => $parcelasPendentes->sum('valor_em_aberto') > 0,
            ];

            // Carregar relacionamentos antes de serializar
            $cliente->load(['operationClients.operacao']);
            
            // Preparar dados do cliente para JSON (evitar problemas de serialização)
            $clienteData = [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => $cliente->documento,
                'documento_formatado' => $cliente->documento_formatado,
                'tipo_pessoa' => $cliente->tipo_pessoa,
                'telefone' => $cliente->telefone,
                'email' => $cliente->email,
                'operation_clients' => $cliente->operationClients->map(function ($oc) {
                    return [
                        'id' => $oc->id,
                        'operacao_id' => $oc->operacao_id,
                        'operacao' => $oc->operacao ? [
                            'id' => $oc->operacao->id,
                            'nome' => $oc->operacao->nome,
                        ] : null,
                        'limite_credito' => (float) $oc->limite_credito,
                        'status' => $oc->status,
                    ];
                }),
            ];

            return response()->json([
                'existe' => true,
                'cliente' => $clienteData,
                'ficha' => $ficha,
                'consulta_cruzada' => false, // Cliente da própria empresa
            ]);
        } else {
            // Cliente encontrado em OUTRA empresa - mostrar consulta cruzada
            $consultaCruzada = $this->consultaService->consultarCruzada($documento, $empresaId);

            if ($consultaCruzada) {
            // Cliente existe em outra empresa, retornar consulta cruzada
            return response()->json([
                'existe' => true,
                'cliente' => [
                    'id' => $consultaCruzada['cliente_id'],
                    'nome' => $consultaCruzada['cliente_nome'],
                    'documento' => $consultaCruzada['cliente_documento'] ?? $consultaCruzada['cliente_cpf'] ?? null,
                    'tipo_pessoa' => $consultaCruzada['cliente_tipo_pessoa'] ?? 'fisica',
                ],
                'consulta_cruzada' => true,
                'ficha' => [
                    'emprestimos_ativos_total' => collect($consultaCruzada['empresas_com_historico'])->sum('emprestimos_ativos.quantidade'),
                    'emprestimos_ativos_operacoes_distintas' => count($consultaCruzada['empresas_com_historico']),
                    'tem_ativo_em_outra_operacao' => true,
                    'ativos_por_operacao' => collect($consultaCruzada['empresas_com_historico'])->map(function ($item) {
                        return [
                            'operacao' => $item['empresa_nome'],
                            'total_ativo' => $item['emprestimos_ativos']['valor_total'],
                            'qtd' => $item['emprestimos_ativos']['quantidade'],
                        ];
                    })->values(),
                    'pendencias_total_em_aberto' => collect($consultaCruzada['empresas_com_historico'])->sum(function ($item) {
                        // Usar apenas parcelas_pendentes_total (já inclui atrasadas + vence hoje)
                        return (float) ($item['parcelas_pendentes_total']['valor_total'] ?? 0);
                    }),
                    'pendencias_por_operacao' => collect($consultaCruzada['empresas_com_historico'])->map(function ($item) {
                        // Usar apenas parcelas_pendentes_total (já inclui atrasadas + vence hoje)
                        $totalEmAberto = (float) ($item['parcelas_pendentes_total']['valor_total'] ?? 0);
                        $atrasadasQtd = (int) ($item['parcelas_atrasadas']['quantidade'] ?? 0);
                        $atrasadasTotal = (float) ($item['parcelas_atrasadas']['valor_total'] ?? 0);
                        $venceHojeQtd = (int) ($item['parcelas_vence_hoje']['quantidade'] ?? 0);
                        $venceHojeTotal = (float) ($item['parcelas_vence_hoje']['valor_total'] ?? 0);
                        
                        return [
                            'operacao' => $item['empresa_nome'],
                            'empresa' => $item['empresa_nome'],
                            'total_em_aberto' => $totalEmAberto,
                            'atrasadas_qtd' => $atrasadasQtd,
                            'atrasadas_total' => $atrasadasTotal,
                            'vence_hoje_qtd' => $venceHojeQtd,
                            'vence_hoje_total' => $venceHojeTotal,
                        ];
                    })->filter(function ($item) {
                        return $item['total_em_aberto'] > 0;
                    })->values(),
                ],
                'empresas_com_historico' => $consultaCruzada['empresas_com_historico'],
            ]);
            } else {
                // Cliente existe mas sem histórico (pode ter pendências mesmo sem empréstimos ativos)
                // Buscar pendências diretamente
                $hoje = Carbon::today();
                $parcelasPendentes = Parcela::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                    ->with([
                        'emprestimo' => function ($q) {
                            $q->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                              ->with(['operacao.empresa']);
                        }
                    ])
                    ->whereHas('emprestimo', function ($q) use ($cliente) {
                        $q->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                          ->where('cliente_id', $cliente->id)
                          ->where('status', 'ativo'); // Apenas empréstimos ativos
                    })
                    ->where(function ($q) use ($hoje) {
                        // Apenas parcelas atrasadas (status atrasada OU pendente com vencimento passado, mas não as que vencem hoje)
                        $q->where('parcelas.status', 'atrasada')
                          ->orWhere(function ($subQ) use ($hoje) {
                              $subQ->where('parcelas.status', 'pendente')
                                   ->whereDate('parcelas.data_vencimento', '<', $hoje); // < ao invés de <= para excluir as que vencem hoje
                          });
                    })
                    ->whereRaw('(parcelas.valor - COALESCE(parcelas.valor_pago, 0)) > 0')
                    ->get();

                $parcelasPendentes = $parcelasPendentes->map(function ($p) {
                        $valorEmAberto = max(0, (float) $p->valor - (float) ($p->valor_pago ?? 0));
                        // Todas as parcelas já são atrasadas (a query já filtra apenas atrasadas)
                        return [
                            'operacao_id' => $p->emprestimo?->operacao_id,
                            'operacao' => $p->emprestimo?->operacao?->nome,
                            'empresa_id' => $p->emprestimo?->operacao?->empresa_id,
                            'empresa' => $p->emprestimo?->operacao?->empresa?->nome,
                            'status' => 'atrasada',
                            'valor_em_aberto' => $valorEmAberto,
                        ];
                    })
                    ->filter(function ($p) {
                        return $p['valor_em_aberto'] > 0;
                    });

                $pendenciasPorOperacao = $parcelasPendentes
                    ->groupBy(function ($item) {
                        return $item['operacao_id'] ?? 'sem_operacao';
                    })
                    ->map(function ($items) {
                        $primeiraParcela = $items->first();
                        // Todas as parcelas já são atrasadas (a query já filtra apenas atrasadas)
                        $atrasadas = $items;
                        return [
                            'operacao' => $primeiraParcela['operacao'] ?? 'Operação não identificada',
                            'empresa' => $primeiraParcela['empresa'] ?? null,
                            'total_em_aberto' => (float) $items->sum('valor_em_aberto'),
                            'atrasadas_qtd' => $atrasadas->count(),
                            'atrasadas_total' => (float) $atrasadas->sum('valor_em_aberto'),
                            'vence_hoje_qtd' => 0, // Sempre zero, pois não buscamos as que vencem hoje
                            'vence_hoje_total' => 0, // Sempre zero, pois não buscamos as que vencem hoje
                        ];
                    })
                    ->filter(function ($item) {
                        return $item['total_em_aberto'] > 0;
                    })
                    ->values();

                return response()->json([
                    'existe' => true,
                    'cliente' => [
                        'id' => $cliente->id,
                        'nome' => $cliente->nome,
                        'documento' => $cliente->documento_formatado,
                        'tipo_pessoa' => $cliente->tipo_pessoa,
                    ],
                    'consulta_cruzada' => true,
                    'ficha' => [
                        'emprestimos_ativos_total' => 0,
                        'emprestimos_ativos_operacoes_distintas' => 0,
                        'tem_ativo_em_outra_operacao' => false,
                        'ativos_por_operacao' => [],
                        'pendencias_total_em_aberto' => (float) $parcelasPendentes->sum('valor_em_aberto'),
                        'pendencias_por_operacao' => $pendenciasPorOperacao,
                    ],
                    'empresas_com_historico' => [],
                ]);
            }
        }
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar cliente por documento', [
                'documento' => $request->input('cpf') ?? $request->input('documento'),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Erro ao verificar documento: ' . $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Buscar clientes para Select2 (AJAX)
     * Retorna lista de clientes formatada para Select2
     */
    public function buscar(Request $request)
    {
        $termo = $request->input('q', '');
        
        if (strlen($termo) < 2) {
            return response()->json(['results' => []]);
        }

        $query = Cliente::query();

        // Remover formatação do CPF se houver
        $documentoLimpo = preg_replace('/[^0-9]/', '', $termo);
        
        // Buscar por documento ou nome
        if (strlen($documentoLimpo) >= 3) {
            $query->where(function($q) use ($documentoLimpo, $termo) {
                $q->where('documento', 'like', "%{$documentoLimpo}%")
                  ->orWhere('nome', 'like', "%{$termo}%");
            });
        } else {
            $query->where('nome', 'like', "%{$termo}%");
        }

        $clientes = $query->orderBy('nome')
            ->limit(20)
            ->get();

        $results = $clientes->map(function($cliente) {
            return [
                'id' => $cliente->id,
                'text' => $cliente->nome . ' - ' . $cliente->documento_formatado
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Filtro de nome na listagem/export: com operação selecionada, busca também em `operacao_dados_clientes` (nome, telefone, e-mail).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Models\Cliente>  $query
     */
    private function aplicarFiltroNomeListagemClientes($query, string $nome, ?int $operacaoIdFiltro): void
    {
        $term = '%'.$nome.'%';
        if ($operacaoIdFiltro) {
            $query->where(function ($q) use ($term, $operacaoIdFiltro) {
                $q->where('nome', 'like', $term)
                    ->orWhereHas('operacaoDadosClientes', function ($sub) use ($operacaoIdFiltro, $term) {
                        $sub->where('operacao_id', $operacaoIdFiltro)
                            ->where(function ($inner) use ($term) {
                                $inner->where('nome', 'like', $term)
                                    ->orWhere('telefone', 'like', $term)
                                    ->orWhere('email', 'like', $term);
                            });
                    });
            });
        } else {
            $query->where('nome', 'like', $term);
        }
    }

    /**
     * Documentos salvos com `operacao_id` = contexto da ficha em edição.
     *
     * @return array{documento: ClientDocument|null, selfie: ClientDocument|null, anexos: Collection<int, ClientDocument>}
     */
    private function documentosClienteNaOperacao(int $clienteId, int $operacaoId): array
    {
        $um = fn (string $categoria) => ClientDocument::query()
            ->where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->where('categoria', $categoria)
            ->orderByDesc('id')
            ->first();

        return [
            'documento' => $um('documento'),
            'selfie' => $um('selfie'),
            'anexos' => ClientDocument::query()
                ->where('cliente_id', $clienteId)
                ->where('operacao_id', $operacaoId)
                ->where('categoria', 'anexo')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    /**
     * Documentos sem `operacao_id` (legado), com o mesmo critério de visibilidade por empresa que a listagem do cliente.
     *
     * @return array{documento: ClientDocument|null, selfie: ClientDocument|null, anexos: Collection<int, ClientDocument>}
     */
    private function documentosClienteLegadoVisivel(int $clienteId, ?int $empresaIdUsuario, bool $isSuperAdmin): array
    {
        $base = function () use ($clienteId, $empresaIdUsuario, $isSuperAdmin) {
            $q = ClientDocument::query()
                ->where('cliente_id', $clienteId)
                ->whereNull('operacao_id');

            if (! $isSuperAdmin && $empresaIdUsuario) {
                $q->where(function ($w) use ($empresaIdUsuario) {
                    $w->whereNull('empresa_id')->orWhere('empresa_id', $empresaIdUsuario);
                });
            }

            return $q;
        };

        $um = fn (string $categoria) => $base()
            ->where('categoria', $categoria)
            ->orderByDesc('id')
            ->first();

        return [
            'documento' => $um('documento'),
            'selfie' => $um('selfie'),
            'anexos' => $base()
                ->where('categoria', 'anexo')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    /**
     * @param  array{documento: mixed, selfie: mixed, anexos: \Illuminate\Support\Collection}  $docs
     */
    private function temAlgumDocumentoNaLista(array $docs): bool
    {
        return ($docs['documento'] ?? null) !== null
            || ($docs['selfie'] ?? null) !== null
            || ($docs['anexos'] ?? collect())->isNotEmpty();
    }

    /**
     * Operações vinculadas ao cliente que o usuário pode usar para editar a ficha (por operação).
     *
     * @return Collection<int, array{operacao_id: int, nome: string, empresa_nome: string|null}>
     */
    private function operacoesVinculadasParaEdicaoFicha(Cliente $cliente, \App\Models\User $user): Collection
    {
        $isSuperAdmin = $user->isSuperAdmin();
        $operacoesIds = $user->getOperacoesIds();

        $q = $cliente->operationClients()->with([
            'operacao' => fn ($oq) => $oq->withoutGlobalScope(EmpresaScope::class)->with('empresa'),
        ]);

        if (! $isSuperAdmin) {
            if (empty($operacoesIds)) {
                return collect();
            }
            $q->whereIn('operacao_id', $operacoesIds);
        }

        return $q->get()->map(function (OperationClient $oc) {
            $nome = $oc->operacao?->nome;
            if ($nome === null || $nome === '') {
                return null;
            }

            return [
                'operacao_id' => (int) $oc->operacao_id,
                'nome' => $nome,
                'empresa_nome' => $oc->operacao?->empresa?->nome,
            ];
        })->filter()->values();
    }

    /**
     * Mesma base que {@see CadastroClienteController::store} para ClienteDadosEmpresa
     * quando o documento já existe e a operação é de outra “visão” de empresa.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function montarDadosEmpresaOverrideFromFormularioInterno(array $validated): array
    {
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
            $dadosEmpresa['responsavel_cpf'] = ! empty($validated['responsavel_cpf'])
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

        return $dadosEmpresa;
    }
}
