<?php

namespace App\Support;

use App\Models\ComprovanteAnexo;
use App\Models\User;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Models\Settlement;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Modules\Loans\Models\Pagamento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ComprovanteAnexoService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, ComprovanteAnexo>
     */
    public function storeExtras(
        LiberacaoEmprestimo|Settlement|Pagamento|CashLedgerEntry $parent,
        array $files,
        User $user,
        ?string $context,
        string $storageSubdir
    ): Collection {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile && $f->isValid()));
        if ($files === []) {
            throw ValidationException::withMessages([
                'comprovantes_extras' => 'Envie pelo menos um arquivo válido.',
            ]);
        }

        $empresaId = $this->resolveEmpresaId($parent);

        return DB::transaction(function () use ($parent, $files, $user, $context, $storageSubdir, $empresaId) {
            $criados = collect();
            foreach ($files as $file) {
                $path = $file->store('comprovantes/'.$storageSubdir, 'public');
                $criados->push(ComprovanteAnexo::create([
                    'anexavel_type' => $parent->getMorphClass(),
                    'anexavel_id' => $parent->getKey(),
                    'context' => $context,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $user->id,
                    'empresa_id' => $empresaId,
                ]));
            }

            return $criados;
        });
    }

    private function resolveEmpresaId(LiberacaoEmprestimo|Settlement|Pagamento|CashLedgerEntry $parent): ?int
    {
        if (isset($parent->empresa_id)) {
            return $parent->empresa_id ? (int) $parent->empresa_id : null;
        }
        if ($parent instanceof Pagamento) {
            return $parent->parcela?->emprestimo?->empresa_id
                ? (int) $parent->parcela->emprestimo->empresa_id
                : null;
        }
        if ($parent instanceof LiberacaoEmprestimo) {
            return $parent->emprestimo?->empresa_id
                ? (int) $parent->emprestimo->empresa_id
                : null;
        }

        return null;
    }
}
