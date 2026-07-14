<?php

namespace App\Console\Commands;

use App\Models\FuncaoAdministrativa;
use App\Models\Escola;
use App\Models\PessoaMatricula;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditarEquipeGestoraCommand extends Command
{
    protected $signature = 'equipe-gestora:sanear
        {--aplicar : Aplica somente migrações e definições de principal inequívocas}';

    protected $description = 'Audita a modelagem da Equipe Gestora e saneia casos inequivocos de principal';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $resultado = $this->auditar($aplicar);

        $this->table(['Verificação', 'Quantidade'], collect($resultado)
            ->map(fn (int $quantidade, string $chave): array => [$chave, $quantidade])
            ->values()
            ->all());

        $this->info($aplicar
            ? 'Saneamento seguro concluído. Revise os conflitos restantes no relatório.'
            : 'Auditoria concluída sem alterar dados. Use --aplicar para os casos inequívocos.');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function auditar(bool $aplicar): array
    {
        $funcoesConflitantes = FuncaoAdministrativa::query()
            ->get()
            ->filter(fn (FuncaoAdministrativa $funcao): bool => $funcao->temFlagsGestorasConflitantes());

        $baseGestora = ServidorFuncaoAdministrativa::query()
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora());
        $pessoasMultiescolaSubquery = DB::table('servidor_funcao_administrativa as sfa')
            ->join('funcao_administrativa as fa', 'fa.id', '=', 'sfa.funcao_administrativa_id')
            ->where('sfa.status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereNotNull('sfa.id_escola')
            ->where(function ($gestoras): void {
                $gestoras
                    ->where('fa.direcao_escolar', true)
                    ->orWhere('fa.coordenacao_pedagogica', true)
                    ->orWhere('fa.secretaria_escolar', true);
            })
            ->groupBy('sfa.servidor_id')
            ->havingRaw('COUNT(DISTINCT sfa.id_escola) > 1')
            ->select('sfa.servidor_id');
        $pessoasMultiescola = DB::query()
            ->fromSub($pessoasMultiescolaSubquery, 'pessoas_multiescola')
            ->count();
        $coordenacoesSemTurma = (clone $baseGestora)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->coordenacao())
            ->whereDoesntHave('vinculosTurmaAtivos')
            ->count();
        $semPortaria = (clone $baseGestora)
            ->whereHas('funcaoAdministrativa', function ($funcoes): void {
                $funcoes->where(function ($portaria): void {
                    $portaria->where('direcao_escolar', true)->orWhere('coordenacao_pedagogica', true);
                });
            })
            ->where(function ($portaria): void {
                $portaria->whereNull('portaria')->orWhere('portaria', '');
            })
            ->count();
        $semInicio = (clone $baseGestora)->whereNull('data_inicio')->count();
        $coordenacoesTurmaSemInicio = ServidorFuncaoTurma::query()
            ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
            ->whereNull('data_inicio')
            ->whereHas('servidorFuncaoAdministrativa', function ($vinculos): void {
                $vinculos
                    ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                    ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->coordenacao());
            })
            ->count();
        $direcaoCoordenacaoDessincronizadas = DB::table('servidor_funcao_administrativa as direcao')
            ->join('funcao_administrativa as funcao_direcao', 'funcao_direcao.id', '=', 'direcao.funcao_administrativa_id')
            ->join('servidor_funcao_administrativa as coordenacao', function ($join): void {
                $join
                    ->on('coordenacao.servidor_id', '=', 'direcao.servidor_id')
                    ->where('coordenacao.status', ServidorFuncaoAdministrativa::STATUS_ATIVO);
            })
            ->join('funcao_administrativa as funcao_coordenacao', 'funcao_coordenacao.id', '=', 'coordenacao.funcao_administrativa_id')
            ->where('direcao.status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->where('funcao_direcao.direcao_escolar', true)
            ->where('funcao_direcao.coordenacao_pedagogica', false)
            ->where('funcao_direcao.secretaria_escolar', false)
            ->where('funcao_coordenacao.direcao_escolar', false)
            ->where('funcao_coordenacao.coordenacao_pedagogica', true)
            ->where('funcao_coordenacao.secretaria_escolar', false)
            ->where(function ($divergencia): void {
                $divergencia
                    ->whereRaw("COALESCE(direcao.portaria, '') <> COALESCE(coordenacao.portaria, '')")
                    ->orWhereRaw("COALESCE(direcao.data_inicio, '') <> COALESCE(coordenacao.data_inicio, '')");
            })
            ->distinct()
            ->count('direcao.servidor_id');
        $contasSemPessoa = User::query()->whereDoesntHave('servidores')->count();

        [$direcoesSemPrincipal, $direcoesDuplicadas, $direcoesSaneadas] = $this->sanearDirecoes($aplicar);
        [$turmasSemPrincipal, $turmasDuplicadas, $turmasSaneadas] = $this->sanearCoordenacoes($aplicar);

        $legado = $this->migrarSecretariosLegados($aplicar);

        return [
            'Funções com flags gestoras conflitantes' => $funcoesConflitantes->count(),
            'Pessoas gestoras em mais de uma escola' => $pessoasMultiescola,
            'Coordenações ativas sem turma' => $coordenacoesSemTurma,
            'Direções/coordenações sem portaria' => $semPortaria,
            'Vínculos gestores sem início' => $semInicio,
            'Vínculos de turma sem início' => $coordenacoesTurmaSemInicio,
            'Pessoas com Direção/Coordenação dessincronizadas' => $direcaoCoordenacaoDessincronizadas,
            'Contas sem Pessoa' => $contasSemPessoa,
            'Escolas sem Diretor principal' => $direcoesSemPrincipal,
            'Escolas com Diretores principais duplicados' => $direcoesDuplicadas,
            'Turmas sem Coordenador principal' => $turmasSemPrincipal,
            'Turmas com Coordenadores principais duplicados' => $turmasDuplicadas,
            'Usuários Secretário sem Pessoa' => $legado['sem_pessoa'],
            'Usuários Secretário elegíveis' => $legado['elegiveis'],
            'Usuários Secretário com conflito' => $legado['conflitos'],
            'Usuários Secretário migrados' => $legado['migrados'],
            'Direções principais saneadas' => $direcoesSaneadas,
            'Coordenações principais saneadas' => $turmasSaneadas,
        ];
    }

    /**
     * Migra somente contas que já possuam exatamente uma Pessoa, uma escola e
     * matrículas canônicas válidas. Data de início desconhecida permanece nula
     * e continua aparecendo na auditoria; o comando não inventa datas.
     *
     * @return array{sem_pessoa: int, elegiveis: int, conflitos: int, migrados: int}
     */
    private function migrarSecretariosLegados(bool $aplicar): array
    {
        $resultado = [
            'sem_pessoa' => 0,
            'elegiveis' => 0,
            'conflitos' => 0,
            'migrados' => 0,
        ];
        $roleLegada = Role::query()
            ->where('name', 'Secretário')
            ->where('guard_name', 'web')
            ->first();

        if (! $roleLegada) {
            return $resultado;
        }

        $roleEquipeGestora = Role::query()
            ->where('name', 'Equipe Gestora')
            ->where('guard_name', 'web')
            ->first();

        User::query()
            ->whereHas('roles', fn ($roles) => $roles->whereKey($roleLegada->id))
            ->with([
                'servidores.matriculas',
                'servidores.professores',
                'servidores.vinculosAtivos',
                'escolas',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($usuarios) use (
                $aplicar,
                $roleEquipeGestora,
                $roleLegada,
                &$resultado,
            ): void {
                foreach ($usuarios as $usuario) {
                    $pessoas = $usuario->servidores;
                    if ($pessoas->isEmpty()) {
                        $resultado['sem_pessoa']++;
                        $resultado['conflitos']++;

                        continue;
                    }
                    if ($pessoas->count() !== 1 || ! $roleEquipeGestora) {
                        $resultado['conflitos']++;

                        continue;
                    }

                    /** @var Servidor $pessoa */
                    $pessoa = $pessoas->first();
                    if ($pessoa->status !== Servidor::STATUS_ATIVO) {
                        $resultado['conflitos']++;

                        continue;
                    }

                    $escolaIds = $usuario->escolas
                        ->pluck('id')
                        ->push($usuario->id_escola)
                        ->push($pessoa->id_escola)
                        ->merge($pessoa->vinculosAtivos->pluck('id_escola'))
                        ->merge($pessoa->professores->where('ativo', true)->pluck('id_escola'))
                        ->filter()
                        ->map(fn ($id): int => (int) $id)
                        ->unique()
                        ->values();
                    $matriculas = $pessoa->matriculas;

                    try {
                        PessoaMatricula::assertConjuntoTurnosValido($matriculas->pluck('turno')->all());
                    } catch (\Throwable) {
                        $resultado['conflitos']++;

                        continue;
                    }

                    $matriculasValidas = $matriculas->isNotEmpty()
                        && $matriculas->count() <= PessoaMatricula::MAX_POR_PESSOA
                        && $matriculas
                            ->pluck('matricula')
                            ->map(fn ($matricula): string => mb_strtolower(trim((string) $matricula)))
                            ->filter()
                            ->unique()
                            ->count() === $matriculas->count()
                        && $matriculas->every(fn (PessoaMatricula $matricula): bool =>
                            filled($matricula->matricula) && filled($matricula->turno));
                    $escola = $escolaIds->count() === 1
                        ? \App\Models\Escola::query()->ativas()->find($escolaIds->first())
                        : null;
                    $possuiOutroCargoGestor = ServidorFuncaoAdministrativa::query()
                        ->where('servidor_id', $pessoa->id)
                        ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                        ->whereHas('funcaoAdministrativa', function ($funcoes): void {
                            $funcoes->where(function ($gestoras): void {
                                $gestoras
                                    ->where('direcao_escolar', true)
                                    ->orWhere('coordenacao_pedagogica', true);
                            });
                        })
                        ->exists();

                    if (! $matriculasValidas
                        || ! $escola
                        || blank($escola->setor_id)
                        || $possuiOutroCargoGestor
                        || $pessoa->professores->where('ativo', true)->isNotEmpty()
                    ) {
                        $resultado['conflitos']++;

                        continue;
                    }

                    $resultado['elegiveis']++;
                    if (! $aplicar) {
                        continue;
                    }

                    DB::transaction(function () use (
                        $escola,
                        $matriculas,
                        $pessoa,
                        $roleEquipeGestora,
                        $roleLegada,
                        $usuario,
                    ): void {
                        $funcao = FuncaoAdministrativa::secretariaPadrao();
                        ServidorFuncaoAdministrativa::query()->updateOrCreate([
                            'servidor_id' => $pessoa->id,
                            'funcao_administrativa_id' => $funcao->id,
                            'id_escola' => $escola->id,
                            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                        ], [
                            'matricula' => $matriculas->first()->matricula,
                            'setor_id' => $escola->setor_id,
                            'origem' => 'legado_secretario',
                            'portaria' => null,
                            'principal' => false,
                            'data_fim' => null,
                        ]);

                        $pessoa->update([
                            'id_escola' => $escola->id,
                            'setor_id' => $escola->setor_id,
                            'matricula' => $matriculas->first()->matricula,
                        ]);
                        $funcao->rolesPadrao()->syncWithoutDetaching([$roleEquipeGestora->id]);
                        app(\App\Services\PessoaAcessoService::class)
                            ->provisionarAcessosDoServidor($pessoa->fresh());

                        // A role antiga só é removida após vínculo, escopo e role nova
                        // terem sido persistidos com sucesso na mesma transação.
                        $usuario->removeRole($roleLegada);
                        $usuario->assignRole($roleEquipeGestora);
                    });
                    $resultado['migrados']++;
                }
            });

        return $resultado;
    }

    /** @return array{int, int, int} */
    private function sanearDirecoes(bool $aplicar): array
    {
        $escolasComPrincipal = 0;
        $duplicadas = 0;
        $saneadas = 0;

        $direcoes = DB::table('servidor_funcao_administrativa as sfa')
            ->join('funcao_administrativa as fa', 'fa.id', '=', 'sfa.funcao_administrativa_id')
            ->join('servidores as s', 's.id', '=', 'sfa.servidor_id')
            ->join('escolas as e', 'e.id', '=', 'sfa.id_escola')
            ->where('sfa.status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->where('s.status', Servidor::STATUS_ATIVO)
            ->where('fa.ativo', true)
            ->where('fa.direcao_escolar', true)
            ->where('fa.coordenacao_pedagogica', false)
            ->where('fa.secretaria_escolar', false)
            ->where('e.ativo', true)
            ->whereNotNull('sfa.id_escola')
            ->whereColumn('s.id_escola', 'sfa.id_escola')
            ->whereNotNull('sfa.portaria')
            ->whereRaw("TRIM(sfa.portaria) <> ''")
            ->whereNotNull('sfa.data_inicio')
            ->whereDate('sfa.data_inicio', '<=', now()->toDateString())
            ->where(function ($vigencia): void {
                $vigencia->whereNull('sfa.data_fim')->orWhereDate('sfa.data_fim', '>=', now()->toDateString());
            })
            ->groupBy('sfa.id_escola')
            ->selectRaw('sfa.id_escola, COUNT(*) as total, SUM(CASE WHEN sfa.principal = 1 THEN 1 ELSE 0 END) as principais, MIN(sfa.id) as candidato_id')
            ->orderBy('sfa.id_escola')
            ->lazy(200);

        foreach ($direcoes as $grupo) {
            if ((int) $grupo->principais > 1) {
                $escolasComPrincipal++;
                $duplicadas++;

                continue;
            }
            if ((int) $grupo->principais === 1) {
                $escolasComPrincipal++;

                continue;
            }

            if ($aplicar && (int) $grupo->total === 1) {
                DB::transaction(function () use ($grupo): void {
                    Escola::query()->whereKey($grupo->id_escola)->lockForUpdate()->firstOrFail();
                    ServidorFuncaoAdministrativa::query()
                        ->where('id_escola', $grupo->id_escola)
                        ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                        ->where('principal', true)
                        ->whereKeyNot($grupo->candidato_id)
                        ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->direcao())
                        ->update(['principal' => false, 'updated_at' => now()]);
                    ServidorFuncaoAdministrativa::query()
                        ->whereKey($grupo->candidato_id)
                        ->update(['principal' => true, 'updated_at' => now()]);
                });
                $saneadas++;
            }
        }

        $semPrincipal = max(0, Escola::query()->ativas()->count() - $escolasComPrincipal);

        return [$semPrincipal, $duplicadas, $saneadas];
    }

    /** @return array{int, int, int} */
    private function sanearCoordenacoes(bool $aplicar): array
    {
        $registros = DB::table('servidor_funcao_turma as sft')
            ->join('servidor_funcao_administrativa as sfa', 'sfa.id', '=', 'sft.servidor_funcao_administrativa_id')
            ->join('funcao_administrativa as fa', 'fa.id', '=', 'sfa.funcao_administrativa_id')
            ->join('servidores as s', 's.id', '=', 'sfa.servidor_id')
            ->join('escolas as e', 'e.id', '=', 'sfa.id_escola')
            ->join('turmas as t', 't.id', '=', 'sft.turma_id')
            ->where('sft.status', ServidorFuncaoTurma::STATUS_ATIVO)
            ->where('sfa.status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->where('s.status', Servidor::STATUS_ATIVO)
            ->where('fa.ativo', true)
            ->where('fa.coordenacao_pedagogica', true)
            ->where('fa.direcao_escolar', false)
            ->where('fa.secretaria_escolar', false)
            ->where('e.ativo', true)
            ->whereColumn('s.id_escola', 'sfa.id_escola')
            ->whereColumn('t.id_escola', 'sfa.id_escola')
            ->whereNotNull('sfa.portaria')
            ->whereRaw("TRIM(sfa.portaria) <> ''")
            ->whereNotNull('sfa.data_inicio')
            ->whereDate('sfa.data_inicio', '<=', now()->toDateString())
            ->where(function ($vigencia): void {
                $vigencia->whereNull('sfa.data_fim')->orWhereDate('sfa.data_fim', '>=', now()->toDateString());
            })
            ->whereNotNull('sft.data_inicio')
            ->whereDate('sft.data_inicio', '<=', now()->toDateString())
            ->where(function ($vigencia): void {
                $vigencia->whereNull('sft.data_fim')->orWhereDate('sft.data_fim', '>=', now()->toDateString());
            })
            ->groupBy('sft.turma_id')
            ->selectRaw('sft.turma_id, COUNT(*) as total, SUM(CASE WHEN sft.principal = 1 THEN 1 ELSE 0 END) as principais, MIN(sft.id) as candidato_id')
            ->orderBy('sft.turma_id')
            ->lazy(200);
        $turmasComPrincipal = 0;
        $duplicadas = 0;
        $saneadas = 0;

        foreach ($registros as $grupo) {
            if ((int) $grupo->principais > 1) {
                $turmasComPrincipal++;
                $duplicadas++;

                continue;
            }
            if ((int) $grupo->principais === 1) {
                $turmasComPrincipal++;

                continue;
            }

            if ($aplicar && (int) $grupo->total === 1) {
                DB::transaction(function () use ($grupo): void {
                    Turma::query()->whereKey($grupo->turma_id)->lockForUpdate()->firstOrFail();
                    ServidorFuncaoTurma::query()
                        ->where('turma_id', $grupo->turma_id)
                        ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
                        ->where('principal', true)
                        ->whereKeyNot($grupo->candidato_id)
                        ->whereHas('servidorFuncaoAdministrativa', function ($vinculos): void {
                            $vinculos
                                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                                ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->coordenacao());
                        })
                        ->update(['principal' => false, 'updated_at' => now()]);
                    ServidorFuncaoTurma::query()
                        ->whereKey($grupo->candidato_id)
                        ->update(['principal' => true, 'updated_at' => now()]);
                });
                $saneadas++;
            }
        }

        $semPrincipal = max(0, Turma::query()->count() - $turmasComPrincipal);

        return [$semPrincipal, $duplicadas, $saneadas];
    }
}
