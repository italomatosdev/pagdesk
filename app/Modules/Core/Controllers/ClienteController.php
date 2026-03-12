<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Services\ClienteService;
use App\Modules\Core\Services\ClienteConsultaService;
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

    public function __construct(ClienteService $clienteService, ClienteConsultaService $consultaService)
    {
        $this->middleware('auth');
        $this->clienteService = $clienteService;
        $this->consultaService = $consultaService;
    }

    /**
     * Listar clientes
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $empresaId = $user->empresa_id ?? null;
        
        // O EmpresaScope já filtra automaticamente incluindo clientes vinculados
        $query = Cliente::query();

        // Super Admin vê todos os clientes (o EmpresaScope já não aplica filtro para Super Admin)
        // Mas vamos carregar o relacionamento empresa para exibir na view
        if ($isSuperAdmin) {
            $query->with('empresa');
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

        // Filtro por nome
        if ($request->filled('nome')) {
            $query->where('nome', 'like', "%{$request->nome}%");
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

        $clientes = $query->with([
                'operationClients' => function ($q) use ($empresaId, $isSuperAdmin) {
                    // Filtrar apenas operações da empresa atual (a menos que seja Super Admin)
                    if (!$isSuperAdmin && $empresaId) {
                        $q->whereHas('operacao', function ($subQ) use ($empresaId) {
                            $subQ->where('empresa_id', $empresaId);
                        });
                    }
                    $q->with('operacao');
                },
                'empresasVinculadas' => function ($q) use ($empresaId) {
                    if ($empresaId) {
                        $q->where('empresa_id', $empresaId);
                    }
                }
            ])
            ->orderBy('nome')
            ->paginate(15);

        return view('clientes.index', compact('clientes', 'isSuperAdmin', 'stats'));
    }

    /**
     * Exportar listagem de clientes em CSV (abre no Excel).
     * Respeita os mesmos filtros da listagem (documento, nome).
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $empresaId = $user->empresa_id ?? null;

        $query = Cliente::query();

        if ($isSuperAdmin) {
            $query->with('empresa');
        }

        if ($request->filled('documento')) {
            $documento = preg_replace('/[^0-9]/', '', $request->documento);
            $query->where('documento', 'like', "%{$documento}%");
        }
        if ($request->filled('cpf')) {
            $documento = preg_replace('/[^0-9]/', '', $request->cpf);
            $query->where('documento', 'like', "%{$documento}%");
        }
        if ($request->filled('nome')) {
            $query->where('nome', 'like', "%{$request->nome}%");
        }

        $clientes = $query->orderBy('nome')->get();

        $filename = 'clientes_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($clientes, $isSuperAdmin) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 para Excel

            $headers = ['Documento', 'Nome', 'Tipo', 'Telefone', 'Email', 'Cidade', 'Estado'];
            if ($isSuperAdmin) {
                $headers[] = 'Empresa';
            }
            fputcsv($out, $headers, ';');

            foreach ($clientes as $c) {
                $tipo = $c->tipo_pessoa === 'fisica' ? 'PF' : 'PJ';
                $row = [
                    $c->documento,
                    $c->nome,
                    $tipo,
                    $c->telefone ?? '',
                    $c->email ?? '',
                    $c->cidade ?? '',
                    $c->estado ?? '',
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
     * Mostrar formulário de criação
     */
    public function create(): View
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->where('ativo', true)->with('documentosObrigatorios')->get();
        } elseif ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->with('documentosObrigatorios')->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
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
        $request->validate([
            'operacao_id' => [
                'required',
                'exists:operacoes,id',
                function ($attr, $value, $fail) {
                    if ($value && !auth()->user()->temAcessoOperacao($value)) {
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
            $dadosCliente = $validated;
            $documentos = [
                'documento_cliente' => $documentoFile,
                'selfie_documento' => $selfieFile,
                'anexos' => $request->file('anexos'),
            ];
            unset($dadosCliente['documento_cliente'], $dadosCliente['selfie_documento'], $dadosCliente['anexos']);
            $dadosCliente['documentos'] = $documentos;

            $cliente = $this->clienteService->cadastrar($dadosCliente);

            $this->clienteService->vincularOperacao(
                $cliente->id,
                (int) $request->operacao_id,
                0,
                null,
                null
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
     */
    public function show(int $id): View
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        
        // Para Super Admin ou quando o cliente não pertence à empresa atual (consulta cruzada),
        // precisamos buscar sem o escopo de empresa
        $empresaId = $user->empresa_id ?? null;
        $query = Cliente::with([
            'documentos' => function ($q) use ($empresaId, $isSuperAdmin) {
                // Se não for Super Admin e houver empresa, filtrar documentos
                if (!$isSuperAdmin && $empresaId) {
                    $q->where(function ($query) use ($empresaId) {
                        $query->whereNull('empresa_id')
                              ->orWhere('empresa_id', $empresaId);
                    })
                    ->orderByRaw('CASE WHEN empresa_id IS NULL THEN 1 ELSE 0 END'); // Específicos primeiro
                }
            },
            'empresa'
        ]);
        
        // Se não for Super Admin, primeiro tenta buscar normalmente (com escopo)
        // Se não encontrar, busca sem escopo (cliente de outra empresa)
        if (!$isSuperAdmin) {
            $cliente = $query->find($id);
            if (!$cliente) {
                // Cliente não encontrado na empresa atual, busca globalmente (apenas dados básicos)
                $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                    ->with([
                        'documentos' => function ($q) use ($empresaId) {
                            // Filtrar documentos: originais + específicos da empresa atual
                            if ($empresaId) {
                                $q->where(function ($query) use ($empresaId) {
                                    $query->whereNull('empresa_id')
                                          ->orWhere('empresa_id', $empresaId);
                                })
                                ->orderByRaw('CASE WHEN empresa_id IS NULL THEN 1 ELSE 0 END'); // Específicos primeiro
                            }
                        },
                        'empresa'
                    ])
                    ->findOrFail($id);
            }
        } else {
            // Super Admin pode ver qualquer cliente
            $cliente = $query->findOrFail($id);
        }

        // Verificar se o cliente pertence à empresa atual
        $empresaId = $user->empresa_id ?? null;
        $clientePertenceEmpresaAtual = $empresaId && $cliente->empresa_id == $empresaId;
        
        // Se o cliente não pertence à empresa atual e não é Super Admin, criar vínculo automaticamente
        if (!$clientePertenceEmpresaAtual && !$isSuperAdmin && $empresaId) {
            // Verificar se já está vinculado
            if (!$cliente->isVinculadoEmpresa($empresaId)) {
                // Criar vínculo automaticamente
                $this->clienteService->vincularClienteEmpresa($cliente->id, $empresaId, $user->id);
            }
            
            // Carregar dados específicos da empresa
            $cliente->load(['dadosEmpresa' => function ($query) use ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }]);
            // Limpar cache para forçar recarregamento
            $cliente->cachedDadosEmpresa = null;
        }
        
        // Carregar vínculos com operações e empréstimos APENAS da empresa atual
        // (não carregar histórico de outras empresas)
        if ($clientePertenceEmpresaAtual || $isSuperAdmin) {
            // Cliente da empresa atual ou Super Admin: carregar todos os relacionamentos
            $cliente->load([
                'operationClients.operacao',
                'operationClients.consultor',
                'emprestimos.operacao'
            ]);
            
            if ($isSuperAdmin) {
                $cliente->load([
                    'operationClients.operacao.empresa',
                    'emprestimos.operacao.empresa'
                ]);
            }
        } else {
            // Cliente de outra empresa: carregar apenas vínculos e empréstimos da empresa atual
            $cliente->load([
                'operationClients' => function ($query) use ($empresaId) {
                    $query->whereHas('operacao', function ($q) use ($empresaId) {
                        $q->where('empresa_id', $empresaId);
                    })
                    ->with(['operacao', 'consultor']);
                },
                'emprestimos' => function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId)
                          ->with('operacao');
                }
            ]);
        }

        $hoje = Carbon::today();

        // Empréstimos ativos - APENAS da empresa atual (não mostrar histórico de outras empresas)
        $emprestimosQuery = Emprestimo::query()
            ->where('cliente_id', $cliente->id)
            ->where('status', 'ativo');
        
        // Para Super Admin, carregar empresa das operações
        if ($isSuperAdmin) {
            $emprestimosQuery->with('operacao.empresa');
        } else {
            $emprestimosQuery->with('operacao');
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

        // Parcelas atrasadas (status 'atrasada' ou 'pendente' com vencimento passado)
        // APENAS da empresa atual (não mostrar histórico de outras empresas)
        $parcelasQuery = Parcela::query()
            ->select('parcelas.*')
            ->whereHas('emprestimo', function ($q) use ($cliente) {
                $q->where('cliente_id', $cliente->id)
                  ->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->where(function ($q) use ($hoje) {
                $q->where('parcelas.status', 'atrasada')
                  ->orWhere(function ($subQ) use ($hoje) {
                      $subQ->where('parcelas.status', 'pendente')
                           ->whereDate('parcelas.data_vencimento', '<', $hoje);
                  });
            })
            ->whereRaw('(parcelas.valor - COALESCE(parcelas.valor_pago, 0)) > 0'); // Apenas se ainda tem valor em aberto
        
        // Para Super Admin, carregar empresa das operações
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

        return view('clientes.show', compact(
            'cliente',
            'emprestimosPorOperacao',
            'atrasadasPorOperacao',
            'totalAtrasado',
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
     * Mostrar formulário de edição
     */
    public function edit(int $id): View
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $empresaId = $user->empresa_id ?? null;
        
        // Buscar cliente (pode ser de outra empresa)
        $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with('documentos', 'empresa')
            ->findOrFail($id);
        
        // Verificar se a empresa atual é a criadora
        $isEmpresaCriadora = $empresaId && $cliente->empresa_id == $empresaId;
        
        // Carregar dados específicos da empresa se existirem
        $dadosEmpresa = null;
        if (!$isEmpresaCriadora && !$isSuperAdmin && $empresaId) {
            $dadosEmpresa = $cliente->dadosPorEmpresa($empresaId);
        }
        
        return view('clientes.edit', compact('cliente', 'isSuperAdmin', 'isEmpresaCriadora', 'dadosEmpresa'));
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
        ]);

        try {
            $user = auth()->user();
            $empresaId = $user->empresa_id ?? null;
            
            // Buscar cliente (pode ser de outra empresa)
            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->findOrFail($id);
            
            // Verificar se a empresa atual é a criadora do cliente
            $isEmpresaCriadora = $empresaId && $cliente->empresa_id == $empresaId;
            
            // Separar dados do cliente dos documentos
            $dadosCliente = $validated;
            $documentos = [
                'documento_cliente' => $request->file('documento_cliente'),
                'selfie_documento' => $request->file('selfie_documento'),
                'anexos' => $request->file('anexos'),
            ];

            // Remover campos de arquivo dos dados do cliente
            unset($dadosCliente['documento_cliente'], $dadosCliente['selfie_documento'], $dadosCliente['anexos']);

            // Adicionar documentos aos dados (apenas se houver novos uploads)
            if ($documentos['documento_cliente'] || $documentos['selfie_documento'] || ($documentos['anexos'] && count(array_filter($documentos['anexos'])))) {
                $dadosCliente['documentos'] = $documentos;
            }

            // Se for a empresa criadora ou Super Admin, atualiza diretamente
            // Caso contrário, salva no override
            if ($isEmpresaCriadora || $user->isSuperAdmin()) {
                $cliente = $this->clienteService->atualizar($id, $dadosCliente);
            } else {
                $cliente = $this->clienteService->atualizarDadosEmpresa($id, $empresaId, $dadosCliente);
            }
            
            return redirect()->route('clientes.show', $cliente->id)
                ->with('success', 'Cliente atualizado com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar cliente: ' . $e->getMessage())->withInput();
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
}
