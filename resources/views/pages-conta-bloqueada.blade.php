@extends('layouts.master-without-nav')
@section('title')
    Conta bloqueada
@endsection
@section('page-title')
    Conta bloqueada
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
                                        <div class="avatar-lg mx-auto rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center">
                                            <i class="bx bx-lock-alt font-size-48 text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center text-muted my-5">
                                <h4>Sua conta está bloqueada</h4>
                                <p class="mb-2">Você não tem permissão para acessar o sistema no momento.</p>
                                @if(!empty($motivo))
                                    <p class="text-muted small mb-0"><strong>Motivo:</strong> {{ $motivo }}</p>
                                @endif
                                <p class="mt-3 mb-0 small">Entre em contato com o administrador para mais informações.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
