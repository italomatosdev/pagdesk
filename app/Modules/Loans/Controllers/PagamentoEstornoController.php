<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Services\PagamentoEstornoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PagamentoEstornoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $pagamento = Pagamento::with(['parcela.emprestimo'])->findOrFail($id);
        $emprestimo = $pagamento->parcela->emprestimo;

        $user = $request->user();
        if (! $user->temAlgumPapelNaOperacao((int) $emprestimo->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestor ou administrador pode estornar pagamentos.');
        }

        if (! $user->temAcessoOperacao((int) $emprestimo->operacao_id)) {
            abort(403, 'Sem acesso a esta operação.');
        }

        $validator = Validator::make($request->all(), [
            'motivo' => ['required', 'string', 'max:2000'],
        ], [], ['motivo' => 'motivo']);

        if ($validator->fails()) {
            return redirect()
                ->route('emprestimos.show', $emprestimo->id)
                ->withErrors($validator)
                ->withInput()
                ->with('error', $validator->errors()->first('motivo'))
                ->with('estorno_pagamento_com_erro', $pagamento->id);
        }

        try {
            app(PagamentoEstornoService::class)->estornar($pagamento, $user, $request->input('motivo'));
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return redirect()
                ->route('emprestimos.show', $emprestimo->id)
                ->withInput($request->only('motivo'))
                ->withErrors($e->errors())
                ->with('error', $mensagem)
                ->with('estorno_pagamento_com_erro', $pagamento->id);
        }

        return redirect()
            ->route('emprestimos.show', $emprestimo->id)
            ->with('success', 'Pagamento estornado com sucesso. Foi registrada uma saída no caixa com a data de hoje.');
    }
}
