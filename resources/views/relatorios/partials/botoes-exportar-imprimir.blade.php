@props([
    'exportRoute',
    'query' => null,
])
@php
    $q = $query ?? request()->query();
@endphp
<div class="no-print btn-group flex-shrink-0">
    <a href="{{ route($exportRoute, $q) }}" class="btn btn-outline-success btn-sm" title="Exportar CSV com os mesmos filtros">
        <i class="bx bx-export"></i> Exportar CSV
    </a>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Imprimir esta página">
        <i class="bx bx-printer"></i> Imprimir
    </button>
</div>
