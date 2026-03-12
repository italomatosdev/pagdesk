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
     * Atualizar perfil do usuário logado
     */
    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png|max:2048', // 2MB
        ]);

        // Atualizar nome e email
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Atualizar senha se fornecida
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // Upload de avatar
        if ($request->hasFile('avatar')) {
            // Deletar avatar antigo se existir
            if ($user->avatar && Storage::disk('public')->exists('avatars/' . $user->avatar)) {
                Storage::disk('public')->delete('avatars/' . $user->avatar);
            }

            // Fazer upload do novo avatar
            $file = $request->file('avatar');
            $filename = 'user_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $filename, 'public');
            
            $user->avatar = $filename;
        }

        $user->save();

        return redirect()->route('profile.show')
            ->with('success', 'Perfil atualizado com sucesso!');
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
