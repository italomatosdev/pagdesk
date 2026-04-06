<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Models\Settlement;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Support\ComprovanteAnexoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComprovanteAnexoController extends Controller
{
    public function __construct(
        protected ComprovanteAnexoService $comprovanteAnexoService
    ) {
        $this->middleware('auth');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tipo' => 'required|in:liberacao,settlement,pagamento,movimentacao_caixa',
            'id' => 'required|integer|min:1',
            'context' => 'required_if:tipo,liberacao|nullable|string|in:liberacao,pagamento_cliente',
            'comprovantes_extras' => 'required|array|min:1|max:15',
            'comprovantes_extras.*' => 'file|mimes:pdf,jpg,jpeg,png|max:2048',
            'redirect' => 'nullable|string|max:2048',
        ]);

        $user = auth()->user();
        $tipo = $validated['tipo'];
        $id = (int) $validated['id'];
        $context = $validated['context'] ?? null;

        if ($tipo === 'liberacao' && ! in_array($context, ['liberacao', 'pagamento_cliente'], true)) {
            throw ValidationException::withMessages([
                'context' => 'Informe o contexto (liberação ou pagamento ao cliente).',
            ]);
        }
        if ($tipo !== 'liberacao' && $context !== null) {
            throw ValidationException::withMessages([
                'context' => 'Contexto só se aplica a liberação.',
            ]);
        }

        $files = $request->file('comprovantes_extras', []);

        try {
            match ($tipo) {
                'liberacao' => $this->storeLiberacao(
                    $id,
                    $context,
                    $files,
                    $user,
                    $context === 'pagamento_cliente' ? 'pagamentos-cliente/extras' : 'liberacoes/extras'
                ),
                'settlement' => $this->storeSettlement($id, $files, $user, 'settlements/extras'),
                'pagamento' => $this->storePagamento($id, $files, $user, 'pagamentos/extras'),
                'movimentacao_caixa' => $this->storeMovimentacao($id, $files, $user, 'movimentacoes/extras'),
                default => abort(400),
            };
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->redirectAfter($request, 'Comprovantes adicionais enviados com sucesso.');
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storeLiberacao(int $id, string $context, array $files, User $user, string $subdir): void
    {
        $lib = LiberacaoEmprestimo::with('emprestimo')->findOrFail($id);
        if ($context === 'liberacao') {
            if (! $user->temAlgumPapelNaOperacao($lib->emprestimo->operacao_id, ['gestor', 'administrador'])) {
                abort(403, 'Acesso negado.');
            }
        } elseif ($lib->consultor_id !== $user->id) {
            abort(403, 'Você só pode anexar comprovantes nas suas próprias liberações.');
        }

        $this->comprovanteAnexoService->storeExtras($lib, $files, $user, $context, $subdir);
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storeSettlement(int $id, array $files, User $user, string $subdir): void
    {
        $settlement = Settlement::findOrFail($id);
        if ($settlement->consultor_id !== $user->id) {
            abort(403, 'Acesso negado.');
        }

        $this->comprovanteAnexoService->storeExtras($settlement, $files, $user, null, $subdir);
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storePagamento(int $id, array $files, User $user, string $subdir): void
    {
        $pagamento = Pagamento::with('parcela.emprestimo')->findOrFail($id);
        $emprestimo = $pagamento->parcela->emprestimo;
        if (! $user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            abort(403, 'Acesso negado.');
        }

        $this->comprovanteAnexoService->storeExtras($pagamento, $files, $user, null, $subdir);
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storeMovimentacao(int $id, array $files, User $user, string $subdir): void
    {
        $mov = CashLedgerEntry::findOrFail($id);
        if ($mov->consultor_id !== $user->id && ! $user->temAlgumPapelNaOperacao($mov->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $this->comprovanteAnexoService->storeExtras($mov, $files, $user, null, $subdir);
    }

    private function redirectAfter(Request $request, string $successMessage): RedirectResponse
    {
        $path = $request->input('redirect');
        if (is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && strlen($path) <= 2048) {
            return redirect()->to($path)->with('success', $successMessage);
        }

        return back()->with('success', $successMessage);
    }
}
