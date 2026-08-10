<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        $stats = $this->normalizarEstrutura($dryRun, $somenteEmail);

        if (! Schema::hasTable('servidores')) {
            return $stats;
        }

        $stats['grupos_email'] = $this->auditarConflitosPorEmail($somenteEmail);
        $stats['anomalias'] = $this->anomalias;

        return $stats;
    }

    /**
     * Normaliza apenas estruturas legadas inequívocas.
     *
     * Conflitos de identidade são auditados, nunca mesclados automaticamente.
     *
     * @return array<string, int|list<string>>
     */
    public function normalizarEstrutura(bool $dryRun = false, ?string $somenteEmail = null): array
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

        if (! $this->haPendenciasEstruturais($somenteEmail)) {
            $stats['early_exit'] = 1;

            return $stats;
        }

        $stats['pendencias_iniciais'] = 1;

        $stats['professores_linkados'] = $this->garantirPessoaParaProfessores($dryRun, $somenteEmail, $stats);
        $stats['matriculas_criadas'] = $this->materializarMatriculas($dryRun, $somenteEmail, $stats);
        $stats['sfa_sincronizados'] = $this->sincronizarShadowEAcesso($dryRun, $somenteEmail, $stats);

        $stats['anomalias'] = $this->anomalias;

        return $stats;
    }

    /**
     * Pendências leves usadas no boot (migrate --seed): apenas estrutura.
     * Não inclui consolidação por e-mail (pode ser permanente e reprocessar minutos a cada up).
     */
    public function haPendenciasEstruturais(?string $somenteEmail = null): bool
    {
        $email = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;

        $semServidor = Professor::query()
            ->when($email, fn ($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$email]))
            ->whereNull('servidor_id')
            ->orderBy('id')
            ->get(['id', 'email', 'user_id'])
            ->contains(fn (Professor $professor): bool => $this->professorPodeReceberPessoaSemConsolidacao($professor));

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
                ->whereNotNull('servidor_id')
                ->whereNull('professor_matricula_id')
                ->exists();

            if ($semMatriculaFk) {
                return true;
            }
        }

        return false;
    }

    private function professorPodeReceberPessoaSemConsolidacao(Professor $professor): bool
    {
        if (filled($professor->user_id)) {
            $pessoasDoUser = Servidor::withTrashed()
                ->where('user_id', $professor->user_id)
                ->orderBy('id')
                ->limit(2)
                ->get();

            if ($pessoasDoUser->count() === 1) {
                return ! $pessoasDoUser->first()->trashed();
            }

            if ($pessoasDoUser->isNotEmpty()) {
                return false;
            }
        }

        $email = $this->normalizarEmail($professor->email);

        if (! $this->emailValido($email)) {
            return true;
        }

        return ! Servidor::withTrashed()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->exists();
    }

    /**
     * Pendências completas (CLI / comando artisan), inclui e-mails duplicados em pessoas distintas.
     */
    public function haPendencias(?string $somenteEmail = null): bool
    {
        if ($this->haPendenciasEstruturais($somenteEmail)) {
            return true;
        }

        $email = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;

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
                    $servidor = null;

                    if (filled($professor->user_id)) {
                        $candidatasPorUser = Servidor::withTrashed()
                            ->where('user_id', $professor->user_id)
                            ->orderBy('id')
                            ->get();

                        if ($candidatasPorUser->count() > 1) {
                            $this->anomalias[] = "Professor #{$professor->id} não vinculado: o usuário #{$professor->user_id} pertence a mais de uma Pessoa.";

                            continue;
                        }

                        $servidor = $candidatasPorUser->first();

                        if ($servidor?->trashed()) {
                            $this->anomalias[] = "Professor #{$professor->id} não vinculado: a Pessoa #{$servidor->id} associada ao usuário está arquivada.";

                            continue;
                        }
                    }

                    if (! $servidor && $this->emailValido($email)) {
                        $conflitoPorEmail = Servidor::withTrashed()
                            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                            ->exists();

                        if ($conflitoPorEmail) {
                            $this->anomalias[] = "Professor #{$professor->id} não vinculado automaticamente: o e-mail {$email} já pertence a uma Pessoa e não é identificador seguro para consolidação.";

                            continue;
                        }
                    }

                    if ($dryRun) {
                        $stats['pessoas_criadas'] += $servidor ? 0 : 1;
                        $linkados++;

                        continue;
                    }

                    if (! $servidor) {
                        $servidor = Servidor::query()->create([
                            'nome' => $professor->nome ?: 'Sem nome',
                            'email' => $this->emailValido($email) ? $email : null,
                            'telefone' => $professor->telefone,
                            'id_escola' => $professor->id_escola,
                            'matricula' => $professor->matricula,
                            'user_id' => $professor->user_id,
                            'status' => Servidor::STATUS_ATIVO,
                        ]);
                        $stats['pessoas_criadas']++;
                    }

                    $professor->forceFill([
                        'servidor_id' => $servidor->id,
                        'user_id' => $professor->user_id ?: $servidor->user_id,
                    ])->saveQuietly();
                    $linkados++;
                }
            });

        return $linkados;
    }

    private function auditarConflitosPorEmail(?string $somenteEmail): int
    {
        $emailFiltro = $somenteEmail ? $this->normalizarEmail($somenteEmail) : null;

        $grupos = Servidor::withTrashed()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->map(function (Servidor $pessoa) use ($emailFiltro): ?array {
                $norm = $this->normalizarEmail($pessoa->email);
                if (! $this->emailValido($norm)) {
                    return null;
                }
                if ($emailFiltro && $norm !== $emailFiltro) {
                    return null;
                }

                return [
                    'email' => $norm,
                    'id' => (int) $pessoa->id,
                ];
            })
            ->filter()
            ->groupBy('email')
            ->filter(fn (Collection $grupo): bool => $grupo->count() > 1);

        foreach ($grupos as $email => $pessoas) {
            $ids = $pessoas->pluck('id')->implode(', ');
            $this->anomalias[] = "E-mail {$email} duplicado nas Pessoas #{$ids}; nenhuma consolidação automática foi realizada.";
        }

        return $grupos->count();
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

            // Alinha user_id e roles sem usar e-mail como identificador de pessoa.
            $email = $this->normalizarEmail($servidor->email);
            if ($email && $this->emailValido($email)) {
                $user = $servidor->user;
                $acessoBloqueado = false;

                if (! $user) {
                    $userIds = $servidor->professores()
                        ->whereNotNull('user_id')
                        ->distinct()
                        ->pluck('user_id')
                        ->map(fn ($id): int => (int) $id)
                        ->values();

                    if ($userIds->count() > 1) {
                        $this->anomalias[] = "Pessoa #{$servidor->id} não teve acesso alinhado: seus professores apontam para mais de um usuário.";
                        $acessoBloqueado = true;
                    } elseif ($userIds->count() === 1) {
                        $candidato = User::withTrashed()->find($userIds->first());
                        $pertenceAOutraPessoa = $candidato && Pessoa::withTrashed()
                            ->where('user_id', $candidato->id)
                            ->whereKeyNot($servidor->id)
                            ->exists();

                        if (! $candidato || $candidato->trashed() || $pertenceAOutraPessoa) {
                            $this->anomalias[] = "Pessoa #{$servidor->id} não teve acesso alinhado: o user_id dos professores está indisponível ou pertence a outra Pessoa.";
                            $acessoBloqueado = true;
                        } else {
                            $user = $candidato;
                        }
                    }
                }

                if ($user?->trashed()) {
                    $this->anomalias[] = "Pessoa #{$servidor->id} não teve acesso alinhado: a conta vinculada está arquivada.";
                    $acessoBloqueado = true;
                }

                if (! $acessoBloqueado && $user && (int) ($servidor->user_id ?? 0) !== (int) $user->id) {
                    $servidor->update(['user_id' => $user->id]);
                }

                if (! $acessoBloqueado && $user) {
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
                    ! $acessoBloqueado
                    && $servidor->professores()->where('ativo', true)->exists()
                    && filled($servidor->email)
                    && Professor::emailInstitucionalValido($servidor->email)
                ) {
                    $contaComMesmoEmail = User::withTrashed()
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                        ->first();

                    if ($contaComMesmoEmail) {
                        $this->anomalias[] = "Pessoa #{$servidor->id} não foi vinculada automaticamente à conta #{$contaComMesmoEmail->id}: e-mail não é identificador seguro.";
                    } else {
                        $this->acessoService->provisionarUsuarioProfessor($servidor->fresh(['professores', 'user']));
                        $stats['users_alinhados'] = ($stats['users_alinhados'] ?? 0) + 1;
                    }
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
        return Pessoa::normalizarEmail($email);
    }
}
