@extends('layouts.master-without-nav')
@section('title')
    Erro 404
@endsection
@section('page-title')
    Erro 404
@endsection
@section('body')

    <body>
    @endsection
    @section('content')

    <div class="min-vh-100" style="background: url(build/images/bg-2.png) top;">
        <div class="bg-overlay bg-light"></div>
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="text-center py-5 mt-5">
                       <div class="position-relative">
                          <h1 class="error-title error-text mb-0">404</h1>
                          <h4 class="error-subtitle text-uppercase mb-0">Ops! Página não encontrada</h4>
                          <p class="font-size-16 mx-auto text-muted w-50 mt-4">A página que você procura não existe ou foi movida.</p>
                       </div>
                        <div class="mt-4 text-center">
                            @auth
                                <a class="btn btn-primary" href="{{ route('dashboard.index') }}">Voltar ao painel</a>
                            @else
                                <a class="btn btn-primary" href="{{ url('/login') }}">Ir para o login</a>
                            @endauth
                        </div>
                    </div>
                </div>
                <!-- end col -->
            </div>
            <!-- end row -->
        </div>
        <!-- end container -->
    </div>
    <!-- end authentication section -->

@endsection