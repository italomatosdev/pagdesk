@yield('css')
<!-- Bootstrap Css -->
<link href="{{ asset('build/css/bootstrap.min.css') }}" id="bootstrap-style" rel="stylesheet" type="text/css" />
<!-- Icons Css -->
<link href="{{ asset('build/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
<!-- Sweet Alert-->
<link href="{{ asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<!-- App Css-->
<link href="{{ asset('build/css/app.min.css') }}" id="app-style" rel="stylesheet" type="text/css" />
<!-- Global Search Styles -->
<style>
    .search-results-container .list-group-item {
        border-left: none;
        border-right: none;
        transition: background-color 0.2s;
    }
    .search-results-container .list-group-item:hover,
    .search-results-container .list-group-item.active {
        background-color: #f8f9fa;
    }
    .search-results-container .list-group-item:first-child {
        border-top: none;
    }
    .search-results-container .list-group-item:last-child {
        border-bottom: none;
    }
    #global-search-input:focus {
        border-color: #038edc;
        box-shadow: 0 0 0 0.2rem rgba(3, 142, 220, 0.25);
    }
    
    /* Ajustar tamanho das setas de paginação */
    .pagination .pagination-arrow,
    .pagination a.pagination-arrow,
    .pagination span.pagination-arrow,
    .pagination li:first-child .page-link,
    .pagination li:last-child .page-link,
    .pagination li:first-child a,
    .pagination li:last-child a,
    .pagination li:first-child span,
    .pagination li:last-child span {
        font-size: 0.875rem !important;
        padding: 0.375rem 0.5rem !important;
        line-height: 1.2 !important;
        display: inline-block;
    }
    
    /* Reduzir tamanho dos SVGs nas setas de paginação - sobrescrever classes Tailwind */
    .pagination svg.w-5,
    .pagination svg.h-5,
    .pagination svg[class*="w-5"],
    .pagination svg[class*="h-5"],
    nav svg.w-5,
    nav svg.h-5,
    nav[aria-label] svg {
        width: 0.875rem !important;
        height: 0.875rem !important;
        max-width: 0.875rem !important;
        max-height: 0.875rem !important;
    }
    
    /* Reduzir tamanho geral dos SVGs na paginação */
    .pagination svg,
    nav[role="navigation"] svg {
        width: 0.875rem !important;
        height: 0.875rem !important;
        max-width: 0.875rem !important;
        max-height: 0.875rem !important;
    }
    
    /* Reduzir tamanho dos caracteres de seta */
    .pagination li:first-child,
    .pagination li:last-child {
        font-size: 0.875rem !important;
    }
    
    .pagination li:first-child .page-link,
    .pagination li:last-child .page-link,
    .pagination li:first-child a,
    .pagination li:last-child a {
        font-size: 0.875rem !important;
        min-width: auto !important;
        width: auto !important;
        padding: 0.375rem 0.5rem !important;
    }
    
    /* Loading Overlay Global */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    
    .loading-overlay .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.3rem;
    }
    
    .loading-overlay p {
        color: #fff;
        margin-top: 1rem;
        font-size: 1.1rem;
        font-weight: 500;
    }
    
    /* Loading em botões */
    .btn-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.7;
    }
    
    .btn-loading .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.15rem;
        margin-right: 0.5rem;
    }
    
    /* Loading inline em campos */
    .field-loading {
        position: relative;
    }
    
    .field-loading::after {
        content: '';
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        border: 2px solid #038edc;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    /* Skeleton loading */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s ease-in-out infinite;
        border-radius: 4px;
    }
    
    @keyframes loading {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }
    
    .skeleton-text {
        height: 1rem;
        margin-bottom: 0.5rem;
    }
    
    .skeleton-title {
        height: 1.5rem;
        width: 60%;
        margin-bottom: 1rem;
    }
    
    /* Corrigir cor do texto e ícone ativo no sidebar colapsado - texto branco, bg padrão do sidebar */
    body[data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a {
        color: #fff !important; /* Texto branco */
    }
    
    body[data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a,
    body[data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a *,
    body[data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a span,
    body[data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a .menu-item {
        color: #fff !important; /* Texto branco - todos os elementos dentro do link */
    }
    
    body[data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a .nav-icon {
        fill: #fff !important; /* Ícone branco */
    }
    
    body[data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a i {
        color: #fff !important; /* Ícone (tag i) branco */
    }
    
    /* Para sidebar dark quando colapsado */
    body[data-sidebar="dark"][data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a {
        color: #fff !important; /* Texto branco */
    }
    
    body[data-sidebar="dark"][data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a,
    body[data-sidebar="dark"][data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a *,
    body[data-sidebar="dark"][data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a span,
    body[data-sidebar="dark"][data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a .menu-item {
        color: #fff !important; /* Texto branco - todos os elementos dentro do link */
    }
    
    body[data-sidebar="dark"][data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a .nav-icon {
        fill: #fff !important; /* Ícone branco */
    }
    
    body[data-sidebar="dark"][data-sidebar-size="sm"] #sidebar-menu > ul > li.mm-active > a i {
        color: #fff !important; /* Ícone (tag i) branco */
    }
</style>