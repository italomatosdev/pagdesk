{{--
  Filtro de operações (GET): checkboxes em dropdown Bootstrap.
  Espera: $operacoes (collection), $operacaoFiltroIds (array), $prefix (string único por tela, ex. 'admin').
--}}
@php
    $prefix = $prefix ?? 'dash';
    $ddId = 'dropdownOperacoes'.preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $prefix);
    $opFiltro = array_values(array_unique(array_map('intval', $operacaoFiltroIds ?? [])));
    sort($opFiltro);
    $opTodos = $operacoes->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    sort($opTodos);
    $todasMarcadas = count($opTodos) > 0 && $opFiltro === $opTodos;
    $nomesSel = $operacoes->filter(fn ($o) => in_array((int) $o->id, $opFiltro, true))->pluck('nome');
    if ($operacoes->isEmpty()) {
        $rotuloBtn = '—';
    } elseif ($todasMarcadas) {
        $rotuloBtn = 'Todas as operações ('.$operacoes->count().')';
    } elseif (count($opFiltro) === 0) {
        $rotuloBtn = 'Selecione…';
    } elseif (count($opFiltro) === 1) {
        $rotuloBtn = $nomesSel->first() ?? '1 operação';
    } elseif (count($opFiltro) === 2) {
        $rotuloBtn = $nomesSel->implode(' · ');
    } else {
        $rotuloBtn = count($opFiltro).' operações';
    }
@endphp
<div class="flex-grow-1">
    <label class="form-label" for="{{ $ddId }}">Operações</label>
    @if($operacoes->isEmpty())
        <div class="form-control bg-light text-muted small py-2">Nenhuma operação disponível</div>
    @else
        <div class="dropdown w-100">
            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center"
                type="button"
                id="{{ $ddId }}"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false">
                <span class="text-truncate me-2">{{ $rotuloBtn }}</span>
            </button>
            <div class="dropdown-menu p-2 shadow w-100" style="max-height: 280px; overflow-y: auto;" aria-labelledby="{{ $ddId }}">
                @foreach($operacoes as $operacao)
                    <div class="form-check mb-1">
                        <input class="form-check-input"
                            type="checkbox"
                            name="operacao_id[]"
                            value="{{ $operacao->id }}"
                            id="{{ $ddId }}-op-{{ $operacao->id }}"
                            @checked(in_array((int) $operacao->id, $opFiltro, true))>
                        <label class="form-check-label w-100" for="{{ $ddId }}-op-{{ $operacao->id }}">
                            {{ $operacao->nome }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
