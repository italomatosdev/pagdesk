<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Máximo de tentativas de login antes do lockout (anti brute-force).
     */
    protected int $maxAttempts = 5;

    /**
     * Minutos de lockout após exceder maxAttempts.
     */
    protected int $decayMinutes = 2;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('locale.pt_BR');
    }

    /**
     * Mensagem de falha de login sempre em português (lang/pt_BR/auth.php).
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => [Lang::get('auth.failed', [], 'pt_BR')],
        ]);
    }

    /**
     * Mensagem de throttle (muitas tentativas) sempre em português (lang/pt_BR/auth.php).
     */
    protected function sendLockoutResponse(Request $request)
    {
        $seconds = $this->limiter()->availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            $this->username() => [Lang::get('auth.throttle', ['seconds' => $seconds], 'pt_BR')],
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }
}
