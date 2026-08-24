<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class PessoaSalvarExceptionService
{
    public function mapear(Throwable $exception): ?ValidationException
    {
        if ($exception instanceof LogicException) {
            return $this->mapearLogica($exception);
        }

        if (! $exception instanceof QueryException) {
            return null;
        }

        $mensagem = Str::lower($exception->getMessage());

        $mapeamentos = [
            [
                ['users_email_unique', 'unique constraint failed: users.email', 'for key \'users.email\''],
                'email',
                'Este e-mail já pertence a outra conta de usuário. Use outro e-mail ou regularize a conta existente.',
            ],
            [
                ['servidores_cpf_unique', 'unique constraint failed: servidores.cpf'],
                'cpf',
                'Este CPF já está vinculado a outra pessoa, inclusive entre os cadastros arquivados.',
            ],
            [
                ['uniq_servidores_email_normalizado', 'uq_servidores_email_normalizado', 'unique constraint failed: servidores.email_normalizado'],
                'email',
                'Este e-mail já está vinculado a outra pessoa, inclusive entre os cadastros arquivados.',
            ],
            [
                ['uniq_professor_matriculas_servidor_matricula', 'unique constraint failed: professor_matriculas.servidor_id, professor_matriculas.matricula'],
                'matriculas',
                'O número de matrícula está repetido para esta pessoa. Revise as matrículas informadas.',
            ],
            [
                ['professores_id_escola_matricula_unique', 'unique constraint failed: professores.id_escola, professores.matricula'],
                'matriculas',
                'Esta matrícula já possui uma lotação para a escola selecionada.',
            ],
        ];

        foreach ($mapeamentos as [$agulhas, $campo, $texto]) {
            if (collect($agulhas)->contains(fn (string $agulha): bool => str_contains($mensagem, $agulha))) {
                Log::warning('Conflito de integridade ao salvar pessoa.', [
                    'campo' => $campo,
                    'sql_state' => $exception->errorInfo[0] ?? null,
                    'codigo_banco' => $exception->errorInfo[1] ?? null,
                ]);

                return ValidationException::withMessages([$campo => $texto]);
            }
        }

        if (($exception->errorInfo[0] ?? null) === '23000' || str_contains($mensagem, 'constraint failed')) {
            Log::warning('Restrição de integridade não mapeada ao salvar pessoa.', [
                'sql_state' => $exception->errorInfo[0] ?? null,
                'codigo_banco' => $exception->errorInfo[1] ?? null,
            ]);

            return ValidationException::withMessages([
                'formulario' => 'Os dados entram em conflito com um cadastro ou vínculo existente. Revise CPF, e-mail, matrículas, escolas e turmas.',
            ]);
        }

        return null;
    }

    public function registrarInesperada(Throwable $exception, ?int $pessoaId, ?int $usuarioId): string
    {
        $protocolo = Str::upper(Str::substr((string) Str::uuid(), 0, 8));

        Log::error('Falha inesperada ao salvar pessoa.', [
            'protocolo' => $protocolo,
            'pessoa_id' => $pessoaId,
            'usuario_id' => $usuarioId,
            'exception' => $exception,
        ]);

        return $protocolo;
    }

    private function mapearLogica(LogicException $exception): ?ValidationException
    {
        $mensagem = Str::lower($exception->getMessage());

        if (str_contains($mensagem, 'já está vinculada a outra pessoa')) {
            return ValidationException::withMessages([
                'email' => 'A conta encontrada para este e-mail já está vinculada a outra pessoa. Regularize o vínculo da conta antes de salvar.',
            ]);
        }

        if (str_contains($mensagem, 'role equipe gestora ainda não foi criada')) {
            return ValidationException::withMessages([
                'cargo' => 'O nível de acesso da Equipe Gestora ainda não está configurado. Execute a sincronização de permissões antes de salvar.',
            ]);
        }

        return null;
    }
}
