<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SandboxService;
use App\Modules\Core\Models\Empresa;
use App\Modules\Core\Models\Operacao;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SandboxController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Tela principal do Sandbox (ambiente de testes)
     */
    public function index(Request $request): View
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Acesso negado.');
        }

        $empresas = Empresa::orderBy('nome')->get();
        $operacoes = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with('empresa')
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        return view('super-admin.sandbox.index', compact('empresas', 'operacoes'));
    }

    /**
     * Gerar clientes fictícios
     */
    public function storeClientes(Request $request): RedirectResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Acesso negado.');
        }

        $validated = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'operacao_id' => 'nullable|exists:operacoes,id',
            'quantidade' => 'required|integer|min:1|max:50',
            'prefixo' => 'nullable|string|max:50',
        ]);

        $prefixo = $request->input('prefixo', '[SANDBOX]');
        $service = app(SandboxService::class);
        $criados = $service->criarClientesFicticios(
            (int) $validated['empresa_id'],
            (int) $validated['quantidade'],
            $prefixo,
            isset($validated['operacao_id']) ? (int) $validated['operacao_id'] : null
        );

        $msg = count($criados) === 1
            ? '1 cliente fictício criado.'
            : count($criados) . ' clientes fictícios criados.';
        return redirect()->route('super-admin.sandbox.index')->with('success', $msg);
    }

    /**
     * Gerar cenário: empréstimos com parcelas atrasadas
     */
    public function storeCenario(Request $request): RedirectResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Acesso negado.');
        }

        $validated = $request->validate([
            'operacao_id' => 'required|exists:operacoes,id',
            'quantidade_emprestimos' => 'nullable|integer|min:1|max:20',
            'valor_parcela' => 'required|numeric|min:1|max:999999',
            'numero_parcelas' => 'required|integer|min:1|max:60',
            'dias_atraso' => 'required|integer|min:1|max:365',
        ]);

        $service = app(SandboxService::class);
        try {
            $emprestimos = $service->criarCenarioParcelasAtrasadas(
                (int) $validated['operacao_id'],
                (int) ($validated['quantidade_emprestimos'] ?? 1),
                null, // usa clientes sandbox da empresa da operação
                (float) $validated['valor_parcela'],
                (int) $validated['numero_parcelas'],
                (int) $validated['dias_atraso']
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('super-admin.sandbox.index')->with('error', $e->getMessage());
        }

        $msg = count($emprestimos) === 1
            ? '1 empréstimo sandbox com parcelas atrasadas criado.'
            : count($emprestimos) . ' empréstimos sandbox criados.';
        return redirect()->route('super-admin.sandbox.index')->with('success', $msg);
    }

    /**
     * Gerar cenário: empréstimo diária com juros 30%
     */
    public function storeCenarioDiaria(Request $request): RedirectResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Acesso negado.');
        }

        $validated = $request->validate([
            'operacao_id' => 'required|exists:operacoes,id',
            'valor_total' => 'nullable|numeric|min:1|max:999999',
            'numero_parcelas' => 'nullable|integer|min:2|max:60',
            'dias_atraso_primeira' => 'nullable|integer|min:0|max:365',
        ]);

        $service = app(SandboxService::class);
        try {
            $emprestimo = $service->criarCenarioEmprestimoDiaria(
                (int) $validated['operacao_id'],
                null,
                (float) ($validated['valor_total'] ?? 1000),
                (int) ($validated['numero_parcelas'] ?? 7),
                (int) ($validated['dias_atraso_primeira'] ?? 5)
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('super-admin.sandbox.index')->with('error', $e->getMessage());
        }

        return redirect()->route('super-admin.sandbox.index')
            ->with('success', 'Empréstimo diária (30% juros) criado. Empréstimo #' . $emprestimo->id . '.');
    }

    /**
     * Limpar dados sandbox
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Acesso negado.');
        }

        $incluirClientes = $request->boolean('incluir_clientes');
        $service = app(SandboxService::class);
        $count = $service->limparSandbox($incluirClientes);

        $msg = 'Sandbox limpo: ' . $count['emprestimos'] . ' empréstimo(s) removido(s).';
        if ($incluirClientes && $count['clientes'] > 0) {
            $msg .= ' ' . $count['clientes'] . ' cliente(s) fictício(s) removido(s).';
        }
        return redirect()->route('super-admin.sandbox.index')->with('success', $msg);
    }
}
