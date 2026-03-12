@extends('layouts.master-without-nav')
@section('title')
    Redefinir Senha
@endsection
@section('page-title')
    Redefinir Senha
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="authentication-bg min-vh-100">
            <div class="bg-overlay bg-light"></div>
            <div class="container">
                <div class="d-flex flex-column min-vh-100 px-3 pt-4">
                    <div class="row justify-content-center my-auto">
                        <div class="col-md-8 col-lg-6 col-xl-5">

                            <div class="mb-4 pb-2">
                                <a href="{{ url('/') }}" class="d-block auth-logo">
                                    <img src="{{ URL::asset('build/images/logo-dark.png') }}" alt="" height="30"
                                        class="auth-logo-dark me-start">
                                    <img src="{{ URL::asset('build/images/logo-light.png') }}" alt="" height="30"
                                        class="auth-logo-light me-start">
                                </a>
                            </div>

                            <div class="card">
                                <div class="card-body p-4">
                                    <div class="text-center mt-2">
                                        <h5>Redefinir senha</h5>
                                        <p class="text-muted">Informe sua nova senha abaixo.</p>
                                    </div>
                                    <div class="p-2 mt-4">
                                        <form method="POST" action="{{ route('password.update') }}" class="auth-input">
                                            @csrf
                                            <input type="hidden" name="token" value="{{ $token }}">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                                <input id="email" type="email"
                                                    class="form-control @error('email') is-invalid @enderror"
                                                    name="email" value="{{ $email ?? old('email') }}" required
                                                    autocomplete="email" autofocus>
                                                @error('email')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="password-input">Nova senha <span class="text-danger">*</span></label>
                                                <input type="password"
                                                    class="form-control @error('password') is-invalid @enderror"
                                                    name="password" placeholder="Digite a nova senha" id="password-input"
                                                    aria-describedby="passwordInput" required>
                                                @error('password')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                                <div id="passwordInput" class="form-text">Mínimo de 8 caracteres.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label" for="confirm-password-input">Confirmar senha <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" name="password_confirmation"
                                                    placeholder="Confirme a nova senha" id="confirm-password-input"
                                                    required>
                                            </div>

                                            <div class="mt-4">
                                                <button class="btn btn-primary w-100" type="submit">Redefinir senha</button>
                                            </div>

                                            <div class="mt-4 text-center">
                                                <p class="mb-0">Lembrou a senha? <a href="{{ route('login') }}"
                                                        class="fw-medium text-primary"> Entrar</a></p>
                                            </div>

                                        </form>
                                    </div>

                                </div>
                            </div>

                        </div><!-- end col -->
                    </div><!-- end row -->

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="text-center p-4">
                                <p>©
                                    <script>
                                        document.write(new Date().getFullYear())
                                    </script> PagDesk. Todos os direitos reservados.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div><!-- end container -->
        </div>
        <!-- end authentication section -->
    @endsection
    @section('scripts')
        <script src="{{ URL::asset('build/js/pages/pass-addon.init.js') }}"></script>
    @endsection
