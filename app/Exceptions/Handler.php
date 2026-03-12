<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Log::channel('structured')->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Tratar PostTooLargeException com mensagem mais clara
        if ($e instanceof PostTooLargeException) {
            $postMaxSize = ini_get('post_max_size');
            $uploadMaxFilesize = ini_get('upload_max_filesize');
            
            $message = "Os arquivos enviados são muito grandes. ";
            $message .= "Limite atual do POST: {$postMaxSize}. ";
            $message .= "Limite por arquivo: {$uploadMaxFilesize}. ";
            $message .= "Por favor, edite o php.ini e aumente 'post_max_size' e 'upload_max_filesize'. ";
            $message .= "Veja o arquivo: docs/SOLUCAO_ERRO_UPLOAD.md";

            // Se for uma requisição de cliente, redirecionar de volta
            if ($request->is('clientes*')) {
                return redirect()->back()
                    ->with('error', $message)
                    ->withInput();
            }

            // Caso contrário, retornar resposta JSON ou view de erro
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'post_max_size' => $postMaxSize,
                    'upload_max_filesize' => $uploadMaxFilesize,
                ], 413);
            }

            return response()->view('errors.413', [
                'message' => $message,
                'post_max_size' => $postMaxSize,
                'upload_max_filesize' => $uploadMaxFilesize,
            ], 413);
        }

        return parent::render($request, $e);
    }
}
