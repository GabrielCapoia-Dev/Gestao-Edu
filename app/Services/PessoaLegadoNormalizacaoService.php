<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Normaliza massa legada para o padrão Pessoa + professor_matriculas + lotações.
 * Idempotente: early-exit quando não há pendências.
 */
class PessoaLegadoNormalizacaoService
{
    /** @var list<string> */
    private array $anomalias = [];

    public function __construct(
        private readonly PessoaAcessoService $acessoService,
    ) {}

    /**
     * @return array<string, int|list<string>>
     */
    public function normalizar(bool $dryRun = false, ?string $somenteEmail = null): array
    {
        $stats = [
            'pendencias_iniciais' => 0,
            'professores_linkados' => 0,
            'pessoas_criadas' => 0,
            'pessoas_mescladas' => 0,
            'matriculas_criadas' => 0,
            'matriculas_vinculadas' => 0,
            'sfa_sincronizados' => 0,
            'users_alinhados' => 0,
            'grupos_email' => 0,
            'early_exit' => 0,
            'anomalias' => [],
        ];

        $this->anomalias = [];

        if (! Schema::hasTable('professores') || ! Schema::hasTable('servidores')) {
            $stats['early_exit'] = 1;

            return $stats;
        }

        if (! $this->haPendencias($somenteEmail)) {
            $stats['early_exit'] = 1;

            return $stats;
        }

        $stats['pendencias_iniciais'] = 1;

        $stats['professores_linkados'] = $this->garantirPessoaParaProfessores($dryRun, $somenteEmail, $stats);
        $stats['grupos_email'] = $this->consolidarPorEmail($dryRun, $somenteEmail, $stats);
        $stats['matriculas_criadas'] = $this->materializarMatriculas($dryRun, $somenteEmail, $stats);
        $stats['sfa_sincronizados'] = $this->sincronizarShadowEAcesso($dryRun, $somenteEmail, $stats);

        $stats['anomalias'] = $this->anomalias;

        return $stats;
    }

    public function haPendencias(?string $somenteEmail = null): bool
    {
        $email = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;

        $semServidor = Professor::query()
            ->when($email, fn ($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$email]))
            ->whereNull('servidor_id')
            ->exists();

        if ($semServidor) {
            return true;
        }

        if (Schema::hasColumn('professores', 'professor_matricula_id')) {
            $semMatriculaFk = Professor::query()
                ->when($email, fn ($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$email]))
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotNull('matricula')
                ->where('matricula', '!=', '')
                ->whereNull('professor_matricula_id')
                ->exists();

            if ($semMatriculaFk) {
                return true;
            }
        }

        // Mesmo e-mail normalizado em mais de um servidor_id (ignora local-part vazio)
        $professores = Professor::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('servidor_id')
            ->get(['email', 'servidor_id']);

        $porEmail = $professores
            ->map(function (Professor $p) use ($email): ?array {
                $norm = $this->normalizarEmail($p->email);
                if (! $this->emailValido($norm)) {
                    return null;
                }
                if ($email && $norm !== $email) {
                    return null;
                }

                return ['email' => $norm, 'servidor_id' => (int) $p->servidor_id];
            })
            ->filter()
            ->groupBy('email');

        foreach ($porEmail as $grupo) {
            if ($grupo->pluck('servidor_id')->unique()->count() > 1) {
                return true;
            }
        }

        return false;
    }

    private function garantirPessoaParaProfessores(bool $dryRun, ?string $somenteEmail, array &$stats): int
    {
        $linkados = 0;
        $emailFiltro = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;

        Professor::query()
            ->whereNull('servidor_id')
            ->when($emailFiltro, fn ($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$emailFiltro]))
            ->orderBy('id')
            ->chunkById(100, function ($professores) use ($dryRun, &$linkados, &$stats): void {
                foreach ($professores as $professor) {
                    $email = $this->normalizarEmail($professor->email);

                    if (! $this->emailValido($email)) {
                        // Pessoa isolada sem e-mail usable
                        if ($dryRun) {
                            $linkados++;
                            $stats['pessoas_criadas']++;

                            continue;
                        }

                        $servidor = Servidor::query()->create([
                            'nome' => $professor->nome ?: 'Sem nome',
                            'email' => null,
                            'telefone' => $professor->telefone,
                            'id_escola' => $professor->id_escola,
                            'matricula' => $professor->matricula,
                            'user_id' => $professor->user_id,
                            'status' => Servidor::STATUS_ATIVO,
                        ]);
                        $stats['pessoas_criadas']++;
                        $professor->update(['servidor_id' => $servidor->id]);
                        $linkados++;

                        continue;
                    }

                    if ($dryRun) {
                        $exists = Servidor::query()
                            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                            ->exists();
                        if (! $exists) {
                            $stats['pessoas_criadas']++;
                        }
                        $linkados++;

                        continue;
                    }

                    $servidor = Servidor::query()
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                        ->orderBy('id')
                        ->first();

                    if (! $servidor) {
                        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->orderBy('id')->first();

                        $servidor = Servidor::query()->create([
                            'nome' => $professor->nome ?: ($user?->name ?? 'Sem nome'),
                            'email' => $email,
                            'telefone' => $professor->telefone,
                            'id_escola' => $professor->id_escola,
                            'matricula' => $professor->matricula,
                            'user_id' => $professor->user_id ?: $user?->id,
                            'status' => Servidor::STATUS_ATIVO,
                        ]);
                        $stats['pessoas_criadas']++;
                    } else {
                        $updates = [];
                        if (blank($servidor->user_id) && filled($professor->user_id)) {
                            $updates['user_id'] = $professor->user_id;
                        }
                        if (blank($servidor->telefone) && filled($professor->telefone)) {
                            $updates['telefone'] = $professor->telefone;
                        }
                        if (blank($servidor->nome) && filled($professor->nome)) {
                            $updates['nome'] = $professor->nome;
                        }
                        if ($updates !== []) {
                            $servidor->update($updates);
                        }
                    }

                    $professor->update([
                        'servidor_id' => $servidor->id,
                        'user_id' => $professor->user_id ?: $servidor->user_id,
                        'email' => $email,
                    ]);
                    $linkados++;
                }
            });

        return $linkados;
    }

    private function consolidarPorEmail(bool $dryRun, ?string $somenteEmail, array &$stats): int
    {
        $grupos = 0;
        $emailFiltro = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;

        $mapa = Professor::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('servidor_id')
            ->get(['id', 'email', 'servidor_id', 'nome'])
            ->map(function (Professor $p) use ($emailFiltro): ?array {
                $norm = $this->normalizarEmail($p->email);
                if (! $this->emailValido($norm)) {
                    return null;
                }
                if ($emailFiltro && $norm !== $emailFiltro) {
                    return null;
                }

                return [
                    'email' => $norm,
                    'servidor_id' => (int) $p->servidor_id,
                    'nome' => $p->nome,
                    'id' => (int) $p->id,
                ];
            })
            ->filter()
            ->groupBy('email');

        foreach ($mapa as $email => $rows) {
            $servidorIds = $rows->pluck('servidor_id')->unique()->values();
            if ($servidorIds->count() < 2) {
                continue;
            }

            $grupos++;

            // Normaliza e-mails gravados com espaço no meio
            if (! $dryRun) {
                Professor::query()
                    ->whereIn('id', $rows->pluck('id')->all())
                    ->update(['email' => $email]);
                Servidor::query()
                    ->whereIn('id', $servidorIds->all())
                    ->update(['email' => $email]);
            }

            $servidores = Servidor::query()
                ->whereIn('id', $servidorIds->all())
                ->with(['user', 'professores', 'servidorFuncoes'])
                ->get();

            $canonica = $this->escolherCanonica($servidores);

            foreach ($servidores as $duplicata) {
                if ((int) $duplicata->id === (int) $canonica->id) {
                    continue;
                }

                if ($dryRun) {
                    $stats['pessoas_mescladas']++;

                    continue;
                }

                $this->mesclarParaCanonica($canonica, $duplicata, $stats);
            }

            $nomes = $rows->pluck('nome')->filter()->unique()->count();
            if ($nomes > 3) {
                $this->anomalias[] = "E-mail {$email} consolidado com {$nomes} nomes distintos (possível e-mail institucional).";
            }
        }

        // Também consolida servidores com mesmo e-mail mesmo sem multi servidor_id em professores
        if (! $dryRun) {
            $stats['pessoas_mescladas'] += $this->consolidarServidoresPorEmailDireto($emailFiltro);
        }

        return $grupos;
    }

    private function consolidarServidoresPorEmailDireto(?string $emailFiltro): int
    {
        $mesclados = 0;

        $servidores = Servidor::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get();

        $grupos = $servidores
            ->map(function (Servidor $s) use ($emailFiltro): ?array {
                $norm = $this->normalizarEmail($s->email);
                if (! $this->emailValido($norm)) {
                    return null;
                }
                if ($emailFiltro && $norm !== $emailFiltro) {
                    return null;
                }

                return ['email' => $norm, 'model' => $s];
            })
            ->filter()
            ->groupBy('email')
            ->filter(fn (Collection $g) => $g->count() > 1);

        foreach ($grupos as $email => $grupo) {
            /** @var Collection<int, array{email: string, model: Servidor}> $grupo */
            $models = $grupo->pluck('model')->values();
            Servidor::query()->whereIn('id', $models->pluck('id')->all())->update(['email' => $email]);

            $canonica = $this->escolherCanonica($models);
            $statsLocal = ['pessoas_mescladas' => 0, 'professores_linkados' => 0];

            foreach ($models as $duplicata) {
                if ((int) $duplicata->id === (int) $canonica->id) {
                    continue;
                }
                $this->mesclarParaCanonica($canonica, $duplicata, $statsLocal);
                $mesclados++;
            }
        }

        return $mesclados;
    }

    /** @param Collection<int, Servidor> $servidores */
    private function escolherCanonica(Collection $servidores): Servidor
    {
        return $servidores->sortBy(function (Servidor $s): array {
            $userApproved = $s->user?->email_approved ? 0 : 1;
            $hasUser = filled($s->user_id) ? 0 : 1;

            return [$hasUser, $userApproved, (int) $s->id];
        })->first();
    }

    private function mesclarParaCanonica(Servidor $canonica, Servidor $duplicata, array &$stats): void
    {
        DB::transaction(function () use ($canonica, $duplicata, &$stats): void {
            $canonica = $canonica->fresh(['user']);
            $duplicata = $duplicata->fresh(['user', 'professores', 'servidorFuncoes', 'professorMatriculas']);

            if (! $canonica || ! $duplicata || (int) $canonica->id === (int) $duplicata->id) {
                return;
            }

            $this->preencherCamposVazios($canonica, $duplicata);
            $this->alinharUsers($canonica, $duplicata);

            foreach ($duplicata->servidorFuncoes as $vinculo) {
                if ($this->vinculoEquivalenteExiste($canonica, $vinculo)) {
                    $vinculo->update([
                        'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                        'data_fim' => now()->toDateString(),
                    ]);

                    continue;
                }

                $vinculo->update(['servidor_id' => $canonica->id]);
            }

            if (Schema::hasTable('professor_matriculas')) {
                foreach ($duplicata->professorMatriculas as $mat) {
                    $existente = ProfessorMatricula::query()
                        ->where('servidor_id', $canonica->id)
                        ->where('matricula', $mat->matricula)
                        ->first();

                    if ($existente) {
                        Professor::query()
                            ->where('professor_matricula_id', $mat->id)
                            ->update(['professor_matricula_id' => $existente->id]);
                        $mat->delete();
                    } else {
                        $mat->update(['servidor_id' => $canonica->id]);
                    }
                }
            }

            foreach ($duplicata->professores as $professor) {
                $equivalente = Professor::query()
                    ->where('servidor_id', $canonica->id)
                    ->where('id_escola', $professor->id_escola)
                    ->where('matricula', $professor->matricula)
                    ->where('id', '!=', $professor->id)
                    ->first();

                if ($equivalente) {
                    $this->realocarReferenciasProfessor($professor, $equivalente);
                    $professor->delete();
                } else {
                    $professor->update([
                        'servidor_id' => $canonica->id,
                        'user_id' => $professor->user_id ?: $canonica->user_id,
                        'email' => $this->normalizarEmail($professor->email) ?: $canonica->email,
                    ]);
                }
            }

            // Se ainda restam FKs, não apaga
            $aindaTem = Professor::query()->where('servidor_id', $duplicata->id)->exists()
                || ServidorFuncaoAdministrativa::query()->where('servidor_id', $duplicata->id)->exists();

            if (! $aindaTem) {
                $duplicata->delete();
                $stats['pessoas_mescladas'] = ($stats['pessoas_mescladas'] ?? 0) + 1;
            } else {
                $this->anomalias[] = "Servidor #{$duplicata->id} não removido após merge para #{$canonica->id} (ainda há FKs).";
            }
        });
    }

    private function preencherCamposVazios(Servidor $canonica, Servidor $duplicata): void
    {
        $updates = [];
        foreach (['cpf', 'email', 'telefone', 'user_id', 'matricula', 'id_escola', 'setor_id', 'nome'] as $campo) {
            if (blank($canonica->{$campo}) && filled($duplicata->{$campo})) {
                $updates[$campo] = $duplicata->{$campo};
            }
        }
        if ($updates !== []) {
            $canonica->update($updates);
        }
    }

    private function alinharUsers(Servidor $canonica, Servidor $duplicata): void
    {
        if (filled($canonica->user_id) && filled($duplicata->user_id) && (int) $canonica->user_id !== (int) $duplicata->user_id) {
            $preferido = $this->escolherUserPreferido(
                User::query()->find($canonica->user_id),
                User::query()->find($duplicata->user_id),
            );

            if ($preferido && (int) $preferido->id !== (int) $canonica->user_id) {
                $canonica->update(['user_id' => $preferido->id]);
            }

            $descartadoId = (int) $duplicata->user_id === (int) $canonica->user_id
                ? null
                : (int) $duplicata->user_id;

            if ($descartadoId && $preferido && $descartadoId !== (int) $preferido->id) {
                $descartado = User::query()->find($descartadoId);
                if ($descartado) {
                    // Evita unique email: sufixo legado
                    $novoEmail = $this->emailLegadoUnico($descartado->email, $descartado->id);
                    $descartado->update([
                        'email' => $novoEmail,
                        'email_approved' => false,
                    ]);
                }
            }
        } elseif (blank($canonica->user_id) && filled($duplicata->user_id)) {
            $canonica->update(['user_id' => $duplicata->user_id]);
        }

        Professor::query()
            ->where('servidor_id', $canonica->id)
            ->whereNull('user_id')
            ->when(filled($canonica->user_id), fn ($q) => $q->update(['user_id' => $canonica->user_id]));
    }

    private function escolherUserPreferido(?User $a, ?User $b): ?User
    {
        if (! $a) {
            return $b;
        }
        if (! $b) {
            return $a;
        }

        $score = function (User $u): array {
            return [
                $u->email_approved ? 0 : 1,
                $u->last_login_at ? 0 : 1,
                - (int) $u->id,
            ];
        };

        return $score($a) <= $score($b) ? $a : $b;
    }

    private function emailLegadoUnico(string $email, int $userId): string
    {
        $email = $this->normalizarEmail($email) ?? "user{$userId}@legado.local";
        if (! str_contains($email, '@')) {
            return "legado+{$userId}@invalid.local";
        }
        [$local, $domain] = explode('@', $email, 2);
        $candidato = "{$local}.legado{$userId}@{$domain}";
        $i = 0;
        while (User::query()->where('email', $candidato)->where('id', '!=', $userId)->exists()) {
            $i++;
            $candidato = "{$local}.legado{$userId}.{$i}@{$domain}";
        }

        return $candidato;
    }

    private function vinculoEquivalenteExiste(Servidor $canonica, ServidorFuncaoAdministrativa $vinculo): bool
    {
        return $canonica->servidorFuncoesAtivas()
            ->where('funcao_administrativa_id', $vinculo->funcao_administrativa_id)
            ->when(filled($vinculo->id_escola), fn ($q) => $q->where('id_escola', $vinculo->id_escola))
            ->when(filled($vinculo->matricula), fn ($q) => $q->where('matricula', $vinculo->matricula))
            ->exists();
    }

    private function realocarReferenciasProfessor(Professor $duplicata, Professor $canonica): void
    {
        $tabelas = [
            ['turma_componente_professor', 'professor_id'],

        ];

        foreach ($tabelas as [$tabela, $coluna]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            // turma_componente_professor tem unique (turma_id, componente) — evita conflito
            if ($tabela === 'turma_componente_professor') {
                $rows = DB::table($tabela)->where($coluna, $duplicata->id)->get();
                foreach ($rows as $row) {
                    $exists = DB::table($tabela)
                        ->where('turma_id', $row->turma_id)
                        ->where('componente_curricular_id', $row->componente_curricular_id)
                        ->where('professor_id', $canonica->id)
                        ->exists();

                    if ($exists) {
                        DB::table($tabela)->where('id', $row->id)->update([
                            'professor_id' => null,
                            'tem_professor' => false,
                        ]);
                    } else {
                        DB::table($tabela)->where('id', $row->id)->update(['professor_id' => $canonica->id]);
                    }
                }

                continue;
            }

            DB::table($tabela)
                ->where($coluna, $duplicata->id)
                ->update([$coluna => $canonica->id]);
        }

        if (
            Schema::hasColumn('professores', 'servidor_funcao_administrativa_id')
            && blank($canonica->servidor_funcao_administrativa_id)
            && filled($duplicata->servidor_funcao_administrativa_id)
        ) {
            $canonica->update([
                'servidor_funcao_administrativa_id' => $duplicata->servidor_funcao_administrativa_id,
            ]);
        }
    }

    private function materializarMatriculas(bool $dryRun, ?string $somenteEmail, array &$stats): int
    {
        if (! Schema::hasTable('professor_matriculas') || ! Schema::hasColumn('professores', 'professor_matricula_id')) {
            return 0;
        }

        $criadas = 0;
        $emailFiltro = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;

        $servidorIds = Professor::query()
            ->whereNotNull('servidor_id')
            ->whereNotNull('matricula')
            ->where('matricula', '!=', '')
            ->when($emailFiltro, fn ($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$emailFiltro]))
            ->distinct()
            ->pluck('servidor_id');

        foreach ($servidorIds as $servidorId) {
            $professores = Professor::query()
                ->where('servidor_id', $servidorId)
                ->whereNotNull('matricula')
                ->where('matricula', '!=', '')
                ->get();

            $porMatricula = $professores->groupBy(fn (Professor $p): string => trim((string) $p->matricula));

            foreach ($porMatricula as $matricula => $grupo) {
                $turno = $this->turnoDominante($grupo);

                if ($dryRun) {
                    $exists = ProfessorMatricula::query()
                        ->where('servidor_id', $servidorId)
                        ->where('matricula', $matricula)
                        ->exists();
                    if (! $exists) {
                        $criadas++;
                    }
                    $stats['matriculas_vinculadas'] = ($stats['matriculas_vinculadas'] ?? 0) + $grupo->count();

                    continue;
                }

                $matModel = ProfessorMatricula::query()->updateOrCreate(
                    [
                        'servidor_id' => $servidorId,
                        'matricula' => $matricula,
                    ],
                    [
                        'turno' => $turno,
                    ],
                );

                if ($matModel->wasRecentlyCreated) {
                    $criadas++;
                }

                foreach ($grupo as $professor) {
                    $payload = [
                        'professor_matricula_id' => $matModel->id,
                        'matricula' => $matricula,
                    ];
                    if (blank($professor->turno) || $professor->turno !== $turno) {
                        // Alinha ao turno da matrícula se ainda vazio; se diverge, só preenche se blank
                        if (blank($professor->turno)) {
                            $payload['turno'] = $turno;
                        }
                    }
                    $professor->update($payload);
                    $stats['matriculas_vinculadas'] = ($stats['matriculas_vinculadas'] ?? 0) + 1;
                }

                if ($grupo->pluck('turno')->filter()->unique()->count() > 1) {
                    $this->anomalias[] = "Servidor #{$servidorId} matrícula {$matricula} com turnos mistos; dominante={$turno}.";
                }
            }

            if ($porMatricula->count() > 2) {
                $email = optional($professores->first())->email;
                $this->anomalias[] = "Pessoa #{$servidorId} ({$email}) tem {$porMatricula->count()} matrículas legadas (todas preservadas).";
            }
        }

        return $criadas;
    }

    /** @param Collection<int, Professor> $grupo */
    private function turnoDominante(Collection $grupo): string
    {
        $counts = $grupo
            ->pluck('turno')
            ->filter(fn ($t) => filled($t) && array_key_exists($t, Professor::TURNOS))
            ->countBy()
            ->sortDesc();

        if ($counts->isEmpty()) {
            return 'manha';
        }

        $max = $counts->first();
        $candidatos = $counts->filter(fn ($c) => $c === $max)->keys();

        if ($candidatos->contains('integral')) {
            return 'integral';
        }

        return (string) $candidatos->first();
    }

    private function sincronizarShadowEAcesso(bool $dryRun, ?string $somenteEmail, array &$stats): int
    {
        if ($dryRun) {
            return 0;
        }

        $sincronizados = 0;
        $emailFiltro = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;
        $funcao = FuncaoAdministrativa::professorPadrao();

        $servidorIds = Professor::query()
            ->whereNotNull('servidor_id')
            ->where('ativo', true)
            ->when($emailFiltro, fn ($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$emailFiltro]))
            ->distinct()
            ->pluck('servidor_id');

        foreach ($servidorIds as $servidorId) {
            $servidor = Servidor::query()->with(['professores.escola', 'user'])->find($servidorId);
            if (! $servidor) {
                continue;
            }

            foreach ($servidor->professores->where('ativo', true) as $professor) {
                $setorId = $professor->escola?->setor_id ? (int) $professor->escola->setor_id : null;

                $vinculo = ServidorFuncaoAdministrativa::query()
                    ->where('servidor_id', $servidor->id)
                    ->where('funcao_administrativa_id', $funcao->id)
                    ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                    ->where('id_escola', $professor->id_escola)
                    ->where('matricula', $professor->matricula)
                    ->first();

                if (! $vinculo) {
                    $vinculo = ServidorFuncaoAdministrativa::query()->create([
                        'servidor_id' => $servidor->id,
                        'funcao_administrativa_id' => $funcao->id,
                        'matricula' => $professor->matricula,
                        'id_escola' => $professor->id_escola,
                        'setor_id' => $setorId,
                        'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                        'origem' => 'legado-normalizacao',
                    ]);
                } else {
                    $vinculo->update([
                        'matricula' => $professor->matricula,
                        'setor_id' => $setorId ?: $vinculo->setor_id,
                        'data_fim' => null,
                    ]);
                }

                if ((int) ($professor->servidor_funcao_administrativa_id ?? 0) !== (int) $vinculo->id) {
                    $professor->update(['servidor_funcao_administrativa_id' => $vinculo->id]);
                }

                $sincronizados++;
            }

            // Alinha user_id e roles sem resetar senha
            $email = $this->normalizarEmail($servidor->email);
            if ($email && $this->emailValido($email)) {
                $user = $servidor->user
                    ?: User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->orderBy('id')->first();

                if ($user && (int) ($servidor->user_id ?? 0) !== (int) $user->id) {
                    $servidor->update(['user_id' => $user->id]);
                }

                if ($user) {
                    Professor::query()
                        ->where('servidor_id', $servidor->id)
                        ->where(function ($q) use ($user): void {
                            $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id);
                        })
                        ->update(['user_id' => $user->id]);

                    if ($servidor->professores()->where('ativo', true)->exists()) {
                        $this->acessoService->aplicarRolesProfessor($user, [], forcarDefaults: true);
                        $stats['users_alinhados'] = ($stats['users_alinhados'] ?? 0) + 1;
                    }

                    try {
                        app(ProfessorEscolaVinculoService::class)->sincronizarPorUsuario($user);
                    } catch (\Throwable) {
                        // best-effort
                    }
                } elseif (
                    $servidor->professores()->where('ativo', true)->exists()
                    && filled($servidor->email)
                    && Professor::emailInstitucionalValido($servidor->email)
                ) {
                    // Cria user só se não existir — provisionarUsuarioProfessor não reseta se já houver
                    $this->acessoService->provisionarUsuarioProfessor($servidor->fresh(['professores', 'user']));
                    $stats['users_alinhados'] = ($stats['users_alinhados'] ?? 0) + 1;
                }
            }

            // Escopo agregado
            $escolaIds = $servidor->professores()->where('ativo', true)->pluck('id_escola')->filter()->unique();
            $setorIds = Escola::query()->whereIn('id', $escolaIds->all())->pluck('setor_id')->filter()->unique();
            $servidor->update([
                'id_escola' => $escolaIds->first() ?: $servidor->id_escola,
                'setor_id' => $setorIds->first() ?: $servidor->setor_id,
            ]);
        }

        return $sincronizados;
    }

    private function emailValido(?string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        if (! str_contains($email, '@')) {
            return false;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        // Local-part vazio (ex.: "@edu.umuarama...") não identifica pessoa real.
        if (! filled($local) || ! filled($domain)) {
            return false;
        }

        return true;
    }

    private function normalizarEmail(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        // Remove espaços internos comuns em legado: "nome @dominio"
        $email = Str::lower(preg_replace('/\s+/', '', trim((string) $email)) ?? '');

        return $email !== '' ? $email : null;
    }
}
