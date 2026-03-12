@extends('layouts.master')
@section('title')
    Nova Operação - {{ $empresa->nome }}
@endsection
@section('page-title')
    Criar Operação para: {{ $empresa->nome }}
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Criar Nova Operação</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="bx bx-info-circle"></i> 
                            <strong>Empresa:</strong> {{ $empresa->nome }}
                        </div>

                        <form action="{{ route('super-admin.empresas.operacoes.store', $empresa->id) }}" method="POST" class="form-criar-operacao" data-no-loading>
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control" 
                                       value="{{ old('nome') }}" required>
                                @error('nome')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" name="codigo" class="form-control" 
                                       placeholder="OP001" 
                                       value="{{ old('codigo') }}">
                                <small class="text-muted">Código único da operação (opcional)</small>
                                @error('codigo')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3">{{ old('descricao') }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Valor de Aprovação Automática</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" id="valor_aprovacao_automatica" name="valor_aprovacao_automatica" class="form-control" inputmode="decimal"
                                           data-mask-money="brl" placeholder="0,00" value="{{ old('valor_aprovacao_automatica') }}">
                                </div>
                                <small class="text-muted">
                                    Empréstimos com valor menor ou igual a este valor serão aprovados automaticamente, 
                                    ignorando dívida ativa e limite de crédito. Deixe em branco para desabilitar.
                                </small>
                                @error('valor_aprovacao_automatica')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr>

                            <h5 class="mb-3">Documentos obrigatórios na criação de cliente</h5>
                            <p class="text-muted small mb-2">
                                Selecione quais documentos serão obrigatórios ao vincular um cliente a esta operação.
                            </p>
                            @foreach(\App\Modules\Core\Models\OperacaoDocumentoObrigatorio::tiposDisponiveis() as $chave => $label)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="documentos_obrigatorios[]" 
                                           id="doc_obr_{{ $chave }}" value="{{ $chave }}"
                                           {{ in_array($chave, old('documentos_obrigatorios', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="doc_obr_{{ $chave }}">{{ $label }}</label>
                                </div>
                            @endforeach

                            <hr>

                            <h5 class="mb-3">Configurações de Workflow</h5>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="requer_aprovacao" id="requer_aprovacao" 
                                           value="1" {{ old('requer_aprovacao', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="requer_aprovacao">
                                        <strong>Requer Aprovação Manual</strong>
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Se marcado, empréstimos precisarão ser aprovados manualmente por um administrador/gestor antes de serem liberados.
                                </small>
                                @error('requer_aprovacao')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="requer_liberacao" id="requer_liberacao" 
                                           value="1" {{ old('requer_liberacao', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="requer_liberacao">
                                        <strong>Requer Liberação do Gestor</strong>
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Se marcado, após aprovação, um gestor precisará liberar o dinheiro antes do pagamento ao cliente.
                                </small>
                                @error('requer_liberacao')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="requer_autorizacao_pagamento_produto" id="requer_autorizacao_pagamento_produto"
                                           value="1" {{ old('requer_autorizacao_pagamento_produto', false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="requer_autorizacao_pagamento_produto">
                                        <strong>Permitir pagamento em produto/objeto</strong>
                                    </label>
                                </div>
                                <small class="text-muted">Pagamento em espécie (não gera caixa); requer aceite de gestor/adm em Liberações.</small>
                                @error('requer_autorizacao_pagamento_produto')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr>

                            <h5 class="mb-3">Configuração de Juros por Atraso</h5>

                            <div class="mb-3">
                                <label class="form-label">Taxa de Juros por Atraso</label>
                                <div class="input-group">
                                    <input type="number" name="taxa_juros_atraso" class="form-control" 
                                           step="0.01" min="0" max="100"
                                           placeholder="0.00" 
                                           value="{{ old('taxa_juros_atraso', 0) }}">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">
                                    Taxa de juros aplicada em parcelas atrasadas. Exemplo: 1.5 = 1,5% ao dia (ou ao mês, conforme o tipo selecionado).
                                    <br>Deixe em 0 para desabilitar juros automáticos.
                                </small>
                                @error('taxa_juros_atraso')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Cálculo</label>
                                <select name="tipo_calculo_juros" class="form-select">
                                    <option value="por_dia" {{ old('tipo_calculo_juros', 'por_dia') === 'por_dia' ? 'selected' : '' }}>
                                        Por Dia
                                    </option>
                                    <option value="por_mes" {{ old('tipo_calculo_juros', 'por_dia') === 'por_mes' ? 'selected' : '' }}>
                                        Por Mês
                                    </option>
                                </select>
                                <small class="text-muted">
                                    <strong>Por Dia:</strong> A taxa será aplicada multiplicada pelos dias de atraso.
                                    <br><strong>Por Mês:</strong> A taxa será aplicada proporcionalmente aos dias (dias/30).
                                </small>
                                @error('tipo_calculo_juros')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativo" 
                                           id="ativo" value="1" 
                                           {{ old('ativo', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ativo">
                                        Operação Ativa
                                    </label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('super-admin.empresas.show', $empresa->id) }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-check"></i> Criar Operação
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
                document.querySelectorAll('.form-criar-operacao').forEach(form => {
                    // Usar capture: true para executar antes do listener do loading.js
                    const submitHandler = function(e) {
                        e.preventDefault();
                        e.stopImmediatePropagation(); // Impede que outros listeners executem
                        
                        const nome = this.querySelector('input[name="nome"]').value.trim();
                        
                        if (!nome) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, preencha o nome da operação.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        Swal.fire({
                            title: 'Criar Operação?',
                            html: `Deseja criar a operação <strong>${nome}</strong>?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, criar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Remover o listener customizado e o data-no-loading para permitir submit normal com loading
                                form.removeEventListener('submit', submitHandler, true);
                                form.removeAttribute('data-no-loading');
                                // Fazer submit normal (agora o loading vai aparecer)
                                form.submit();
                            }
                        });
                    };
                    
                    form.addEventListener('submit', submitHandler, true); // capture: true
                });
            });
        </script>
    @endsection
