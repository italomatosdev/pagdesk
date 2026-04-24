@php
    $opcoes = \App\Modules\Core\Models\Produto::unidadesParaSelect();
    $v = (string) ($valorSelecionado ?? 'un');
    if ($v !== '' && ! array_key_exists($v, $opcoes)) {
        $opcoes = [$v => 'Legado: '.$v] + $opcoes;
    }
@endphp
<div class="mb-3">
    <label class="form-label">Unidade <span class="text-danger">*</span></label>
    <select name="unidade" id="produto_unidade_select" class="form-select" required>
        @foreach($opcoes as $cod => $label)
            <option value="{{ $cod }}" @selected($v === $cod)>{{ $label }}</option>
        @endforeach
    </select>
    <small class="text-muted">Un, peça, caixa e dúzia: estoque inteiro. Peso e medida: até 3 decimais.</small>
    @error('unidade')
        <div class="text-danger">{{ $message }}</div>
    @enderror
</div>
