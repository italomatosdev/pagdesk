@extends('layouts.master')
@section('title')
    Nova Prestação de Contas
@endsection
@section('page-title')
    Nova Prestação de Contas
@endsection
@section('body')

    <body>

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Criar Prestação de Contas</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('prestacoes.preview') }}" method="POST" class="form-criar-prestacao" data-no-loading>
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Operação <span class="text-danger">*</span></label>
                                <select name="operacao_id" class="form-select" required>
                                    <option value="">Selecione uma operação...</option>
                                    @foreach($operacoes as $operacao)
                                        <option value="{{ $operacao->id }}" 
                                                {{ old('operacao_id') == $operacao->id ? 'selected' : '' }}>
                                            {{ $operacao->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('operacao_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data Início <span class="text-danger">*</span></label>
                                    <input type="date" name="data_inicio" class="form-control" 
                                           value="{{ old('data_inicio') }}" required>
                                    @error('data_inicio')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data Fim <span class="text-danger">*</span></label>
                                    <input type="date" name="data_fim" class="form-control" 
                                           value="{{ old('data_fim') }}" required>
                                    @error('data_fim')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3">{{ old('observacoes') }}</textarea>
                            </div>

                            <div class="alert alert-info">
                                <i class="bx bx-info-circle"></i> 
                                O valor total será calculado automaticamente com base nas movimentações de entrada no período informado. Você poderá conferir os detalhes antes de criar a prestação.
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('prestacoes.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Conferir e Continuar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.form-criar-prestacao').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const operacaoSelect = this.querySelector('select[name="operacao_id"]');
                        const operacaoNome = operacaoSelect && operacaoSelect.selectedIndex >= 0 
                            ? operacaoSelect.options[operacaoSelect.selectedIndex].text 
                            : 'Não selecionada';
                        
                        const dataInicio = this.querySelector('input[name="data_inicio"]').value;
                        const dataFim = this.querySelector('input[name="data_fim"]').value;
                        
                        // Validar campos obrigatórios
                        if (!operacaoSelect || !operacaoSelect.value) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, selecione uma operação.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        if (!dataInicio || !dataFim) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, informe as datas de início e fim.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        const dataInicioFormatada = dataInicio.split('-').reverse().join('/');
                        const dataFimFormatada = dataFim.split('-').reverse().join('/');
                        
                        // Não precisa de confirmação, vai direto para preview
                        this.submit();
                    });
                });
            });
        </script>
    @endsection