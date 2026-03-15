<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Mostrar perfil do usuário logado
     */
    public function show(): View
    {
        $user = auth()->user()->load(['roles', 'operacoes']);
        return view('profile.show', compact('user'));
    }

    /**
     * Página "Minhas operações" – lista as operações que o usuário faz parte.
     * Acesso apenas pelo dropdown do usuário (não está no sidebar).
     */
    public function operacoes(): View
    {
        $user = auth()->user();
        $operacoes = $user->operacoes()->orderBy('nome')->get();
        return view('profile.operacoes', compact('operacoes'));
    }

    /**
     * Atualizar perfil do usuário logado
     */
    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Atualizar nome e email
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Atualizar senha se fornecida
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('profile.show')
            ->with('success', 'Perfil atualizado com sucesso!');
    }

    /**
     * Atualizar apenas o avatar do usuário (formulário separado)
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,jpg,png|max:5120', // 5MB
        ]);

        $user = auth()->user();

        // Deletar avatar antigo se existir
        if ($user->avatar && Storage::disk('public')->exists('avatars/' . $user->avatar)) {
            Storage::disk('public')->delete('avatars/' . $user->avatar);
        }

        $file = $request->file('avatar');
        $filename = 'user_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('avatars', $filename, 'public');

        $user->avatar = $filename;
        $user->save();

        return redirect()->route('profile.show')
            ->with('success', 'Foto de perfil atualizada com sucesso!');
    }

    /**
     * Remover avatar do usuário
     */
    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user->avatar && Storage::disk('public')->exists('avatars/' . $user->avatar)) {
            Storage::disk('public')->delete('avatars/' . $user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return redirect()->route('profile.show')
            ->with('success', 'Avatar removido com sucesso!');
    }
}
