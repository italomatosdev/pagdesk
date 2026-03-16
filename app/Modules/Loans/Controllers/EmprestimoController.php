<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Services\ClienteService;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Models\SolicitacaoEmprestimoRetroativo;
use App\Modules\Loans\Services\EmprestimoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmprestimoController extends Controller
{
    protected EmprestimoService $emprestimoService;
    protected ClienteService $clienteService;

    public function __construct(
        EmprestimoService $emprestimoService,
        ClienteService $clienteService
    ) {
        $this->middleware('auth');
        $this->emprestimoService = $emprestimoService;
        $this->clienteService = $clienteService;
    }

    /**
     * Listar empréstimos
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = Emprestimo::with(['cliente', 'operacao', 'consultor', 'parcelas']);

        // Aplicar filtro de operações (Super Admin vê tudo; admin/gestor/consultor só suas operações)
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Consultor (sem ser gestor/admin em nenhuma operação): respeitar visibilidade por operação
        $ehApenasConsultor = empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']));
        if ($ehApenasConsultor) {
            $query->where(function ($q) use ($user) {
                $q->where('consultor_id', $user->id)
                    ->orWhereHas('operacao', fn ($op) => $op->where('consultores_veem_apenas_proprios_emprestimos', false));
            });
        }

        // Filtros
        if ($request->filled('operacao_id')) {
            // Validar se o usuário tem acesso a essa operação
            if ($user->temAcessoOperacao($request->operacao_id)) {
                $query->where('operacao_id', $request->operacao_id);
            }
        }

        $statuses = array_filter((array) $request->input('status', []));
        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('tipo')) {
            if ($request->tipo === 'outros') {
                $query->whereNotIn('tipo', ['dinheiro', 'price', 'empenho', 'troca_cheque', 'crediario']);
            } else {
                $query->where('tipo', $request->tipo);
            }
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('cliente_nome')) {
            $termo = trim((string) $request->cliente_nome);
            if ($termo !== '') {
                $digits = preg_replace('/[^0-9]/', '', $termo);
                $query->whereHas('cliente', function ($q) use ($termo, $digits) {
                    $q->where('nome', 'like', '%' . $termo . '%');
                    if ($digits !== '') {
                        $q->orWhere('documento', 'like', '%' . $digits . '%');
                    }
                });
            }
        }

        // Filtro: próximo vencimento (data de/até = primeira parcela não paga)
        $quitados = ['paga', 'quitada_garantia'];
        $proxVencDe = $request->filled('proximo_vencimento_de') ? $request->proximo_vencimento_de : null;
        $proxVencAte = $request->filled('proximo_vencimento_ate') ? $request->proximo_vencimento_ate : null;
        if ($proxVencDe !== null || $proxVencAte !== null) {
            $sub = Parcela::selectRaw('emprestimo_id')
                ->whereNotIn('status', $quitados)
                ->groupBy('emprestimo_id');
            if ($proxVencDe !== null && $proxVencAte !== null) {
                $sub->havingRaw('MIN(data_vencimento) BETWEEN ? AND ?', [$proxVencDe, $proxVencAte]);
            } elseif ($proxVencDe !== null) {
                $sub->havingRaw('MIN(data_vencimento) >= ?', [$proxVencDe]);
            } else {
                $sub->havingRaw('MIN(data_vencimento) <= ?', [$proxVencAte]);
            }
            $query->whereIn('id', $sub);
        }

        // Filtro: apenas com parcelas atrasadas
        if ($request->boolean('apenas_atrasadas')) {
            $query->whereHas('parcelas', fn ($q) => $q->where('status', 'atrasada'));
        }

        // Contadores (respeitam os mesmos filtros da listagem)
        $stats = [
            'total' => (clone $query)->count(),
            'valor_total_emprestado' => (clone $query)->sum('valor_total'),
            'ativos' => (clone $query)->where('status', 'ativo')->count(),
            'pendentes' => (clone $query)->where('status', 'pendente')->count(),
            'com_parcela_atrasada' => (clone $query)->whereHas('parcelas', fn ($q) => $q->where('status', 'atrasada'))->count(),
            'novos_mes' => (clone $query)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
        // Valor a receber (parcelas não pagas dos empréstimos filtrados)
        $stats['valor_a_receber'] = Parcela::whereIn('emprestimo_id', (clone $query)->select('id'))
            ->whereNotIn('status', ['paga', 'quitada_garantia'])
            ->sum(DB::raw('valor - COALESCE(valor_pago, 0)'));

        $emprestimos = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Operações disponíveis no filtro (Super Admin: todas; demais: apenas as do usuário)
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        return view('emprestimos.index', compact('emprestimos', 'operacoes', 'stats'));
    }

    /**
     * Exportar listagem de empréstimos em CSV (abre no Excel).
     * Respeita os mesmos filtros da listagem (operacao_id, status, tipo, cliente_id).
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $query = Emprestimo::with(['cliente', 'operacao', 'consultor']);

        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $ehApenasConsultor = empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']));
        if ($ehApenasConsultor) {
            $query->where(function ($q) use ($user) {
                $q->where('consultor_id', $user->id)
                    ->orWhereHas('operacao', fn ($op) => $op->where('consultores_veem_apenas_proprios_emprestimos', false));
            });
        }

        if ($request->filled('operacao_id') && $user->temAcessoOperacao($request->operacao_id)) {
            $query->where('operacao_id', $request->operacao_id);
        }
        $statusesExport = array_filter((array) $request->input('status', []));
        if (!empty($statusesExport)) {
            $query->whereIn('status', $statusesExport);
        }
        if ($request->filled('tipo')) {
            if ($request->tipo === 'outros') {
                $query->whereNotIn('tipo', ['dinheiro', 'price', 'empenho', 'troca_cheque', 'crediario']);
            } else {
                $query->where('tipo', $request->tipo);
            }
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('cliente_nome')) {
            $termo = trim((string) $request->cliente_nome);
            if ($termo !== '') {
                $digits = preg_replace('/[^0-9]/', '', $termo);
                $query->whereHas('cliente', function ($q) use ($termo, $digits) {
                    $q->where('nome', 'like', '%' . $termo . '%');
                    if ($digits !== '') {
                        $q->orWhere('documento', 'like', '%' . $digits . '%');
                    }
                });
            }
        }
        $proxVencDeExport = $request->filled('proximo_vencimento_de') ? $request->proximo_vencimento_de : null;
        $proxVencAteExport = $request->filled('proximo_vencimento_ate') ? $request->proximo_vencimento_ate : null;
        if ($proxVencDeExport !== null || $proxVencAteExport !== null) {
            $subExport = Parcela::selectRaw('emprestimo_id')
                ->whereNotIn('status', ['paga', 'quitada_garantia'])
                ->groupBy('emprestimo_id');
            if ($proxVencDeExport !== null && $proxVencAteExport !== null) {
                $subExport->havingRaw('MIN(data_vencimento) BETWEEN ? AND ?', [$proxVencDeExport, $proxVencAteExport]);
            } elseif ($proxVencDeExport !== null) {
                $subExport->havingRaw('MIN(data_vencimento) >= ?', [$proxVencDeExport]);
            } else {
                $subExport->havingRaw('MIN(data_vencimento) <= ?', [$proxVencAteExport]);
            }
            $query->whereIn('id', $subExport);
        }
        // Filtro: apenas com parcelas atrasadas
        if ($request->boolean('apenas_atrasadas')) {
            $query->whereHas('parcelas', fn ($q) => $q->where('status', 'atrasada'));
        }

        $emprestimos = $query->orderBy('created_at', 'desc')->get();

        $filename = 'emprestimos_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($emprestimos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['ID', 'Cliente', 'Operação', 'Valor', 'Status', 'Tipo', 'Data início', 'Consultor'], ';');

            foreach ($emprestimos as $e) {
                fputcsv($out, [
                    $e->id,
                    $e->cliente?->nome ?? '',
                    $e->operacao?->nome ?? '',
                    number_format((float) $e->valor_total, 2, ',', '.'),
                    $e->status,
                    $e->tipo ?? '',
                    $e->data_inicio?->format('d/m/Y') ?? '',
                    $e->consultor?->name ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Mostrar formulário de criação
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        
        // Super Admin não pode criar empréstimos
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar empréstimos.');
        }
        
        try {
            
            // Operações disponíveis (Super Admin: todas; admin/gestor: apenas as suas)
            if ($user->isSuperAdmin()) {
                $operacoes = Operacao::where('ativo', true)->get();
            } else {
                $operacoesIds = $user->getOperacoesIds();
                $operacoes = !empty($operacoesIds)
                    ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                    : collect([]);
            }

            // Consultores por operação (gestor/admin escolhem o consultor; opção "Nome (Você)" ao final)
            $consultoresPorOperacao = [];
            foreach ($operacoes as $op) {
                $lista = \App\Models\User::where('ativo', true)
                    ->whereHas('operacoes', fn ($q) => $q->where('operacoes.id', $op->id)->where('operacao_user.role', 'consultor'))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values()
                    ->toArray();
                // Gestor ou admin na operação: adicionar "Nome (Você)" ao final da lista
                if ($user->temAlgumPapelNaOperacao($op->id, ['gestor', 'administrador'])) {
                    $lista[] = ['id' => $user->id, 'name' => $user->name . ' (Você)'];
                }
                $consultoresPorOperacao[$op->id] = $lista;
            }
            
            // Se o usuário tem apenas 1 operação, já vir selecionada
            $operacaoSelecionadaId = $operacoes->count() === 1 ? $operacoes->first()->id : null;
            
            // Verificar se há cliente_id na query string (vindo da página de detalhes do cliente)
            $clientePreSelecionado = null;
            if ($request->has('cliente_id')) {
                $clienteId = $request->input('cliente_id');
                $clientePreSelecionado = Cliente::find($clienteId);
            }
            
            // Verificar se é uma negociação de empréstimo existente
            $negociacao = false;
            $emprestimoOrigem = null;
            $saldoDevedor = null;
            
            if ($request->has('negociacao_de')) {
                $emprestimoOrigemId = (int) $request->input('negociacao_de');
                $emprestimoOrigem = Emprestimo::with(['cliente', 'operacao', 'parcelas'])->find($emprestimoOrigemId);
                
                if ($emprestimoOrigem) {
                    if (!$user->temAlgumPapelNaOperacao($emprestimoOrigem->operacao_id, ['administrador', 'gestor'])) {
                        if ($emprestimoOrigem->consultor_id !== $user->id) {
                            abort(403, 'Você só pode negociar seus próprios empréstimos.');
                        }
                    } else {
                        if (!$user->temAcessoOperacao($emprestimoOrigem->operacao_id)) {
                            abort(403, 'Sem acesso a esta operação.');
                        }
                    }

                    // Verificar se pode ser negociado
                    if (!$emprestimoOrigem->isAtivo()) {
                        return redirect()->route('emprestimos.show', $emprestimoOrigem->id)
                            ->with('error', 'Apenas empréstimos ativos podem ser negociados.');
                    }

                    // Bloquear negociação de empréstimo que já é negociação
                    if ($emprestimoOrigem->emprestimo_origem_id) {
                        return redirect()->route('emprestimos.show', $emprestimoOrigem->id)
                            ->with('error', 'Este empréstimo já é resultado de uma negociação anterior e não pode ser negociado novamente.');
                    }
                    
                    $quitacaoService = app(\App\Modules\Loans\Services\QuitacaoService::class);
                    $saldoDevedor = $quitacaoService->getSaldoDevedor($emprestimoOrigem);
                    
                    if ($saldoDevedor <= 0) {
                        return redirect()->route('emprestimos.show', $emprestimoOrigem->id)
                            ->with('error', 'Este empréstimo não possui saldo devedor para negociação.');
                    }
                    
                    $negociacao = true;
                    $clientePreSelecionado = $emprestimoOrigem->cliente;
                    $operacaoSelecionadaId = $emprestimoOrigem->operacao_id;
                }
            }
            
            return view('emprestimos.create', compact(
                'operacoes',
                'consultoresPorOperacao',
                'clientePreSelecionado',
                'operacaoSelecionadaId',
                'negociacao',
                'emprestimoOrigem',
                'saldoDevedor'
            ));
        } catch (\Exception $e) {
            \Log::error('Erro ao carregar formulário de empréstimo: ' . $e->getMessage());
            if (!isset($operacoes)) {
                $operacoes = collect([]);
            }
            $consultoresPorOperacao = $consultoresPorOperacao ?? [];
            $clientePreSelecionado = null;
            $operacaoSelecionadaId = null;
            $negociacao = false;
            $emprestimoOrigem = null;
            $saldoDevedor = null;
            return view('emprestimos.create', compact(
                'operacoes',
                'consultoresPorOperacao',
                'clientePreSelecionado',
                'operacaoSelecionadaId',
                'negociacao',
                'emprestimoOrigem',
                'saldoDevedor'
            ));
        }
    }

    /**
     * Criar novo empréstimo
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        
        // Super Admin não pode criar empréstimos
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar empréstimos.');
        }

        // Normalizar valor_total: pode vir como array (dois campos no form) ou string;
        // aceitar formato BR (1.234,56) e EN (1234.56).
        $valorTotalRaw = $request->input('valor_total');
        if (is_array($valorTotalRaw)) {
            $valorTotalRaw = collect($valorTotalRaw)->map(fn ($v) => is_scalar($v) ? trim((string) $v) : null)
                ->filter(fn ($v) => $v !== '' && $v !== null)->first();
        }
        if (is_string($valorTotalRaw) || is_numeric($valorTotalRaw)) {
            $valorTotalRaw = trim((string) $valorTotalRaw);
            // Formato BR: 1.234,56 ou 1234,56
            if (str_contains($valorTotalRaw, ',')) {
                $valorTotalRaw = str_replace('.', '', $valorTotalRaw);
                $valorTotalRaw = str_replace(',', '.', $valorTotalRaw);
            }
        }
        $request->merge(['valor_total' => $valorTotalRaw !== '' && $valorTotalRaw !== null ? $valorTotalRaw : null]);

        // Normalizar numero_parcelas: o formulário tem dois campos (manual + hidden troca_cheque);
        // PHP pode receber como array — usar o valor correto conforme o tipo.
        $numeroParcelasRaw = $request->input('numero_parcelas');
        if (is_array($numeroParcelasRaw)) {
            $tipo = $request->input('tipo');
            // Ordem no form: primeiro o input visível (dinheiro/price/empenho), depois o hidden (troca_cheque)
            $numeroParcelasRaw = $tipo === 'troca_cheque'
                ? (collect($numeroParcelasRaw)->filter(fn ($v) => $v !== '' && $v !== null)->last() ?? 1)
                : (collect($numeroParcelasRaw)->filter(fn ($v) => $v !== '' && $v !== null)->first() ?? 1);
        }
        $request->merge(['numero_parcelas' => $numeroParcelasRaw]);

        $isRetroativo = $request->boolean('is_retroativo');
        $hojeBrasil = Carbon::today('America/Sao_Paulo')->toDateString();
        $rules = [
            'operacao_id' => 'required|exists:operacoes,id',
            'cliente_id' => 'required|exists:clientes,id',
            'valor_total' => 'required|numeric|min:0.01',
            'numero_parcelas' => 'required|integer|min:1',
            'frequencia' => 'required|in:diaria,semanal,mensal',
            'data_inicio' => $isRetroativo
                ? ['required', 'date']
                : [
                    'required',
                    'date',
                    function ($attribute, $value, $fail) use ($hojeBrasil) {
                        $data = $value instanceof \DateTimeInterface
                            ? $value->format('Y-m-d')
                            : Carbon::parse($value)->format('Y-m-d');
                        if ($data < $hojeBrasil) {
                            $fail('A data de início deve ser igual ou posterior a ' . Carbon::parse($hojeBrasil)->format('d/m/Y') . '.');
                        }
                    },
                ],
            'tipo' => 'required|in:dinheiro,price,empenho,troca_cheque',
            'taxa_juros' => 'nullable|numeric|min:0|max:100',
            'observacoes' => 'nullable|string',
            // Validação para cheques (troca_cheque)
            'cheques' => 'required_if:tipo,troca_cheque|array|min:1',
            'cheques.*.banco' => 'nullable|string|max:100',
            'cheques.*.agencia' => 'nullable|string|max:20',
            'cheques.*.conta' => 'nullable|string|max:20',
            'cheques.*.numero_cheque' => 'nullable|string|max:50',
            'cheques.*.data_vencimento' => 'required_with:cheques|date|after_or_equal:today',
            'cheques.*.valor_cheque' => 'required_with:cheques|numeric|min:0.01',
            'cheques.*.taxa_juros' => 'nullable|numeric|min:0|max:100',
            'cheques.*.portador' => 'nullable|string|max:255',
            'cheques.*.observacoes' => 'nullable|string|max:1000',
            'is_retroativo' => 'boolean',
            'consultor_id' => 'nullable|exists:users,id',
        ];
        $validated = $request->validate($rules);

        // Gestor ou administrador na operação: devem selecionar o consultor responsável (podem escolher a si mesmos — "Nome (Você)")
        $ehGestorOuAdminQueEscolhe = $user->temAlgumPapelNaOperacao((int) $validated['operacao_id'], ['gestor', 'administrador']);
        if ($ehGestorOuAdminQueEscolhe) {
            $consultorId = $request->input('consultor_id');
            if (empty($consultorId)) {
                return back()->withErrors(['consultor_id' => 'Selecione o consultor responsável pelo empréstimo.'])->withInput();
            }
            $consultor = \App\Models\User::find($consultorId);
            if (!$consultor || !$consultor->temAcessoOperacao($validated['operacao_id'])) {
                return back()->withErrors(['consultor_id' => 'O consultor selecionado não pertence a esta operação.'])->withInput();
            }
            $validated['consultor_id'] = (int) $consultorId;
        }

        // Empréstimo retroativo: consultor (não gestor/admin) cria com aceite
        if ($isRetroativo) {
            if (!$user->temAcessoOperacao($validated['operacao_id'])) {
                return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
            }
            if (!$ehGestorOuAdminQueEscolhe) {
                // Consultor: ele é o responsável; empréstimo fica aguardando aceite de gestor/admin
                $validated['consultor_id'] = $user->id;
                $validated['is_retroativo'] = true;
                $validated['solicitar_aceite_retroativo'] = true;
            } else {
                $validated['is_retroativo'] = true;
            }
        } elseif (!$ehGestorOuAdminQueEscolhe) {
            // Apenas consultor (sem gestor/admin): empréstimo normal fica para ele
            $validated['consultor_id'] = $user->id;
        }

        // Registrar quem criou o empréstimo (para auditoria; gestor que criou em nome do consultor)
        $validated['criado_por_user_id'] = $user->id;

        // Validação específica para Price: taxa de juros obrigatória
        if ($validated['tipo'] === 'price' && (empty($validated['taxa_juros']) || $validated['taxa_juros'] <= 0)) {
            return back()->withErrors(['taxa_juros' => 'A taxa de juros é obrigatória para empréstimos do tipo Price.'])->withInput();
        }

        // Validação específica para troca_cheque: deve ter pelo menos um cheque
        if ($validated['tipo'] === 'troca_cheque') {
            if (empty($validated['cheques']) || count($validated['cheques']) < 1) {
                return back()->withErrors(['cheques' => 'Troca de cheque precisa ter pelo menos um cheque cadastrado.'])->withInput();
            }
        }

        // Validar se o usuário tem acesso à operação (Super Admin: qualquer; demais: apenas as suas)
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds) || !in_array($validated['operacao_id'], $operacoesIds)) {
                return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
            }
        }

        // Verificar se é uma negociação
        $negociacaoEmprestimoId = $request->input('negociacao_emprestimo_id');
        
        if ($negociacaoEmprestimoId) {
            return $this->processarNegociacao($request, $user, $validated, (int) $negociacaoEmprestimoId);
        }

        try {
            $emprestimo = $this->emprestimoService->criar($validated);

            if ($emprestimo->status === 'aguardando_aceite_retroativo') {
                $mensagem = 'Empréstimo retroativo criado e aguardando aceite do gestor ou administrador.';
            } elseif ($emprestimo->status === 'pendente') {
                $mensagem = 'Empréstimo criado e aguardando aprovação.';
            } else {
                $mensagem = 'Empréstimo criado e aprovado automaticamente!';
            }

            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('success', $mensagem);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao criar empréstimo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao criar empréstimo: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Processar negociação de empréstimo
     */
    protected function processarNegociacao(Request $request, $user, array $validated, int $emprestimoOrigemId): RedirectResponse
    {
        $motivo = $request->input('motivo_negociacao');
        
        if (empty($motivo)) {
            return back()->withErrors(['motivo_negociacao' => 'O motivo da negociação é obrigatório.'])->withInput();
        }

        $emprestimoOrigem = Emprestimo::with(['operacao', 'cliente'])->findOrFail($emprestimoOrigemId);

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            if ($emprestimoOrigem->consultor_id !== $user->id) {
                return back()->with('error', 'Você só pode negociar seus próprios empréstimos.')->withInput();
            }
        } elseif (!$user->isSuperAdmin() && !$user->temAcessoOperacao($emprestimoOrigem->operacao_id)) {
            return back()->with('error', 'Sem acesso a esta operação.')->withInput();
        }

        // Dados do novo empréstimo
        $dadosNovoEmprestimo = [
            'tipo' => $validated['tipo'],
            'frequencia' => $validated['frequencia'],
            'taxa_juros' => $validated['taxa_juros'] ?? 0,
            'numero_parcelas' => $validated['numero_parcelas'],
            'data_inicio' => $validated['data_inicio'],
            'observacoes' => $validated['observacoes'] ?? null,
        ];

        try {
            // Gestor/Admin na operação executa direto
            if ($user->temAlgumPapelNaOperacao($operacaoIdOrigem, ['administrador', 'gestor'])) {
                $novoEmprestimo = $this->emprestimoService->negociar(
                    $emprestimoOrigemId,
                    $dadosNovoEmprestimo,
                    $motivo
                );

                return redirect()->route('emprestimos.show', $novoEmprestimo->id)
                    ->with('success', "Negociação realizada com sucesso! Novo empréstimo #{$novoEmprestimo->id} criado a partir do empréstimo #{$emprestimoOrigemId}.");
            }

            // Consultor cria solicitação
            $quitacaoService = app(\App\Modules\Loans\Services\QuitacaoService::class);
            $saldoDevedor = $quitacaoService->getSaldoDevedor($emprestimoOrigem);

            $solicitacao = \App\Modules\Loans\Models\SolicitacaoNegociacao::create([
                'emprestimo_id' => $emprestimoOrigemId,
                'consultor_id' => $user->id,
                'operacao_id' => $emprestimoOrigem->operacao_id,
                'saldo_devedor' => $saldoDevedor,
                'dados_novo_emprestimo' => $dadosNovoEmprestimo,
                'motivo' => $motivo,
                'status' => 'pendente',
            ]);

            // Notificar gestores e admins da operação
            $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
            $operacaoId = (int) $emprestimoOrigem->operacao_id;
            $dadosNotif = [
                'tipo' => 'negociacao_pendente',
                'titulo' => 'Nova Solicitação de Negociação',
                'mensagem' => "{$user->name} solicitou negociação do empréstimo #{$emprestimoOrigemId} - Cliente: {$emprestimoOrigem->cliente->nome}. Saldo devedor: R$ " . number_format($saldoDevedor, 2, ',', '.'),
                'url' => route('liberacoes.negociacoes'),
                'dados' => ['solicitacao_id' => $solicitacao->id],
            ];
            $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, $dadosNotif);
            $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, $dadosNotif);

            return redirect()->route('emprestimos.show', $emprestimoOrigemId)
                ->with('success', 'Solicitação de negociação enviada para aprovação do gestor/administrador.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao processar negociação: ' . $e->getMessage());
            return back()->with('error', 'Erro ao processar negociação: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar detalhes do empréstimo
     */
    public function show(int $id): View
    {
        $emprestimo = Emprestimo::with([
            'cliente',
            'operacao',
            'consultor',
            'criadoPor',
            'aprovador',
            'parcelas' => function ($query) {
                $query->orderBy('numero');
            },
            'parcelas.pagamentos.consultor',
            'parcelas.pagamentos.produtoObjetoItens',
            'parcelas.pagamentos.rejeitadoPor',
            'aprovacao.aprovador',
            'liberacao.consultor',
            'liberacao.gestor',
            'liberacao.confirmadoPagamentoPor',
            'emprestimoOrigem',
            'renovacoes',
            'garantias.anexos', // Garantias para empréstimo tipo empenho
            'cheques', // Cheques para empréstimo tipo troca_cheque
        ])->findOrFail($id);

        $user = auth()->user();
        if (!$user->temAcessoOperacao($emprestimo->operacao_id)) {
            abort(403, 'Sem acesso a esta operação.');
        }

        $temRenovacaoAbatePendente = \App\Modules\Loans\Models\SolicitacaoRenovacaoAbate::whereHas('parcela', fn ($q) => $q->where('emprestimo_id', $id))
            ->where('status', 'aguardando')
            ->exists();

        $solicitacoesRenovacaoAbate = \App\Modules\Loans\Models\SolicitacaoRenovacaoAbate::whereHas('parcela', fn ($q) => $q->where('emprestimo_id', $id))
            ->with(['aprovadoPor', 'rejeitadoPor'])
            ->orderByDesc('id')
            ->get();

        $opId = $emprestimo->operacao_id;
        $podeVerAcoesGestorAdmin = $user->temAlgumPapelNaOperacao($opId, ['gestor', 'administrador']);
        $podeCancelar = $user->temPapelNaOperacao($opId, 'administrador');
        $podeExecutarGarantia = $podeVerAcoesGestorAdmin;
        $podeRenovar = $podeVerAcoesGestorAdmin || $emprestimo->consultor_id === $user->id;
        $podeNegociar = $podeRenovar;
        $podeCancelarComDesfazimento = $podeVerAcoesGestorAdmin;
        $podeConfirmarPagamentoCliente = $emprestimo->liberacao && $emprestimo->liberacao->consultor_id === $user->id;
        $podeAprovarLiberacao = $podeVerAcoesGestorAdmin;
        $podeAcoesCheque = $podeVerAcoesGestorAdmin;
        // Garantias só podem ser editadas/excluídas antes da liberação e se empréstimo não finalizado (apenas empenho)
        $podeEditarGarantias = $emprestimo->isEmpenho() && !$emprestimo->isFinalizado() && !$emprestimo->foiLiberado();

        return view('emprestimos.show', compact(
            'emprestimo',
            'temRenovacaoAbatePendente',
            'solicitacoesRenovacaoAbate',
            'podeVerAcoesGestorAdmin',
            'podeCancelar',
            'podeExecutarGarantia',
            'podeRenovar',
            'podeNegociar',
            'podeCancelarComDesfazimento',
            'podeConfirmarPagamentoCliente',
            'podeAprovarLiberacao',
            'podeAcoesCheque',
            'podeEditarGarantias'
        ));
    }

    /**
     * Renovar empréstimo (pagar apenas juros e gerar novo empréstimo)
     */
    public function renovar(int $id): RedirectResponse
    {
        $user = auth()->user();
        $emprestimo = Emprestimo::findOrFail($id);
        $operacaoId = $emprestimo->operacao_id;

        if (!$user->temAlgumPapelNaOperacao($operacaoId, ['administrador', 'gestor'])) {
            if ($emprestimo->consultor_id !== $user->id) {
                abort(403, 'Você só pode renovar seus próprios empréstimos.');
            }
        } elseif (!$user->temAcessoOperacao($operacaoId)) {
            abort(403, 'Sem acesso a esta operação.');
        }

        try {
            $novoEmprestimo = $this->emprestimoService->renovar($id);

            return redirect()
                ->route('emprestimos.show', $novoEmprestimo->id)
                ->with(
                    'success',
                    "Empréstimo renovado com sucesso! Este empréstimo é a renovação do empréstimo #{$id}."
                );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Erro ao renovar empréstimo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao renovar empréstimo: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar empréstimo (apenas administradores na operação do empréstimo)
     */
    public function cancelar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $emprestimo = Emprestimo::findOrFail($id);

        if (!$user->temPapelNaOperacao($emprestimo->operacao_id, 'administrador')) {
            abort(403, 'Apenas administradores podem cancelar empréstimos.');
        }

        $request->validate([
            'motivo_cancelamento' => 'required|string|min:10|max:1000',
        ], [
            'motivo_cancelamento.required' => 'O motivo do cancelamento é obrigatório.',
            'motivo_cancelamento.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo_cancelamento.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ]);

        try {
            $this->emprestimoService->cancelar($id, $user->id, $request->motivo_cancelamento);

            return redirect()
                ->route('emprestimos.show', $id)
                ->with('success', 'Empréstimo cancelado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Erro ao cancelar empréstimo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao cancelar empréstimo: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar empréstimo com desfazimento de todos os pagamentos (gestor ou administrador).
     * Permite cancelar mesmo com parcelas pagas ou empréstimo finalizado.
     */
    public function cancelarComDesfazimento(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $emprestimo = Emprestimo::findOrFail($id);

        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor'])) {
            abort(403, 'Apenas gestores e administradores podem cancelar empréstimo com desfazimento.');
        }

        $request->validate([
            'motivo_cancelamento' => 'required|string|min:10|max:1000',
        ], [
            'motivo_cancelamento.required' => 'O motivo do cancelamento é obrigatório.',
            'motivo_cancelamento.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo_cancelamento.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ]);

        try {
            $this->emprestimoService->cancelarComDesfazimento($id, $user->id, $request->motivo_cancelamento);

            return redirect()
                ->route('emprestimos.show', $id)
                ->with('success', 'Empréstimo cancelado com sucesso. Todos os pagamentos e movimentações foram desfeitos.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Erro ao cancelar empréstimo com desfazimento: ' . $e->getMessage());
            return back()->with('error', 'Erro ao cancelar empréstimo: ' . $e->getMessage());
        }
    }

    /**
     * Executar garantia de empréstimo tipo empenho
     */
    public function executarGarantia(Request $request, int $id, int $garantiaId): RedirectResponse
    {
        $user = auth()->user();
        $emprestimo = Emprestimo::findOrFail($id);

        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor'])) {
            abort(403, 'Apenas gestores e administradores podem executar garantias.');
        }

        $request->validate([
            'observacoes' => 'required|string|min:10|max:1000',
        ]);

        try {
            $garantia = $this->emprestimoService->executarGarantia(
                $id,
                $garantiaId,
                $user->id,
                $request->input('observacoes')
            );

            return redirect()
                ->route('emprestimos.show', $id)
                ->with('success', 'Garantia executada com sucesso! O empréstimo foi finalizado automaticamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao executar garantia: ' . $e->getMessage());
            return back()->with('error', 'Erro ao executar garantia: ' . $e->getMessage());
        }
    }

    /**
     * Registrar parcelas já pagas (empréstimo retroativo) com opção de gerar ou não caixa
     */
    public function registrarParcelasPagasRetroativo(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $emprestimo = Emprestimo::findOrFail($id);

        if (!$emprestimo->is_retroativo) {
            return back()->with('error', 'Este empréstimo não é retroativo.');
        }
        if ($emprestimo->isAguardandoAceiteRetroativo()) {
            return back()->with('error', 'Este empréstimo retroativo ainda está aguardando aceite do gestor ou administrador.');
        }

        $podeRegistrar = $user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor'])
            || $emprestimo->consultor_id === $user->id;
        if (!$podeRegistrar) {
            abort(403, 'Você não pode registrar parcelas pagas deste empréstimo.');
        }

        $parcelasInput = $request->input('parcelas');
        if (is_string($parcelasInput)) {
            $parcelasInput = json_decode($parcelasInput, true) ?? [];
        }
        $request->merge(['parcelas' => is_array($parcelasInput) ? $parcelasInput : []]);

        $request->validate([
            'parcelas' => 'required|array|min:1',
            'parcelas.*.parcela_id' => 'required|exists:parcelas,id',
            'parcelas.*.data_pagamento' => 'required|date',
            'gerar_caixa_global' => 'required|in:0,1',
        ]);

        $gerarCaixaGlobal = $request->boolean('gerar_caixa_global');
        $cashService = app(\App\Modules\Cash\Services\CashService::class);

        \Illuminate\Support\Facades\DB::transaction(function () use ($emprestimo, $request, $gerarCaixaGlobal, $cashService) {
            foreach ($request->input('parcelas') as $item) {
                $parcela = Parcela::where('emprestimo_id', $emprestimo->id)->find($item['parcela_id']);
                if (!$parcela || $parcela->status === 'paga') {
                    continue;
                }
                // Só aceita parcelas cujo vencimento já passou (ou é hoje)
                if ($parcela->data_vencimento && $parcela->data_vencimento->isFuture()) {
                    continue;
                }

                $dataPagamento = \Carbon\Carbon::parse($item['data_pagamento']);
                $gerarCaixa = $gerarCaixaGlobal;

                $parcela->update([
                    'valor_pago' => $parcela->valor,
                    'data_pagamento' => $dataPagamento,
                    'status' => 'paga',
                    'dias_atraso' => 0,
                ]);

                if ($gerarCaixa) {
                    $pagamento = \App\Modules\Loans\Models\Pagamento::create([
                        'parcela_id' => $parcela->id,
                        'consultor_id' => $emprestimo->consultor_id,
                        'valor' => $parcela->valor,
                        'metodo' => 'dinheiro',
                        'data_pagamento' => $dataPagamento,
                        'observacoes' => 'Pagamento retroativo (empréstimo já existente)',
                        'tipo_juros' => null,
                        'taxa_juros_aplicada' => null,
                        'valor_juros' => 0,
                    ]);

                    $cashService->registrarMovimentacao([
                        'operacao_id' => $emprestimo->operacao_id,
                        'consultor_id' => $emprestimo->consultor_id,
                        'pagamento_id' => $pagamento->id,
                        'tipo' => 'entrada',
                        'origem' => 'automatica',
                        'valor' => $pagamento->valor,
                        'descricao' => 'Pagamento retroativo - Parcela #' . $parcela->numero . ' - Empréstimo #' . $emprestimo->id,
                        'data_movimentacao' => $dataPagamento,
                        'referencia_tipo' => 'pagamento_parcela',
                        'referencia_id' => $parcela->id,
                    ]);
                }
            }

            app(\App\Modules\Loans\Services\PagamentoService::class)->verificarFinalizacaoEmprestimo($emprestimo);
        });

        return redirect()->route('emprestimos.show', $id)
            ->with('success', 'Parcelas registradas como pagas.');
    }

    /**
     * Listagem de empréstimos retroativos aguardando aceite (gestor e administrador).
     */
    public function indexPendentesRetroativo(Request $request): View
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Apenas gestores e administradores podem ver empréstimos retroativos pendentes de aceite.');
        }

        $operacaoId = $request->input('operacao_id');
        if ($operacaoId && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = SolicitacaoEmprestimoRetroativo::with(['emprestimo.cliente', 'emprestimo.operacao', 'emprestimo.parcelas', 'solicitante'])
            ->where('status', 'aguardando');
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if ($operacaoId) {
            $query->whereHas('emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = \App\Modules\Core\Models\Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? \App\Modules\Core\Models\Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('emprestimos.retroativo-pendentes', compact('solicitacoes', 'operacoes', 'operacaoId'));
    }

    /**
     * Aprovar empréstimo retroativo criado por consultor.
     */
    public function aprovarRetroativo(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoEmprestimoRetroativo::with('emprestimo')->findOrFail($id);
        if ($solicitacao->status !== 'aguardando') {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        if (!$user->temAlgumPapelNaOperacao($solicitacao->emprestimo->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores da operação podem aprovar empréstimos retroativos.');
        }

        $solicitacao->update([
            'status' => 'aprovado',
            'aprovado_por' => $user->id,
            'aprovado_em' => now(),
        ]);
        $solicitacao->emprestimo->update(['status' => 'ativo']);

        // Criar liberação retroativa (pago_ao_cliente) para permitir pagamento de parcelas
        app(\App\Modules\Loans\Services\LiberacaoService::class)
            ->criarParaRetroativo($solicitacao->emprestimo, $user->id);
        // Marcar como atrasadas as parcelas já vencidas (não depender do cron)
        app(\App\Modules\Loans\Services\ParcelaService::class)->marcarAtrasadasDoEmprestimo($solicitacao->emprestimo);

        return redirect()->route('emprestimos.retroativo.pendentes')
            ->with('success', 'Empréstimo retroativo aprovado. O empréstimo #' . $solicitacao->emprestimo_id . ' está ativo.');
    }

    /**
     * Aprovar em lote várias solicitações de empréstimo retroativo.
     */
    public function aprovarRetroativoLote(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Apenas gestores e administradores podem aprovar empréstimos retroativos.');
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:solicitacoes_emprestimo_retroativo,id',
        ]);

        $aprovados = 0;

        foreach ($request->input('ids') as $id) {
            $solicitacao = SolicitacaoEmprestimoRetroativo::with('emprestimo')->find($id);
            if (!$solicitacao || $solicitacao->status !== 'aguardando') {
                continue;
            }
            if (!$user->temAlgumPapelNaOperacao($solicitacao->emprestimo->operacao_id, ['gestor', 'administrador'])) {
                continue;
            }
            $solicitacao->update([
                'status' => 'aprovado',
                'aprovado_por' => $user->id,
                'aprovado_em' => now(),
            ]);
            $solicitacao->emprestimo->update(['status' => 'ativo']);
            app(\App\Modules\Loans\Services\LiberacaoService::class)
                ->criarParaRetroativo($solicitacao->emprestimo, $user->id);
            app(\App\Modules\Loans\Services\ParcelaService::class)->marcarAtrasadasDoEmprestimo($solicitacao->emprestimo);
            $aprovados++;
        }

        $msg = $aprovados === 0
            ? 'Nenhuma solicitação elegível para aprovação.'
            : ($aprovados === 1 ? '1 empréstimo retroativo aprovado.' : $aprovados . ' empréstimos retroativos aprovados.');

        return redirect()->route('emprestimos.retroativo.pendentes')->with('success', $msg);
    }

    /**
     * Rejeitar empréstimo retroativo criado por consultor.
     */
    public function rejeitarRetroativo(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoEmprestimoRetroativo::with('emprestimo')->findOrFail($id);
        if ($solicitacao->status !== 'aguardando') {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        if (!$user->temAlgumPapelNaOperacao($solicitacao->emprestimo->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem rejeitar empréstimos retroativos.');
        }

        $validated = $request->validate([
            'motivo_rejeicao' => 'required|string|min:5|max:500',
        ]);

        $solicitacao->update([
            'status' => 'rejeitado',
            'motivo_rejeicao' => $validated['motivo_rejeicao'],
        ]);
        $solicitacao->emprestimo->update(['status' => 'cancelado']);

        return redirect()->route('emprestimos.retroativo.pendentes')
            ->with('success', 'Empréstimo retroativo rejeitado.');
    }
}
