<?php

namespace App\Services;

use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServidorService
{
    public function criarServidorComFuncoes(array $data, array $vinculos = []): Servidor
    {
        if ($this->fluxoMotorista($data, $vinculos)) {
            return $this->criarPessoaMotorista($data, $vinculos);
        }

        if ($this->fluxoTransporte($data, $vinculos)) {
            return $this->criarPessoaOperacional($data, $vinculos, 'transporte');
        }

        if ($this->fluxoAssessoriaPedagogica($data, $vinculos)) {
            return $this->criarPessoaOperacional($data, $vinculos, 'assessoria_pedagogica');
        }

        if ($this->fluxoObras($data, $vinculos)) {
            return app(PessoaObrasService::class)->criarPessoaObras(
                $data,
                $this->dadosObras($data, $vinculos),
            );
        }

        if ($this->fluxoManutencao($data, $vinculos)) {
            return app(PessoaManutencaoService::class)->criarPessoaManutencao(
                $data,
                $this->dadosManutencao($data, $vinculos),
            );
        }

        if ($this->fluxoEquipeGestora($data, $vinculos)) {
            return app(PessoaEquipeGestoraService::class)->criarPessoaEquipeGestora(
                $data,
                $this->dadosEquipeGestora($data, $vinculos),
            );
        }

        if ($this->fluxoProfessor($data, $vinculos)) {
            return app(PessoaProfessorService::class)->criarPessoaProfessor(
                $data,
                $vinculos['matriculas_professor']
                    ?? $vinculos['registros_professor']
                    ?? $data['matriculas_professor']
                    ?? $data['registros_professor']
                    ?? $vinculos,
                $this->dadosAcesso($data),
            );
        }

        return app(PessoaVinculoService::class)->criarPessoaComVinculos($data, $vinculos);
    }

    public function atualizarServidorComFuncoes(Servidor $servidor, array $data, array $vinculos = []): Servidor
    {
        if ($this->fluxoMotorista($data, $vinculos)) {
            return $this->atualizarPessoaMotorista($servidor, $data, $vinculos);
        }

        if ($this->fluxoTransporte($data, $vinculos)) {
            return $this->atualizarPessoaOperacional($servidor, $data, $vinculos, 'transporte');
        }

        if ($this->fluxoAssessoriaPedagogica($data, $vinculos)) {
            return $this->atualizarPessoaOperacional($servidor, $data, $vinculos, 'assessoria_pedagogica');
        }

        if ($this->fluxoObras($data, $vinculos)) {
            $dadosObras = $this->dadosObras($data, $vinculos);

            if ($servidor->professores()->where('ativo', true)->exists()) {
                return app(PessoaObrasService::class)->converterProfessorParaObras(
                    $servidor,
                    $data,
                    $dadosObras,
                );
            }

            if ($servidor->vinculosAtivos()
                ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
                ->exists()) {
                return app(PessoaObrasService::class)->converterEquipeGestoraParaObras(
                    $servidor,
                    $data,
                    $dadosObras,
                );
            }

            if ($this->pessoaEhManutencao($servidor)) {
                return DB::transaction(function () use ($servidor, $data, $dadosObras): Servidor {
                    app(PessoaManutencaoService::class)->encerrarVinculosManutencao(
                        $servidor,
                        reconciliarAcesso: false,
                    );

                    return app(PessoaObrasService::class)->atualizarPessoaObras(
                        $servidor->fresh(),
                        $data,
                        $dadosObras,
                    );
                });
            }

            return app(PessoaObrasService::class)->atualizarPessoaObras(
                $servidor,
                $data,
                $dadosObras,
            );
        }

        if ($this->fluxoManutencao($data, $vinculos)) {
            $dadosManutencao = $this->dadosManutencao($data, $vinculos);

            if ($this->pessoaEhObras($servidor)) {
                return DB::transaction(function () use ($servidor, $data, $dadosManutencao): Servidor {
                    app(PessoaObrasService::class)->encerrarVinculosObras(
                        $servidor,
                        reconciliarAcesso: false,
                    );

                    return app(PessoaManutencaoService::class)->atualizarPessoaManutencao(
                        $servidor->fresh(),
                        $data,
                        $dadosManutencao,
                    );
                });
            }

            if ($servidor->professores()->where('ativo', true)->exists()) {
                return app(PessoaManutencaoService::class)->converterProfessorParaManutencao(
                    $servidor,
                    $data,
                    $dadosManutencao,
                );
            }

            if ($servidor->vinculosAtivos()
                ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
                ->exists()) {
                return app(PessoaManutencaoService::class)->converterEquipeGestoraParaManutencao(
                    $servidor,
                    $data,
                    $dadosManutencao,
                );
            }

            return app(PessoaManutencaoService::class)->atualizarPessoaManutencao(
                $servidor,
                $data,
                $dadosManutencao,
            );
        }

        if ($this->fluxoEquipeGestora($data, $vinculos)) {
            $dadosGestao = $this->dadosEquipeGestora($data, $vinculos);

            if ($servidor->professores()->where('ativo', true)->exists()) {
                return app(PessoaEquipeGestoraService::class)->converterProfessorParaEquipeGestora(
                    $servidor,
                    $dadosGestao,
                    $data,
                );
            }

            if ($this->pessoaEhManutencao($servidor)) {
                return DB::transaction(function () use ($servidor, $data, $dadosGestao): Servidor {
                    app(PessoaManutencaoService::class)->encerrarVinculosManutencao(
                        $servidor,
                        reconciliarAcesso: false,
                    );

                    return app(PessoaEquipeGestoraService::class)->atualizarPessoaEquipeGestora(
                        $servidor->fresh(),
                        $data,
                        $dadosGestao,
                    );
                });
            }

            if ($this->pessoaEhObras($servidor)) {
                return DB::transaction(function () use ($servidor, $data, $dadosGestao): Servidor {
                    app(PessoaObrasService::class)->encerrarVinculosObras(
                        $servidor,
                        reconciliarAcesso: false,
                    );

                    return app(PessoaEquipeGestoraService::class)->atualizarPessoaEquipeGestora(
                        $servidor->fresh(),
                        $data,
                        $dadosGestao,
                    );
                });
            }

            return app(PessoaEquipeGestoraService::class)->atualizarPessoaEquipeGestora(
                $servidor,
                $data,
                $dadosGestao,
            );
        }

        if ($this->fluxoProfessor($data, $vinculos)) {
            $registros = $vinculos['matriculas_professor']
                ?? $vinculos['registros_professor']
                ?? $data['matriculas_professor']
                ?? $data['registros_professor']
                ?? $vinculos;

            if ($servidor->vinculosAtivos()
                ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
                ->exists()) {
                return app(PessoaEquipeGestoraService::class)->converterEquipeGestoraParaProfessor(
                    $servidor,
                    $data,
                    $registros,
                    $this->dadosAcesso($data),
                );
            }

            if ($this->pessoaEhManutencao($servidor)) {
                return DB::transaction(function () use ($servidor, $data, $registros): Servidor {
                    app(PessoaManutencaoService::class)->encerrarVinculosManutencao(
                        $servidor,
                        reconciliarAcesso: false,
                    );

                    return app(PessoaProfessorService::class)->atualizarPessoaProfessor(
                        $servidor->fresh(),
                        $data,
                        $registros,
                        $this->dadosAcesso($data),
                    );
                });
            }

            if ($this->pessoaEhObras($servidor)) {
                return DB::transaction(function () use ($servidor, $data, $registros): Servidor {
                    app(PessoaObrasService::class)->encerrarVinculosObras(
                        $servidor,
                        reconciliarAcesso: false,
                    );

                    return app(PessoaProfessorService::class)->atualizarPessoaProfessor(
                        $servidor->fresh(),
                        $data,
                        $registros,
                        $this->dadosAcesso($data),
                    );
                });
            }

            return app(PessoaProfessorService::class)->atualizarPessoaProfessor(
                $servidor,
                $data,
                $registros,
                $this->dadosAcesso($data),
            );
        }

        return app(PessoaVinculoService::class)->atualizarPessoaComVinculos($servidor, $data, $vinculos);
    }

    private function fluxoProfessor(array $data, array $vinculos): bool
    {
        $cargo = $data['cargo'] ?? null;

        if ($cargo === 'professor') {
            return true;
        }

        return array_key_exists('registros_professor', $vinculos)
            || array_key_exists('registros_professor', $data)
            || array_key_exists('matriculas_professor', $vinculos)
            || array_key_exists('matriculas_professor', $data);
    }

    private function fluxoMotorista(array $data, array $vinculos): bool
    {
        return ($data['cargo'] ?? null) === 'motorista'
            || array_key_exists('motorista', $data)
            || array_key_exists('motorista', $vinculos);
    }

    private function fluxoTransporte(array $data, array $vinculos): bool
    {
        return ($data['cargo'] ?? null) === 'transporte'
            || array_key_exists('transporte', $data)
            || array_key_exists('transporte', $vinculos);
    }

    private function fluxoAssessoriaPedagogica(array $data, array $vinculos): bool
    {
        return ($data['cargo'] ?? null) === 'assessoria_pedagogica'
            || array_key_exists('assessoria_pedagogica', $data)
            || array_key_exists('assessoria_pedagogica', $vinculos);
    }

    private function criarPessoaMotorista(array $data, array $vinculos): Servidor
    {
        $matricula = $vinculos['motorista']['matricula']
            ?? $data['motorista']['matricula']
            ?? $data['matricula']
            ?? null;

        return DB::transaction(function () use ($data, $matricula): Servidor {
            $motorista = Servidor::query()->create([
                ...collect($data)->only([
                    'nome',
                    'cpf',
                    'email',
                    'telefone',
                    'status',
                    'observacoes',
                ])->all(),
                'user_id' => null,
                'id_escola' => null,
                'setor_id' => null,
                'matricula' => filled($matricula) ? trim((string) $matricula) : null,
            ]);

            $this->vincularFuncao($motorista, FuncaoAdministrativa::motoristaPadrao(), [
                'origem' => 'pessoas',
                'matricula' => $motorista->matricula,
                'id_escola' => null,
                'setor_id' => null,
            ]);

            return $motorista->fresh(['vinculosAtivos.funcaoAdministrativa']);
        });
    }

    private function atualizarPessoaMotorista(Servidor $servidor, array $data, array $vinculos): Servidor
    {
        $matricula = $vinculos['motorista']['matricula']
            ?? $data['motorista']['matricula']
            ?? $data['matricula']
            ?? null;

        return DB::transaction(function () use ($servidor, $data, $matricula): Servidor {
            $servidor = Servidor::query()
                ->with('vinculosAtivos.funcaoAdministrativa')
                ->lockForUpdate()
                ->findOrFail($servidor->getKey());

            $possuiOutroCargo = $servidor->professores()->where('ativo', true)->exists()
                || $servidor->vinculosAtivos->contains(
                    fn (ServidorFuncaoAdministrativa $vinculo): bool => ! (bool) $vinculo
                        ->funcaoAdministrativa?->ehMotorista(),
                );

            if (filled($servidor->user_id)) {
                throw ValidationException::withMessages([
                    'cargo' => 'Remova primeiro o acesso desta pessoa ao sistema antes de defini-la como motorista.',
                ]);
            }

            if ($possuiOutroCargo) {
                throw ValidationException::withMessages([
                    'cargo' => 'Converta ou encerre os outros vínculos funcionais antes de definir esta pessoa como motorista.',
                ]);
            }

            $servidor->forceFill([
                ...collect($data)->only([
                    'nome',
                    'cpf',
                    'email',
                    'telefone',
                    'status',
                    'observacoes',
                ])->all(),
                'id_escola' => null,
                'setor_id' => null,
                'matricula' => filled($matricula) ? trim((string) $matricula) : null,
            ])->save();

            $funcaoMotorista = FuncaoAdministrativa::motoristaPadrao();
            $vinculo = $servidor->servidorFuncoes()
                ->ativos()
                ->where('funcao_administrativa_id', $funcaoMotorista->getKey())
                ->first()
                ?? $this->vincularFuncao($servidor, $funcaoMotorista, [
                    'origem' => 'pessoas',
                    'matricula' => $servidor->matricula,
                    'id_escola' => null,
                    'setor_id' => null,
                ]);
            $vinculo->forceFill([
                'matricula' => $servidor->matricula,
                'id_escola' => null,
                'setor_id' => null,
            ])->save();

            return $servidor->fresh(['vinculosAtivos.funcaoAdministrativa']);
        });
    }

    private function criarPessoaOperacional(array $data, array $vinculos, string $tipo): Servidor
    {
        $dados = $vinculos[$tipo] ?? $data[$tipo] ?? $vinculos;
        $matricula = $dados['matricula'] ?? $data['matricula'] ?? null;

        return DB::transaction(function () use ($data, $matricula, $tipo): Servidor {
            $pessoa = Servidor::query()->create([
                ...collect($data)->only([
                    'nome',
                    'cpf',
                    'email',
                    'telefone',
                    'status',
                    'observacoes',
                ])->all(),
                'user_id' => null,
                'id_escola' => null,
                'setor_id' => null,
                'matricula' => filled($matricula) ? trim((string) $matricula) : null,
            ]);

            $funcao = $tipo === 'transporte'
                ? FuncaoAdministrativa::transportePadrao()
                : FuncaoAdministrativa::assessoriaPedagogicaPadrao();

            $this->vincularFuncao($pessoa, $funcao, [
                'origem' => 'pessoas',
                'matricula' => $pessoa->matricula,
                'id_escola' => null,
                'setor_id' => null,
            ]);

            app(PessoaAcessoService::class)->provisionarAcessosDoServidor($pessoa->fresh());

            return $pessoa->fresh(['vinculosAtivos.funcaoAdministrativa']);
        });
    }

    private function atualizarPessoaOperacional(Servidor $servidor, array $data, array $vinculos, string $tipo): Servidor
    {
        $dados = $vinculos[$tipo] ?? $data[$tipo] ?? $vinculos;
        $matricula = $dados['matricula'] ?? $data['matricula'] ?? null;

        return DB::transaction(function () use ($servidor, $data, $matricula, $tipo): Servidor {
            $servidor = Servidor::query()
                ->with('vinculosAtivos.funcaoAdministrativa')
                ->lockForUpdate()
                ->findOrFail($servidor->getKey());

            $servidor->forceFill([
                ...collect($data)->only([
                    'nome',
                    'cpf',
                    'email',
                    'telefone',
                    'status',
                    'observacoes',
                ])->all(),
                'id_escola' => null,
                'setor_id' => null,
                'matricula' => filled($matricula) ? trim((string) $matricula) : null,
            ])->save();

            $funcao = $tipo === 'transporte'
                ? FuncaoAdministrativa::transportePadrao()
                : FuncaoAdministrativa::assessoriaPedagogicaPadrao();

            $this->encerrarVinculosOperacionaisIncompativeis($servidor, $funcao);

            $vinculo = $servidor->servidorFuncoes()
                ->ativos()
                ->where('funcao_administrativa_id', $funcao->getKey())
                ->first()
                ?? $this->vincularFuncao($servidor, $funcao, [
                    'origem' => 'pessoas',
                    'matricula' => $servidor->matricula,
                    'id_escola' => null,
                    'setor_id' => null,
                ]);
            $vinculo->forceFill([
                'matricula' => $servidor->matricula,
                'id_escola' => null,
                'setor_id' => null,
            ])->save();

            app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidor->fresh());

            return $servidor->fresh(['vinculosAtivos.funcaoAdministrativa']);
        });
    }

    private function encerrarVinculosOperacionaisIncompativeis(
        Servidor $servidor,
        FuncaoAdministrativa $funcaoAtual,
    ): void {
        $funcaoIdsExclusivas = FuncaoAdministrativa::query()
            ->whereIn('codigo', [
                'manutencao',
                'obras',
                'transporte',
                'assessoria-pedagogica',
            ])
            ->whereKeyNot($funcaoAtual->getKey())
            ->pluck('id');

        ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $servidor->getKey())
            ->whereIn('funcao_administrativa_id', $funcaoIdsExclusivas)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->lockForUpdate()
            ->update([
                'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                'data_fim' => now()->toDateString(),
                'updated_at' => now(),
            ]);
    }

    private function fluxoManutencao(array $data, array $vinculos): bool
    {
        return ($data['cargo'] ?? null) === 'manutencao'
            || array_key_exists('manutencao', $data)
            || array_key_exists('manutencao', $vinculos);
    }

    private function fluxoObras(array $data, array $vinculos): bool
    {
        return ($data['cargo'] ?? null) === 'obras'
            || array_key_exists('obras', $data)
            || array_key_exists('obras', $vinculos);
    }

    private function fluxoEquipeGestora(array $data, array $vinculos): bool
    {
        return ($data['cargo'] ?? null) === 'equipe_gestora'
            || array_key_exists('equipe_gestora', $data)
            || array_key_exists('equipe_gestora', $vinculos);
    }

    private function dadosEquipeGestora(array $data, array $vinculos): array
    {
        $dados = $vinculos['equipe_gestora']
            ?? $data['equipe_gestora']
            ?? $vinculos;

        return is_array($dados) ? [...$data, ...$dados] : $data;
    }

    private function dadosManutencao(array $data, array $vinculos): array
    {
        $dados = $vinculos['manutencao']
            ?? $data['manutencao']
            ?? $vinculos;

        return is_array($dados) ? [...$data, ...$dados] : $data;
    }

    private function dadosObras(array $data, array $vinculos): array
    {
        $dados = $vinculos['obras']
            ?? $data['obras']
            ?? $vinculos;

        return is_array($dados) ? [...$data, ...$dados] : $data;
    }

    private function pessoaEhManutencao(Servidor $servidor): bool
    {
        return $servidor->vinculosAtivos()
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->manutencao())
            ->exists();
    }

    private function pessoaEhObras(Servidor $servidor): bool
    {
        return $servidor->vinculosAtivos()
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->obras())
            ->exists();
    }

    private function dadosAcesso(array $data): array
    {
        $permissoesExtras = collect($data)
            ->filter(fn ($_, string $key): bool => str_starts_with($key, 'permissions_'))
            ->flatMap(fn ($permissions) => is_array($permissions) ? $permissions : [$permissions])
            ->filter(fn ($permission): bool => filled($permission))
            ->unique()
            ->values()
            ->all();

        return [
            'roles' => $data['roles_adicionais'] ?? $data['roles'] ?? [],
            'roles_adicionais' => $data['roles_adicionais'] ?? $data['roles'] ?? [],
            'email_approved' => $data['email_approved'] ?? true,
            'usar_permissoes_extras' => $data['usar_permissoes_extras'] ?? false,
            'permissoes_extras' => $permissoesExtras,
        ];
    }

    public function sincronizarProfessor(Professor $professor): ?Servidor
    {
        if (! Schema::hasTable('servidores') || ! Schema::hasColumn('professores', 'servidor_id')) {
            return null;
        }

        return DB::transaction(function () use ($professor): Servidor {
            $servidor = $this->criarOuAtualizarServidorDoProfessor($professor);

            $this->vincularFuncao($servidor, FuncaoAdministrativa::professorPadrao(), [
                'origem' => 'professor',
                'id_escola' => $professor->id_escola,
                'setor_id' => $this->setorIdDoProfessor($professor),
            ]);

            return $servidor->fresh(['funcoesAtivas']);
        });
    }

    public function backfillProfessores(): array
    {
        $resultado = [
            'professores_processados' => 0,
            'servidores_criados_ou_atualizados' => 0,
            'funcoes_vinculadas' => 0,
        ];

        Professor::query()
            ->with(['escola'])
            ->orderBy('id')
            ->chunkById(100, function ($professores) use (&$resultado): void {
                foreach ($professores as $professor) {
                    $antes = $professor->servidor_id;
                    $servidor = $this->sincronizarProfessor($professor);

                    $resultado['professores_processados']++;
                    $resultado['servidores_criados_ou_atualizados'] += $servidor ? 1 : 0;
                    $resultado['funcoes_vinculadas'] += $antes || $servidor ? 1 : 0;
                }
            });

        return $resultado;
    }

    public function vincularFuncao(Servidor $servidor, FuncaoAdministrativa|int $funcao, array $contexto = []): ServidorFuncaoAdministrativa
    {
        $funcao = $funcao instanceof FuncaoAdministrativa
            ? $funcao
            : FuncaoAdministrativa::query()->findOrFail($funcao);

        if ($funcao->ehEquipeGestora()) {
            throw ValidationException::withMessages([
                'equipe_gestora' => 'Funções da Equipe Gestora só podem ser alteradas pelo fluxo próprio, que preserva vigência e principais.',
            ]);
        }

        return DB::transaction(function () use ($servidor, $funcao, $contexto): ServidorFuncaoAdministrativa {
            $servidor = $servidor->fresh(['professores', 'escola']);

            if ($funcao->exige_professor) {
                // Preferir escola/matrícula do contexto do vínculo (podem ainda não estar no agregado da pessoa).
                if (filled($contexto['id_escola'] ?? null) && blank($servidor->id_escola)) {
                    $servidor->id_escola = (int) $contexto['id_escola'];
                }
                if (filled($contexto['matricula'] ?? null) && blank($servidor->matricula)) {
                    $servidor->matricula = (string) $contexto['matricula'];
                }
                if (filled($contexto['setor_id'] ?? null) && blank($servidor->setor_id)) {
                    $servidor->setor_id = (int) $contexto['setor_id'];
                }

                $this->garantirProfessorParaServidor($servidor);
            }

            $origem = (string) ($contexto['origem'] ?? 'manual');
            $query = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $servidor->id)
                ->where('funcao_administrativa_id', $funcao->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO);

            if ($origem !== 'manual') {
                $query->where('origem', $origem);
            }

            $vinculo = $query->first() ?? new ServidorFuncaoAdministrativa([
                'servidor_id' => $servidor->id,
                'funcao_administrativa_id' => $funcao->id,
                'origem' => $origem,
            ]);

            $vinculo->fill([
                'matricula' => $contexto['matricula'] ?? $servidor->matricula,
                'id_escola' => $contexto['id_escola'] ?? $servidor->id_escola,
                'setor_id' => $contexto['setor_id'] ?? $servidor->setor_id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'portaria' => array_key_exists('portaria', $contexto) ? $contexto['portaria'] : $vinculo->portaria,
                'data_inicio' => $contexto['data_inicio'] ?? $vinculo->data_inicio,
                'data_fim' => null,
            ]);
            $vinculo->save();

            if (array_key_exists('turma_ids', $contexto) && Schema::hasTable('servidor_funcao_turma')) {
                $turmaIds = collect($contexto['turma_ids'])
                    ->filter(fn ($id): bool => filled($id))
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $vinculo->turmas()->sync($turmaIds);
            }

            $vinculo = $vinculo->fresh(['turmas']);

            app(PessoaAcessoService::class)->vincularProfessorAoVinculo($vinculo);

            return $vinculo;
        });
    }

    public function removerFuncao(Servidor $servidor, FuncaoAdministrativa|int $funcao): void
    {
        $funcao = $funcao instanceof FuncaoAdministrativa
            ? $funcao
            : FuncaoAdministrativa::query()->findOrFail($funcao);

        if ($funcao->ehEquipeGestora()) {
            throw ValidationException::withMessages([
                'equipe_gestora' => 'Funções da Equipe Gestora só podem ser encerradas pelo fluxo próprio.',
            ]);
        }

        DB::transaction(function () use ($servidor, $funcao): void {
            $servidor = $servidor->fresh(['professores']);

            if ($funcao->exige_professor) {
                $professor = $servidor->professores()->first();

                if ($professor && $this->professorPossuiVinculosPedagogicos($professor)) {
                    throw ValidationException::withMessages([
                        'vinculos_funcionais' => 'Não é possível remover a função Professor enquanto houver vínculos pedagógicos ativos.',
                    ]);
                }
            }

            $vinculos = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $servidor->id)
                ->where('funcao_administrativa_id', $funcao->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->get();

            if ($vinculos->isEmpty()) {
                return;
            }

            foreach ($vinculos as $vinculo) {
                if (Schema::hasTable('servidor_funcao_turma')) {
                    if (Schema::hasColumn('servidor_funcao_turma', 'status')) {
                        app(PessoaEquipeGestoraService::class)->encerrarVinculo($vinculo);
                    } else {
                        $vinculo->turmas()->detach();
                    }
                }
            }

            ServidorFuncaoAdministrativa::query()
                ->whereKey($vinculos->pluck('id')->all())
                ->update([
                    'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                    'data_fim' => now()->toDateString(),
                    'updated_at' => now(),
                ]);
        });
    }

    public function sincronizarFuncoesSelecionadas(Servidor $servidor, array $funcaoIds): void
    {
        $this->sincronizarVinculosFuncionais($servidor, $funcaoIds);
    }

    public function sincronizarVinculosFuncionais(Servidor $servidor, array $vinculos): void
    {
        $vinculos = $this->normalizarVinculosFuncionais($vinculos);
        $funcaoIds = $vinculos->pluck('funcao_administrativa_id')->values();
        $ativas = $servidor->funcoesAtivas()
            ->pluck('funcao_administrativa.id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        foreach ($vinculos as $vinculo) {
            $this->vincularFuncao($servidor, (int) $vinculo['funcao_administrativa_id'], [
                'matricula' => $vinculo['matricula'] ?? null,
                'setor_id' => $vinculo['setor_id'] ?? null,
                'id_escola' => $vinculo['id_escola'] ?? null,
                'portaria' => $vinculo['portaria'] ?? null,
                'turma_ids' => $vinculo['turma_ids'] ?? [],
            ]);
        }

        foreach ($ativas->diff($funcaoIds) as $funcaoId) {
            $this->removerFuncao($servidor, $funcaoId);
        }
    }

    public function aplicarEscopoVisibilidade(Builder $query, ?User $user): Builder
    {
        return app(PessoaScopeService::class)->applyPessoaScope($query, $user);
    }

    /**
     * Usado só para impedir remoção parcial de lotação ainda com TCP ativo no form.
     * Avaliações NÃO bloqueiam: professor_id é metadado de exportação e pode ser anulado.
     */
    public function professorPossuiVinculosPedagogicos(Professor $professor): bool
    {
        if (
            Schema::hasTable('turma_componente_professor')
            && DB::table('turma_componente_professor')
                ->where('professor_id', $professor->id)
                ->where('tem_professor', true)
                ->exists()
        ) {
            return true;
        }

        return false;
    }

    public function pessoaPodeSerExcluida(Servidor $pessoa): bool
    {
        return ! $this->possuiCargoProtegidoContraExclusao($pessoa)
            && ! $this->possuiEventosFuturosComoMotorista($pessoa);
    }

    public function possuiEventosFuturosComoMotorista(Servidor $pessoa): bool
    {
        if (! Schema::hasTable('evento_calendario_transporte_alocacoes')) {
            return false;
        }

        return $pessoa->alocacoesTransporteAtivas()
            ->whereHas('evento', fn (Builder $eventos): Builder => $eventos
                ->where('data_fim', '>=', now()))
            ->exists();
    }

    private function possuiCargoProtegidoContraExclusao(Servidor $pessoa): bool
    {
        return $pessoa->servidorFuncoes()
            ->whereHas('funcaoAdministrativa', function (Builder $funcoes): void {
                $funcoes->where(function (Builder $cargos): void {
                    $cargos
                        ->equipeGestora()
                        ->orWhere(fn (Builder $manutencao): Builder => $manutencao->manutencao())
                        ->orWhere(fn (Builder $obras): Builder => $obras->obras());
                });
            })
            ->exists();
    }

    public function motivoBloqueioExclusao(Servidor $pessoa): ?string
    {
        if ($this->possuiCargoProtegidoContraExclusao($pessoa)) {
            return "{$pessoa->nome} não pode ser excluída porque possui histórico em cargo funcional protegido. "
                .'Inative a pessoa ou converta o cargo pelo fluxo próprio; os vínculos históricos devem ser preservados.';
        }

        if ($this->possuiEventosFuturosComoMotorista($pessoa)) {
            $quantidade = $pessoa->alocacoesTransporteAtivas()
                ->whereHas('evento', fn (Builder $eventos): Builder => $eventos
                    ->where('data_fim', '>=', now()))
                ->distinct('evento_calendario_id')
                ->count('evento_calendario_id');

            return "{$pessoa->nome} possui vínculo como motorista em {$quantidade} evento(s) atual(is) ou futuro(s). "
                .'Substitua o motorista nesses eventos antes de excluir a pessoa. Os eventos já atendidos permanecerão no histórico.';
        }

        return null;
    }

    /**
     * Anula referências ao professor em tabelas pedagógicas sem apagar avaliações/alunos.
     *
     * @param  list<int>  $professorIds
     */
    public function desvincularProfessorDePedagogico(array $professorIds): void
    {
        if ($professorIds === []) {
            return;
        }

        if (Schema::hasTable('turma_componente_professor')) {
            DB::table('turma_componente_professor')
                ->whereIn('professor_id', $professorIds)
                ->update([
                    'professor_id' => null,
                    'tem_professor' => false,
                    'updated_at' => now(),
                ]);
        }

        // Avaliação fica no aluno (payload JSON). Professor no documento é só contexto
        // denormalizado em professor_ids; não há tabela linha-por-resposta para limpar.
    }

    public function excluirPessoa(Servidor $pessoa): void
    {
        DB::transaction(function () use ($pessoa): void {
            $pessoa = Servidor::query()
                ->lockForUpdate()
                ->findOrFail($pessoa->getKey());

            if ($motivo = $this->motivoBloqueioExclusao($pessoa)) {
                throw ValidationException::withMessages([
                    'pessoa' => $motivo,
                ]);
            }

            $pessoa->load(['professores', 'professorMatriculas', 'servidorFuncoes']);
            $professorIds = $pessoa->professores->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $this->desvincularProfessorDePedagogico($professorIds);

            if ($professorIds !== []) {
                Professor::query()->whereIn('id', $professorIds)->delete();
            }

            if (Schema::hasTable('professor_matriculas')) {
                DB::table('professor_matriculas')->where('servidor_id', $pessoa->id)->delete();
            }

            if (Schema::hasTable('servidor_funcao_turma')) {
                $vinculoIds = $pessoa->servidorFuncoes->pluck('id')->all();
                if ($vinculoIds !== []) {
                    DB::table('servidor_funcao_turma')
                        ->whereIn('servidor_funcao_administrativa_id', $vinculoIds)
                        ->delete();
                }
            }

            if (Schema::hasTable('evento_calendario_transporte_alocacoes')) {
                DB::table('evento_calendario_transporte_alocacoes')
                    ->where('motorista_id', $pessoa->id)
                    ->update([
                        'motorista_nome' => $pessoa->nome,
                        'motorista_cpf' => $pessoa->cpf,
                        'motorista_matricula' => $pessoa->matricula,
                        'motorista_id' => null,
                        'updated_at' => now(),
                    ]);
            }

            ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $pessoa->id)
                ->delete();

            // Desvincula login sem apagar a conta de usuário.
            if (filled($pessoa->user_id)) {
                $pessoa->update(['user_id' => null]);
            }

            $pessoa->delete();
        });
    }

    /**
     * @param  iterable<int, Servidor>  $pessoas
     * @return array{excluidos: int, bloqueados: list<string>}
     */
    public function excluirPessoasEmMassa(iterable $pessoas): array
    {
        $excluidos = 0;
        $bloqueados = [];

        foreach ($pessoas as $pessoa) {
            if (! $pessoa instanceof Servidor) {
                continue;
            }

            $motivo = $this->motivoBloqueioExclusao($pessoa);
            if ($motivo) {
                $bloqueados[] = $motivo;

                continue;
            }

            $this->excluirPessoa($pessoa);
            $excluidos++;
        }

        return [
            'excluidos' => $excluidos,
            'bloqueados' => $bloqueados,
        ];
    }

    private function normalizarVinculosFuncionais(array $vinculos)
    {
        return collect($vinculos)
            ->map(function ($vinculo): ?array {
                if (is_array($vinculo)) {
                    $funcaoId = $vinculo['funcao_administrativa_id'] ?? $vinculo['id'] ?? null;

                    if (! filled($funcaoId)) {
                        return null;
                    }

                    return [
                        'funcao_administrativa_id' => (int) $funcaoId,
                        'matricula' => $vinculo['matricula'] ?? null,
                        'setor_id' => $vinculo['setor_id'] ?? null,
                        'id_escola' => $vinculo['id_escola'] ?? null,
                        'portaria' => filled($vinculo['portaria'] ?? null) ? (string) $vinculo['portaria'] : null,
                        'turma_ids' => $vinculo['turma_ids'] ?? $vinculo['turmas'] ?? [],
                    ];
                }

                if (! filled($vinculo)) {
                    return null;
                }

                return [
                    'funcao_administrativa_id' => (int) $vinculo,
                    'portaria' => null,
                    'turma_ids' => [],
                ];
            })
            ->filter()
            ->keyBy('funcao_administrativa_id')
            ->values();
    }

    private function dadosServidor(array $data): array
    {
        return [
            'cpf' => $data['cpf'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'id_escola' => $data['id_escola'] ?? null,
            'setor_id' => $data['setor_id'] ?? null,
            'matricula' => $data['matricula'] ?? null,
            'nome' => $data['nome'] ?? null,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'status' => $data['status'] ?? Servidor::STATUS_ATIVO,
            'observacoes' => $data['observacoes'] ?? null,
        ];
    }

    private function criarOuAtualizarServidorDoProfessor(Professor $professor): Servidor
    {
        $professor->loadMissing('escola', 'servidor');

        $payload = [
            'user_id' => $professor->user_id,
            'id_escola' => $professor->id_escola,
            'setor_id' => $this->setorIdDoProfessor($professor),
            'matricula' => $professor->matricula,
            'nome' => $professor->nome,
            'email' => $professor->email,
            'telefone' => $professor->telefone,
            'status' => Servidor::STATUS_ATIVO,
        ];

        $servidor = $professor->servidor;

        if (! $servidor && filled($professor->id_escola) && filled($professor->matricula)) {
            $servidor = Servidor::query()
                ->where('id_escola', $professor->id_escola)
                ->where('matricula', $professor->matricula)
                ->whereDoesntHave('professores')
                ->first();
        }

        if ($servidor) {
            $servidor->forceFill($payload)->save();
        } else {
            $servidor = Servidor::query()->create($payload);
        }

        if ((int) ($professor->servidor_id ?? 0) !== (int) $servidor->id) {
            $professor->forceFill(['servidor_id' => $servidor->id])->saveQuietly();
        }

        return $servidor;
    }

    private function garantirProfessorParaServidor(Servidor $servidor): Professor
    {
        if (filled($servidor->id_escola) && filled($servidor->matricula)) {
            $correspondente = $servidor->professores()
                ->where('id_escola', $servidor->id_escola)
                ->where('matricula', $servidor->matricula)
                ->first();

            if ($correspondente) {
                $correspondente->forceFill([
                    'nome' => $servidor->nome,
                    'email' => $servidor->email,
                    'telefone' => $servidor->telefone,
                    'user_id' => $servidor->user_id,
                ])->saveQuietly();

                return $correspondente;
            }
        }

        $professor = $servidor->professores()->first();

        if ($professor) {
            $payload = [
                'nome' => $servidor->nome,
                'email' => $servidor->email,
                'telefone' => $servidor->telefone,
                'user_id' => $servidor->user_id,
            ];

            if (filled($servidor->id_escola)) {
                $payload['id_escola'] = $servidor->id_escola;
            }

            if (filled($servidor->matricula) && ! $this->professorMatriculaConflita($professor, $servidor)) {
                $payload['matricula'] = $servidor->matricula;
            }

            $professor->forceFill($payload)->saveQuietly();

            return $professor;
        }

        if (blank($servidor->id_escola) || blank($servidor->matricula) || blank($servidor->nome)) {
            throw ValidationException::withMessages([
                'vinculos_funcionais' => 'Para atribuir a função Professor, informe escola, matrícula e nome do servidor.',
            ]);
        }

        $professor = Professor::query()
            ->where('id_escola', $servidor->id_escola)
            ->where('matricula', $servidor->matricula)
            ->first();

        if ($professor) {
            $professor->forceFill([
                'servidor_id' => $servidor->id,
                'nome' => $servidor->nome,
                'email' => $servidor->email,
                'telefone' => $servidor->telefone,
                'user_id' => $servidor->user_id,
            ])->saveQuietly();

            return $professor;
        }

        return Professor::query()->create([
            'servidor_id' => $servidor->id,
            'user_id' => $servidor->user_id,
            'id_escola' => $servidor->id_escola,
            'matricula' => $servidor->matricula,
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'telefone' => $servidor->telefone,
        ]);
    }

    private function professorMatriculaConflita(Professor $professor, Servidor $servidor): bool
    {
        if (blank($servidor->id_escola) || blank($servidor->matricula)) {
            return false;
        }

        return Professor::query()
            ->where('id_escola', $servidor->id_escola)
            ->where('matricula', $servidor->matricula)
            ->where('id', '!=', $professor->id)
            ->exists();
    }

    private function setorIdDoProfessor(Professor $professor): ?int
    {
        return $professor->escola?->setor_id ? (int) $professor->escola->setor_id : null;
    }
}
