<?php

namespace App\Console\Commands;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class ManageAvaliacaoLoadTestFixtures extends Command
{
    protected $signature = 'loadtest:avaliacoes
        {action=prepare : prepare, reset, verify ou restore}
        {--run-id= : Identificador seguro e unico da execucao}
        {--count=20 : Quantidade de professores}
        {--students=5 : Alunos sinteticos por turma}
        {--evaluation= : Avaliacao ativa; usa a mais recente quando omitida}
        {--force : Permite execucao em APP_ENV=production}';

    protected $description = 'Prepara, verifica e restaura fixtures reversiveis do teste de carga de avaliacoes.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Bloqueado em production. Use --force somente no Hub de Testes.');

            return self::FAILURE;
        }

        $runId = trim((string) $this->option('run-id'));

        if ($runId === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,80}$/', $runId)) {
            $this->error('Informe --run-id com 3 a 81 caracteres seguros.');

            return self::FAILURE;
        }

        return match (Str::lower((string) $this->argument('action'))) {
            'prepare' => $this->prepare($runId),
            'reset' => $this->reset($runId),
            'verify' => $this->verify($runId),
            'restore' => $this->restore($runId),
            default => $this->invalidAction(),
        };
    }

    private function prepare(string $runId): int
    {
        $directory = $this->directory($runId);
        $manifestPath = $directory.'/manifest.json';
        $csvPath = $directory.'/users.csv';

        if (is_file($manifestPath) && is_file($csvPath)) {
            $this->info('Fixtures ja preparadas.');
            $this->line($csvPath);

            return self::SUCCESS;
        }

        $count = max(1, (int) $this->option('count'));
        $studentsPerClass = max(1, (int) $this->option('students'));
        $evaluationId = $this->evaluationId();
        $targets = $this->selectTargets($evaluationId, $count);

        if ($targets->count() !== $count) {
            $this->error("Foram encontrados {$targets->count()} professores elegiveis; esperados {$count}.");

            return self::FAILURE;
        }

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('Nao foi possivel criar o diretorio privado das fixtures.');

            return self::FAILURE;
        }

        $password = 'Ge!'.Str::random(38);
        $passwordHash = Hash::make($password);
        $manifest = [];

        try {
            $manifest = DB::transaction(function () use (
                $targets,
                $runId,
                $evaluationId,
                $studentsPerClass,
                $passwordHash,
            ): array {
                $users = User::query()
                    ->whereIn('id', $targets->pluck('user_id')->all())
                    ->get()
                    ->keyBy(fn (User $user): int => (int) $user->getKey());
                $userSnapshots = [];

                foreach ($users as $user) {
                    $userSnapshots[] = [
                        'id' => (int) $user->getKey(),
                        'password' => (string) $user->getRawOriginal('password'),
                        'must_change_password' => (bool) $user->must_change_password,
                        'remember_token' => $user->getRawOriginal('remember_token'),
                        'last_login_at' => $user->getRawOriginal('last_login_at'),
                        'last_seen_at' => $user->getRawOriginal('last_seen_at'),
                    ];

                    DB::table('users')->where('id', $user->getKey())->update([
                        'password' => $passwordHash,
                        'must_change_password' => false,
                        'remember_token' => null,
                    ]);
                }

                $studentsByClass = [];

                foreach ($targets->pluck('turma_id')->unique()->values() as $classId) {
                    $studentsByClass[(int) $classId] = [];

                    for ($index = 1; $index <= $studentsPerClass; $index++) {
                        $suffix = Str::upper(substr(hash('sha256', "{$runId}:{$classId}:{$index}"), 0, 20));
                        $student = Aluno::query()->create([
                            'nome' => "LOADTEST {$runId} {$classId}-{$index}",
                            'cgm' => "LT{$suffix}",
                            'data_nascimento' => '2015-01-01',
                            'data_matricula' => now()->toDateString(),
                            'id_turma' => (int) $classId,
                            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
                            'permite_contra_turno' => false,
                            'status' => Aluno::STATUS_MATRICULADO,
                        ]);

                        $studentsByClass[(int) $classId][] = (int) $student->getKey();
                    }
                }

                return [
                    'schema' => 1,
                    'run_id' => $runId,
                    'evaluation_id' => $evaluationId,
                    'created_at' => now()->toIso8601String(),
                    'users' => $userSnapshots,
                    'student_ids' => collect($studentsByClass)->flatten()->values()->all(),
                    'targets' => $targets->map(fn (object $target): array => [
                        'user_id' => (int) $target->user_id,
                        'email' => (string) $target->email,
                        'turma_id' => (int) $target->turma_id,
                        'pauta_id' => (int) $target->pauta_id,
                        'alternativa_ids' => $target->alternativa_ids,
                        'aluno_ids' => $studentsByClass[(int) $target->turma_id],
                    ])->all(),
                ];
            }, 3);

            $this->writePrivateJson($manifestPath, $manifest);
            $this->writeCsv($csvPath, $manifest, $password);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable $exception) {
            @unlink($manifestPath);
            @unlink($csvPath);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Fixtures de avaliacao preparadas.');
        $this->line("Avaliacao: {$evaluationId}");
        $this->line("Professores: {$count}");
        $this->line("Alunos sinteticos: ".count($manifest['student_ids']));
        $this->line($csvPath);

        return self::SUCCESS;
    }

    private function reset(string $runId): int
    {
        $manifest = $this->manifest($runId);

        if ($manifest === null) {
            return self::FAILURE;
        }

        $studentIds = array_map('intval', $manifest['student_ids'] ?? []);

        DB::transaction(function () use ($studentIds): void {
            DB::table('avaliacao_respostas_operacionais')->whereIn('aluno_id', $studentIds)->delete();
            DB::table('avaliacao_informacoes_operacionais')->whereIn('aluno_id', $studentIds)->delete();
        }, 3);

        $this->info('Respostas das fixtures removidas; usuarios e alunos foram preservados.');

        return self::SUCCESS;
    }

    private function verify(string $runId): int
    {
        $manifest = $this->manifest($runId);

        if ($manifest === null) {
            return self::FAILURE;
        }

        $expected = 0;
        $persisted = 0;
        $invalid = 0;

        foreach ($manifest['targets'] ?? [] as $target) {
            $studentIds = array_map('intval', $target['aluno_ids'] ?? []);
            $alternativeIds = array_map('intval', $target['alternativa_ids'] ?? []);
            $expected += count($studentIds);

            $rows = DB::table('avaliacao_respostas_operacionais')
                ->where('avaliacao_id', (int) $manifest['evaluation_id'])
                ->where('turma_avaliativa_id', (int) $target['turma_id'])
                ->where('pauta_id', (int) $target['pauta_id'])
                ->whereIn('aluno_id', $studentIds)
                ->get(['aluno_id', 'alternativa_id', 'version']);

            $persisted += $rows->count();
            $invalid += $rows->filter(fn (object $row): bool =>
                (int) $row->version < 1 || ! in_array((int) $row->alternativa_id, $alternativeIds, true)
            )->count();
        }

        $summary = ['expected' => $expected, 'persisted' => $persisted, 'invalid' => $invalid];
        $this->line((string) json_encode($summary, JSON_UNESCAPED_SLASHES));

        return $persisted === $expected && $invalid === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function restore(string $runId): int
    {
        $manifest = $this->manifest($runId);

        if ($manifest === null) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($manifest): void {
            $studentIds = array_map('intval', $manifest['student_ids'] ?? []);

            DB::table('avaliacao_respostas_operacionais')->whereIn('aluno_id', $studentIds)->delete();
            DB::table('avaliacao_informacoes_operacionais')->whereIn('aluno_id', $studentIds)->delete();
            Aluno::query()->whereIn('id', $studentIds)->delete();

            foreach ($manifest['users'] ?? [] as $user) {
                DB::table('users')->where('id', (int) $user['id'])->update([
                    'password' => $user['password'],
                    'must_change_password' => (bool) $user['must_change_password'],
                    'remember_token' => $user['remember_token'],
                    'last_login_at' => $user['last_login_at'],
                    'last_seen_at' => $user['last_seen_at'],
                ]);
            }
        }, 3);

        $manifest['restored_at'] = now()->toIso8601String();
        $this->writePrivateJson($this->directory($runId).'/manifest.json', $manifest);
        @unlink($this->directory($runId).'/users.csv');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Fixtures removidas e usuarios restaurados.');

        return self::SUCCESS;
    }

    private function evaluationId(): int
    {
        $requested = (int) ($this->option('evaluation') ?: 0);

        if ($requested > 0) {
            return $requested;
        }

        return (int) Avaliacao::query()
            ->where('status', Avaliacao::STATUS_ATIVA)
            ->whereDate('data_inicio', '<=', today())
            ->whereDate('data_fim', '>=', today())
            ->latest('id')
            ->value('id');
    }

    /** @return Collection<int, object> */
    private function selectTargets(int $evaluationId, int $count): Collection
    {
        $candidates = DB::table('users as u')
            ->join('professores as p', function ($join): void {
                $join->on('p.user_id', '=', 'u.id')->where('p.ativo', true);
            })
            ->join('servidores as s', function ($join): void {
                $join->on('s.id', '=', 'p.servidor_id')->where('s.status', 'ativo');
            })
            ->join('turma_componente_professor as tcp', function ($join): void {
                $join->on('tcp.professor_id', '=', 'p.id')->where('tcp.tem_professor', true);
            })
            ->join('turmas as t', 't.id', '=', 'tcp.turma_id')
            ->join('avaliacao_turma as at', function ($join) use ($evaluationId): void {
                $join->on('at.turma_id', '=', 't.id')->where('at.avaliacao_id', $evaluationId);
            })
            ->join('avaliacao_turma_ciclos as ciclo', function ($join) use ($evaluationId): void {
                $join->on('ciclo.avaliacao_id', '=', 'at.avaliacao_id')
                    ->on('ciclo.turma_avaliativa_id', '=', 't.id')
                    ->whereIn('ciclo.status', [
                        \App\Models\AvaliacaoTurmaCiclo::STATUS_ABERTA,
                        \App\Models\AvaliacaoTurmaCiclo::STATUS_REABERTA,
                    ]);
            })
            ->join('avaliacao_pauta as avp', 'avp.avaliacao_id', '=', 'at.avaliacao_id')
            ->join('pautas as pa', function ($join): void {
                $join->on('pa.id', '=', 'avp.pauta_id')
                    ->where('pa.status', true)
                    ->where(function ($query): void {
                        $query->whereNull('pa.serie_id')->orWhereColumn('pa.serie_id', 't.id_serie');
                    })
                    ->where(function ($query): void {
                        $query->whereNull('pa.componente_curricular_id')
                            ->orWhereColumn('pa.componente_curricular_id', 'tcp.componente_curricular_id');
                    });
            })
            ->where('u.ativo', true)
            ->where('u.email_approved', true)
            ->select([
                'u.id as user_id',
                'u.email',
                'tcp.turma_id',
                'pa.id as pauta_id',
            ])
            ->distinct()
            ->orderBy('u.id')
            ->orderBy('tcp.turma_id')
            ->orderBy('pa.id')
            ->get();

        $selected = collect();
        $usedTargets = [];

        foreach ($candidates->groupBy('user_id') as $userCandidates) {
            foreach ($userCandidates as $candidate) {
                $targetKey = $candidate->turma_id.':'.$candidate->pauta_id;
                $alternatives = $this->allowedAlternatives($evaluationId, (int) $candidate->pauta_id);

                if (isset($usedTargets[$targetKey]) || count($alternatives) < 2) {
                    continue;
                }

                $candidate->alternativa_ids = array_slice($alternatives, 0, 2);
                $selected->push($candidate);
                $usedTargets[$targetKey] = true;
                break;
            }

            if ($selected->count() === $count) {
                break;
            }
        }

        return $selected->values();
    }

    /** @return array<int, int> */
    private function allowedAlternatives(int $evaluationId, int $pautaId): array
    {
        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', $evaluationId)
            ->where('pauta_id', $pautaId)
            ->pluck('alternativa_id');
        $ids = $overrides->isNotEmpty()
            ? $overrides
            : DB::table('alternativa_pauta')->where('pauta_id', $pautaId)->pluck('alternativa_id');

        return DB::table('alternativas')
            ->whereIn('id', $ids)
            ->where('status', true)
            ->where('tem_observacao', false)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function manifest(string $runId): ?array
    {
        $path = $this->directory($runId).'/manifest.json';

        if (! is_file($path)) {
            $this->error('Manifesto da execucao nao encontrado.');

            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest) || ($manifest['run_id'] ?? null) !== $runId) {
            $this->error('Manifesto invalido.');

            return null;
        }

        return $manifest;
    }

    private function directory(string $runId): string
    {
        return storage_path('app/private/load-tests/avaliacoes/'.$runId);
    }

    private function writePrivateJson(string $path, array $payload): void
    {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($path, 0600);
    }

    private function writeCsv(string $path, array $manifest, string $password): void
    {
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['email', 'password', 'avaliacao_id', 'turma_id', 'pauta_id', 'alternativa_ids', 'aluno_versions']);

        foreach ($manifest['targets'] as $target) {
            fputcsv($handle, [
                $target['email'],
                $password,
                $manifest['evaluation_id'],
                $target['turma_id'],
                $target['pauta_id'],
                implode('|', $target['alternativa_ids']),
                collect($target['aluno_ids'])->map(fn (int $id): string => $id.':0')->implode('|'),
            ]);
        }

        fclose($handle);
        chmod($path, 0600);
    }

    private function invalidAction(): int
    {
        $this->error('Acao invalida. Use prepare, reset, verify ou restore.');

        return self::FAILURE;
    }
}
