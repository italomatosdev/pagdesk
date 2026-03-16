@extends('layouts.master-without-nav')
@section('title')
    Sistema em manutenção
@endsection
@section('page-title')
    Sistema em manutenção
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="authentication-bg min-vh-100">
            <div class="bg-overlay bg-light"></div>
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex flex-column min-vh-100">
                            <div class="my-auto py-5">
                                <div class="text-center mb-4 pb-1">
                                    <a href="{{ route('login') }}" class="d-block auth-logo">
                                        <img src="{{ URL::asset('build/images/logo-dark.png') }}" alt="" height="36"
                                            class="auth-logo-dark">
                                        <img src="{{ URL::asset('build/images/logo-light.png') }}" alt="" height="36"
                                            class="auth-logo-light">
                                    </a>
                                </div>
                                <div class="row align-items-center justify-content-center">
                                    <div class="col-md-5">
                                        <div class="mt-4">
                                            <img src="{{ URL::asset('build/images/maintenance.png') }}" class="img-fluid" alt="">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center text-muted my-5">
                                    <h4>Sistema em manutenção</h4>
                                    <p>Voltamos em breve. Obrigado pela compreensão.</p>
                                    <a href="{{ route('manutencao') }}" class="btn btn-primary mt-3">
                                        <i class="bx bx-refresh me-1"></i> Atualizar
                                    </a>
                                </div>

                                <div class="row justify-content-center">
                                    <div class="col-md-5 mb-3 mb-md-0">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="text-primary">01.</h5>
                                                <h5 class="font-size-16 text-uppercase mt-3">Por que o sistema está fora?</h5>
                                                <p class="text-muted mb-0">Estamos realizando melhorias e atualizações para oferecer um serviço ainda melhor.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-5">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="text-primary">02.</h5>
                                                <h5 class="font-size-16 text-uppercase mt-3">Quanto tempo vai levar?</h5>
                                                <p class="text-muted mb-0">O tempo de manutenção é variável. Tente acessar novamente em alguns minutos.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
