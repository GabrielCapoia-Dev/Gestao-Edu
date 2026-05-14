<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ForcePasswordChangeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = Filament::auth()->user() ?: $request->user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getLoginUrl());
        }

        if (! $user->must_change_password) {
            return redirect()->to(Filament::getUrl());
        }

        $data = $request->validate(
            [
                'password' => [
                    'required',
                    'confirmed',
                    PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
                ],
            ],
            [
                'password.required' => 'Informe a nova senha.',
                'password.confirmed' => 'As senhas devem ser iguais.',
                'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
                'password.mixed' => 'Use letras maiusculas e minusculas.',
                'password.numbers' => 'Use pelo menos um numero.',
                'password.symbols' => 'Use pelo menos um caractere especial.',
            ],
            [
                'password' => 'senha',
                'password_confirmation' => 'confirmacao da senha',
            ],
        );

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'must_change_password' => false,
            ])->saveOrFail();
        });

        Filament::auth()->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->to(Filament::getLoginUrl())
            ->with('status', 'Senha redefinida. Entre novamente usando a nova senha.');
    }
}
