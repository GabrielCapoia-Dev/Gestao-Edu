<?php

namespace App\Console\Commands;

use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Services\PessoaUsuarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class SincronizarAcessosPessoas extends Command
{
    protected $signature = 'pessoas:sincronizar-acessos
        {--apply : Aplica as alterações; sem esta opção a execução é somente leitura}
        {--report= : Caminho do relatório JSON}';

    protected $description = 'Sincroniza o status único, o cargo obrigatório e o Usuário de cada Pessoa.';

    public function handle(PessoaUsuarioService $usuarios): int
    {
        $apply = (bool) $this->option('apply');
        $report = [
            'modo' => $apply ? 'apply' : 'dry-run',
            'gerado_em' => now()->toISOString(),
            'totais' => [
                'pessoas' => 0,
                'usuarios_criados_ou_vinculados' => 0,
                'ja_sincronizados' => 0,
                'inativados' => 0,
                'sem_email' => 0,
                'email_invalido' => 0,
                'email_duplicado' => 0,
                'sem_cargo' => 0,
                'usuario_arquivado' => 0,
                'erros' => 0,
                'usuarios_sem_pessoa' => 0,
                'usuarios_convertidos_em_pessoa' => 0,
                'usuarios_inativos_sem_email' => 0,
                'cargos_pendentes_criados' => 0,
            ],
            'pendencias' => [],
        ];

        $this->sincronizarUsuariosSemPessoa($apply, $report);

        Pessoa::withTrashed()
            ->orderBy('id')
            ->chunkById(100, function ($pessoas) use ($apply, $usuarios, &$report): void {
                foreach ($pessoas as $pessoa) {
                    $report['totais']['pessoas']++;
                    $motivos = $this->motivosPendencia($pessoa);

                    if ($motivos !== []) {
                        foreach ($motivos as $motivo) {
                            $report['totais'][$motivo]++;
                        }

                        $report['pendencias'][] = [
                            'pessoa_id' => $pessoa->id,
                            'motivos' => $motivos,
                        ];

                        if ($apply && $pessoa->status !== Pessoa::STATUS_INATIVO) {
                            $pessoa->forceFill(['status' => Pessoa::STATUS_INATIVO])->save();
                            $report['totais']['inativados']++;
                        }

                        if ($apply) {
                            try {
                                DB::transaction(function () use ($pessoa, $motivos, $usuarios, &$report): void {
                                    $pessoaAtual = Pessoa::withTrashed()->findOrFail($pessoa->id);

                                    if (in_array('sem_cargo', $motivos, true)) {
                                        if ($this->garantirCargoPendente($pessoaAtual)) {
                                            $report['totais']['cargos_pendentes_criados']++;
                                        }
                                    }

                                    if (blank($pessoaAtual->user_id)) {
                                        $possuiEmailUtilizavel = ! in_array('sem_email', $motivos, true)
                                            && ! in_array('email_invalido', $motivos, true)
                                            && ! in_array('email_duplicado', $motivos, true);

                                        if ($possuiEmailUtilizavel) {
                                            $usuarios->garantirUsuario($pessoaAtual->fresh());
                                        } else {
                                            $usuarios->garantirUsuarioInativoSemEmail($pessoaAtual->fresh());
                                            $report['totais']['usuarios_inativos_sem_email']++;
                                        }
                                    }
                                });
                            } catch (Throwable $exception) {
                                report($exception);
                                $report['totais']['erros']++;
                                $report['pendencias'][] = [
                                    'pessoa_id' => $pessoa->id,
                                    'motivos' => ['erro_ao_tratar_pendencia'],
                                ];
                            }
                        }

                        continue;
                    }

                    if (! $apply) {
                        $pessoa->user_id
                            ? $report['totais']['ja_sincronizados']++
                            : $report['totais']['usuarios_criados_ou_vinculados']++;
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($usuarios, $pessoa, &$report): void {
                            $jaVinculado = filled($pessoa->user_id);
                            $usuarios->garantirUsuario($pessoa);
                            $report['totais'][$jaVinculado
                                ? 'ja_sincronizados'
                                : 'usuarios_criados_ou_vinculados']++;
                        });
                    } catch (Throwable $exception) {
                        report($exception);
                        $report['totais']['erros']++;
                        $report['pendencias'][] = [
                            'pessoa_id' => $pessoa->id,
                            'motivos' => ['erro_ao_sincronizar'],
                        ];
                    }
                }
            });

        $this->salvarRelatorio($report);
        $this->table(
            ['Indicador', 'Quantidade'],
            collect($report['totais'])->map(fn (int $valor, string $chave): array => [$chave, $valor])->values()->all(),
        );

        return $report['totais']['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    private function motivosPendencia(Pessoa $pessoa): array
    {
        $motivos = [];
        $email = Pessoa::normalizarEmail($pessoa->email);

        if ($email === null) {
            $motivos[] = 'sem_email';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $motivos[] = 'email_invalido';
        } elseif (Pessoa::withTrashed()->where('email_normalizado', $email)->whereKeyNot($pessoa->id)->exists()) {
            $motivos[] = 'email_duplicado';
        }

        $possuiCargo = $pessoa->professores()->where('ativo', true)->exists()
            || $pessoa->vinculosAtivos()
                ->whereHas('funcaoAdministrativa', fn ($cargos) => $cargos
                    ->where('codigo', '<>', Pessoa::CARGO_PENDENTE_CODIGO))
                ->exists();

        if (! $possuiCargo) {
            $motivos[] = 'sem_cargo';
        }

        if ($pessoa->user?->trashed()) {
            $motivos[] = 'usuario_arquivado';
        }

        return $motivos;
    }

    /** @param array<string, mixed> $report */
    private function sincronizarUsuariosSemPessoa(bool $apply, array &$report): void
    {
        User::withTrashed()
            ->whereDoesntHave('servidores', fn ($pessoas) => $pessoas->withTrashed())
            ->with('roles:id,name')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($apply, &$report): void {
                foreach ($users as $user) {
                    $report['totais']['usuarios_sem_pessoa']++;
                    $email = Pessoa::normalizarEmail($user->email);
                    $pessoasMesmoEmail = $email
                        ? Pessoa::withTrashed()->where('email_normalizado', $email)->limit(2)->get()
                        : collect();

                    if ($pessoasMesmoEmail->count() === 1 && blank($pessoasMesmoEmail->first()->user_id)) {
                        if ($apply) {
                            $pessoasMesmoEmail->first()->forceFill(['user_id' => $user->id])->save();
                        }
                        $report['totais']['usuarios_convertidos_em_pessoa']++;
                        continue;
                    }

                    if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $pessoasMesmoEmail->isNotEmpty()) {
                        $report['pendencias'][] = [
                            'user_id' => $user->id,
                            'motivos' => ['usuario_sem_pessoa_com_email_indisponivel'],
                        ];
                        continue;
                    }

                    if (! $apply) {
                        $report['totais']['usuarios_convertidos_em_pessoa']++;
                        continue;
                    }

                    DB::transaction(function () use ($user, $email): void {
                        $role = $user->roles->sortBy(fn ($role): int => $role->name === 'Admin' ? 0 : 1)->first();
                        $cargoNome = $role?->name ?: 'Visitante';
                        $cargoCodigo = 'usuario_'.(string) Str::of($cargoNome)->ascii()->snake()->limit(40, '');
                        $funcao = FuncaoAdministrativa::query()->firstOrCreate(
                            ['codigo' => $cargoCodigo],
                            [
                                'nome' => $cargoNome,
                                'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
                                'ativo' => true,
                                'exige_professor' => false,
                                'concede_acesso_sistema' => true,
                            ],
                        );

                        if ($role) {
                            $funcao->rolesPadrao()->syncWithoutDetaching([$role->id]);
                        }

                        $pessoa = Pessoa::query()->create([
                            'user_id' => $user->id,
                            'nome' => $user->name,
                            'email' => $email,
                            'status' => $user->trashed() ? Pessoa::STATUS_INATIVO : Pessoa::STATUS_ATIVO,
                        ]);
                        ServidorFuncaoAdministrativa::query()->create([
                            'servidor_id' => $pessoa->id,
                            'funcao_administrativa_id' => $funcao->id,
                            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                            'origem' => 'backfill_usuario',
                        ]);
                    });

                    $report['totais']['usuarios_convertidos_em_pessoa']++;
                }
            });
    }

    private function garantirCargoPendente(Pessoa $pessoa): bool
    {
        $funcao = FuncaoAdministrativa::query()->firstOrCreate(
            ['codigo' => Pessoa::CARGO_PENDENTE_CODIGO],
            [
                'nome' => 'Cargo pendente de regularização',
                'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
                'ativo' => true,
                'exige_professor' => false,
                'concede_acesso_sistema' => true,
            ],
        );

        $vinculo = ServidorFuncaoAdministrativa::query()->firstOrCreate(
            [
                'servidor_id' => $pessoa->id,
                'funcao_administrativa_id' => $funcao->id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            ],
            ['origem' => 'backfill_cargo_pendente'],
        );

        return $vinculo->wasRecentlyCreated;
    }

    /** @param array<string, mixed> $report */
    private function salvarRelatorio(array $report): void
    {
        $option = trim((string) $this->option('report'));
        if ($option === '') {
            return;
        }

        $path = str_starts_with($option, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $option)
            ? $option
            : base_path($option);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->line("Relatório: {$path}");
    }
}
