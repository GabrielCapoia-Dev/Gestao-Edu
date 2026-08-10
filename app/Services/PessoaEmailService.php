<?php

namespace App\Services;

use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PessoaEmailService
{
    public function assertDisponivel(Pessoa $pessoa): void
    {
        $email = Pessoa::normalizarEmail($pessoa->getAttribute('email'));

        if ($email === null) {
            return;
        }

        $emailOriginal = Pessoa::normalizarEmail($pessoa->getRawOriginal('email'));

        // Um duplicado legado continua editável enquanto a identidade do e-mail
        // não mudar. Qualquer troca volta a exigir disponibilidade global.
        if ($pessoa->exists && $email === $emailOriginal) {
            return;
        }

        $duplicado = Pessoa::withTrashed()
            ->where('email_normalizado', $email)
            ->when($pessoa->exists, fn (Builder $query): Builder => $query->whereKeyNot($pessoa->getKey()))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages([
                'email' => 'Este e-mail já está vinculado a outra pessoa, inclusive entre os cadastros arquivados.',
            ]);
        }
    }
}
