{{-- @include: tipo, parentId, anexos (collection), canUpload, modalSuffix; opcional: context --}}
@php
    $context = $context ?? null;
    $modalSuffix = $modalSuffix ?? 'default';
    $modalId = 'modalComprovanteExtras' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $modalSuffix);
    $redirectUrl = request()->getRequestUri();
@endphp

@if($canUpload || $anexos->isNotEmpty())
<div class="mt-2 pt-2 border-top border-light">
    <small class="text-muted d-block mb-1">Comprovantes adicionais</small>
    @if($anexos->isNotEmpty())
        <ul class="list-unstyled small mb-2 ps-0">
            @foreach($anexos as $anexo)
                <li class="mb-1">
                    <a href="{{ $anexo->urlAsset() }}" target="_blank" rel="noopener">
                        <i class="bx bx-file"></i> {{ $anexo->original_name ?: 'Arquivo #'.$anexo->id }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if($canUpload)
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" title="Adicionar mais comprovantes">
            <i class="bx bx-plus"></i>
        </button>
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('comprovante-anexos.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="tipo" value="{{ $tipo }}">
                        <input type="hidden" name="id" value="{{ $parentId }}">
                        @if($context)
                            <input type="hidden" name="context" value="{{ $context }}">
                        @endif
                        <input type="hidden" name="redirect" value="{{ $redirectUrl }}">
                        <div class="modal-header">
                            <h5 class="modal-title">Comprovantes adicionais</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-2">PDF, JPG ou PNG (máx. 2MB por arquivo). Os arquivos somam aos já enviados.</p>
                            <input type="file" name="comprovantes_extras[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="bx bx-upload"></i> Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endif
