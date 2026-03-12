<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Radar — Consulta cadastral interna (Serasa/SPC do sistema).
 * Mesmos dados do modal de verificação de CPF, exibidos em tela cheia.
 */
class RadarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasAnyRole(['administrador', 'gestor', 'consultor'])) {
                abort(403, 'Acesso negado. Apenas administradores, gestores e consultores podem acessar o Radar.');
            }
            return $next($request);
        });
    }

    /**
     * Exibe o formulário de busca ou o resultado da consulta por CPF/CNPJ.
     */
    public function index(Request $request): View
    {
        $documento = $request->input('documento');
        $existe = false;
        $cliente = null;
        $ficha = null;
        $consultaCruzada = false;
        $error = null;

        if ($documento !== null && $documento !== '') {
            $fakeRequest = Request::create(
                route('clientes.buscar.cpf'),
                'GET',
                ['cpf' => $documento]
            );
            $fakeRequest->setUserResolver(fn () => auth()->user());

            try {
                $response = app(ClienteController::class)->buscarPorCpf($fakeRequest);

                if ($response->getStatusCode() !== Response::HTTP_OK) {
                    $error = 'Não foi possível consultar o documento no momento.';
                } else {
                    $data = $response->getData(true);
                    if (!empty($data['error'])) {
                        $error = $data['error'];
                    } elseif (isset($data['existe'])) {
                        $existe = (bool) $data['existe'];
                        $cliente = $data['cliente'] ?? null;
                        $ficha = $data['ficha'] ?? null;
                        $consultaCruzada = (bool) ($data['consulta_cruzada'] ?? false);
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Radar: erro ao consultar documento', [
                    'documento' => $documento,
                    'error' => $e->getMessage(),
                ]);
                $error = 'Erro ao consultar. Tente novamente.';
            }
        }

        return view('radar.index', compact('documento', 'existe', 'cliente', 'ficha', 'consultaCruzada', 'error'));
    }
}
