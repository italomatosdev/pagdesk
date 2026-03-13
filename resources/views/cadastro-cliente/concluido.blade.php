@extends('layouts.master-without-nav')
@section('title')
    Cadastro realizado
@endsection
@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow text-center">
                    <div class="card-body py-5">
                        @if(session('success'))
                            <div class="mb-4">
                                <i class="bx bx-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h4 class="card-title">Cadastro realizado com sucesso!</h4>
                            <p class="text-muted mb-0">{{ session('success') }}</p>
                        @else
                            <p class="text-muted mb-0">Nenhuma informação para exibir.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
