<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\Turma;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PessoaEquipeGestoraService
{
    public function __construct(
        private readonly PessoaAcessoService $pessoaAcessoService,
        private readonly PessoaProfessorService $pessoaProfessorService,
        private readonly ServidorService $servidorService,
    ) {}

    public function criarPessoaEquipeGestora(array $dadosPessoa, array $dadosGestao): Servidor
    {
        return DB::transaction(function () use ($dadosPessoa, $dadosGestao): Servidor {
            $pessoa = Servidor::query()->create($this->dadosPessoa($dadosPessoa, statusPadraoAtivo: true));

            return $this->sincronizarInterno($pessoa, $dadosGestao, bloquearProfessorAtivo: true);
        });
    }

    public function atualizarPessoaEquipeGestora(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $dadosGestao,
    ): Servidor {
        return DB::transaction(function () use ($pessoa, $dadosPessoa, $dadosGestao): Servidor {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);
            $pessoa->update($this->dadosPessoa($dadosPessoa));

            return $this->sincronizarInterno(
                $pessoa->fresh(),
                $dadosGestao,
                bloquearProfessorAtivo: true,
            );
        });
    }

    public function sincronizar(Pessoa|Servidor $pessoa, array $dadosGestao): Servidor
    {
        return DB::transaction(fn (): Servidor => $this->sincronizarInterno(
            Servidor::query()->lockForUpdate()->findOrFail($pessoa->id),
            $dadosGestao,
            bloquearProfessorAtivo: true,
        ));
    }

    public function converterProfessorParaEquipeGestora(
        Pessoa|Servidor $pessoa,
        array $dadosGestao,
        array $dadosPessoa = [],
    ): Servidor {
        return DB::transaction(function () use ($pessoa, $dadosGestao, $dadosPessoa): Servidor {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);
            if ($dadosPessoa !== []) {
                $pessoa->update($this->dadosPessoa($dadosPessoa));
            }
            $professores = $pessoa->professores()->where('ativo', true)->lockForUpdate()->get();

            if ($professores->isEmpty()) {
                return $this->sincronizarInterno($pessoa, $dadosGestao, bloquearProfessorAtivo: false);
            }

            $escolasProfessor = $professores
                ->pluck('id_escola')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
            $escolaDestino = (int) ($dadosGestao['id_escola'] ?? 0);

            if ($escolasProfessor->count() !== 1 || $escolasProfessor->first() !== $escolaDestino) {
                throw ValidationException::withMessages([
                    'id_escola' => 'A conversão exige que todas as lotações ativas do professor pertençam à escola selecionada.',
                ]);
            }

            foreach ($professores as $professor) {
                if ($this->servidorService->professorPossuiVinculosPedagogicos($professor)) {
                    throw ValidationException::withMessages([
                        'equipe_gestora' => 'Não é possível converter o professor enquanto houver vínculos ativos com turmas e componentes.',
                    ]);
                }
            }

            $vinculosProfessor = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $pessoa->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->where('exige_professor', true))
                ->lockForUpdate()
                ->get();

            foreach ($vinculosProfessor as $vinculo) {
                $this->encerrarVinculo($vinculo);
            }

            Professor::query()
                ->whereKey($professores->pluck('id')->all())
                ->update([
                    'ativo' => false,
                    'desativado_em' => now(),
                    'desativado_por_id' => auth()->id(),
                    'motivo_desativacao' => 'Conversão para Equipe Gestora',
                    'updated_at' => now(),
                ]);

            return $this->sincronizarInterno($pessoa->fresh(), $dadosGestao, bloquearProfessorAtivo: false);
        });
    }

    public function converterEquipeGestoraParaProfessor(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $registrosProfessor,
        array $acesso = [],
    ): Servidor {
        return DB::transaction(function () use ($pessoa, $dadosPessoa, $registrosProfessor, $acesso): Servidor {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);
            $matriculas = $this->pessoaProfessorService->normalizarMatriculas($registrosProfessor);
            $escolaIds = $matriculas
                ->flatMap(fn (array $matricula): array => $matricula['escolas'] ?? [])
                ->pluck('id_escola')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            if ($escolaIds->count() !== 1 || ! Escola::query()->ativas()->whereKey($escolaIds->first())->exists()) {
                throw ValidationException::withMessages([
                    'registros_professor' => 'A conversão para Professor exige ao menos uma lotação válida em uma única escola.',
                ]);
            }

            $this->encerrarVinculosEquipeGestora($pessoa, reconciliarAcesso: false);

            $dadosPessoa = [...$this->dadosPessoaAtual($pessoa), ...$dadosPessoa];
            $professor = $this->pessoaProfessorService->atualizarPessoaProfessor(
                $pessoa,
                $dadosPessoa,
                $registrosProfessor,
                $acesso,
            );

            $professor->professores()
                ->where('ativo', true)
                ->update([
                    'desativado_em' => null,
                    'desativado_por_id' => null,
                    'motivo_desativacao' => null,
                    'updated_at' => now(),
                ]);

            $this->pessoaAcessoService->provisionarAcessosDoServidor($professor->fresh());

            return $this->carregar($professor->fresh());
        });
    }

    public function encerrarVinculosEquipeGestora(
        Pessoa|Servidor $pessoa,
        ?string $dataFim = null,
        bool $reconciliarAcesso = true,
    ): void
    {
        $vinculos = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
            ->lockForUpdate()
            ->get();

        foreach ($vinculos as $vinculo) {
            $this->encerrarVinculo($vinculo, $dataFim);
        }

        if ($reconciliarAcesso) {
            $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());
        }
    }

    public function encerrarVinculo(ServidorFuncaoAdministrativa $vinculo, ?string $dataFim = null): void
    {
        $dataFim = $dataFim ?: now()->toDateString();

        ServidorFuncaoTurma::query()
            ->where('servidor_funcao_administrativa_id', $vinculo->id)
            ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
            ->update([
                'principal' => false,
                'status' => ServidorFuncaoTurma::STATUS_INATIVO,
                'data_fim' => $dataFim,
                'updated_at' => now(),
            ]);

        $vinculo->update([
            'principal' => false,
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
            'data_fim' => $dataFim,
        ]);
    }

    private function sincronizarInterno(
        Servidor $pessoa,
        array $dados,
        bool $bloquearProfessorAtivo,
    ): Servidor {
        if ($pessoa->status !== Pessoa::STATUS_ATIVO) {
            $this->encerrarVinculosEquipeGestora($pessoa, reconciliarAcesso: false);
            $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());

            return $this->carregar($pessoa->fresh());
        }

        if ($bloquearProfessorAtivo && $pessoa->professores()->where('ativo', true)->exists()) {
            throw ValidationException::withMessages([
                'equipe_gestora' => 'Use a conversão de Professor para Equipe Gestora para preservar o histórico funcional.',
            ]);
        }

        $normalizado = $this->normalizarEValidar($dados);
        $matriculas = $this->sincronizarMatriculas($pessoa, $normalizado['matriculas']);
        // Serializa todas as decisões de principal e mudança funcional da escola,
        // inclusive quando ainda não existe linha principal para ser bloqueada.
        $escola = Escola::query()->lockForUpdate()->findOrFail($normalizado['escola']->id);
        $possuiOutroVinculoEmEscolaDiferente = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereNotNull('id_escola')
            ->where('id_escola', '!=', $escola->id)
            ->whereHas('funcaoAdministrativa', function ($funcoes): void {
                $funcoes
                    ->where('direcao_escolar', false)
                    ->where('coordenacao_pedagogica', false)
                    ->where('secretaria_escolar', false);
            })
            ->lockForUpdate()
            ->exists();
        if ($possuiOutroVinculoEmEscolaDiferente) {
            throw ValidationException::withMessages([
                'id_escola' => 'A Pessoa possui outro vínculo funcional ativo em escola diferente. Encerre esse vínculo antes de incluí-la na Equipe Gestora.',
            ]);
        }

        $ativos = $this->vinculosGestoresAtivos($pessoa);
        if ($ativos->contains(
            fn (ServidorFuncaoAdministrativa $vinculo): bool =>
                (bool) $vinculo->funcaoAdministrativa?->temFlagsGestorasConflitantes()
        )) {
            throw ValidationException::withMessages([
                'cargos' => 'A Pessoa possui um vínculo gestor legado com flags conflitantes. Saneie esse vínculo antes de editar a Equipe Gestora.',
            ]);
        }

        $escolasAtuais = $ativos->pluck('id_escola')->filter()->map(fn ($id): int => (int) $id)->unique();

        if ($escolasAtuais->isNotEmpty() && ($escolasAtuais->count() > 1 || $escolasAtuais->first() !== (int) $escola->id)) {
            $this->validarInicioPosteriorAosVinculos($normalizado['data_inicio'], $ativos);
            $dataFimAnterior = $this->dataFimAnteriorAoInicio($normalizado['data_inicio']);

            foreach ($ativos as $vinculo) {
                $this->encerrarVinculo($vinculo, $dataFimAnterior);
            }
            $ativos = collect();
        }

        $tiposDesejados = collect([
            FuncaoAdministrativa::TIPO_DIRECAO => $normalizado['diretor'],
            FuncaoAdministrativa::TIPO_COORDENACAO => $normalizado['coordenador'],
            FuncaoAdministrativa::TIPO_SECRETARIA => $normalizado['secretario'],
        ])->filter();

        $portariaMudou = $ativos
            ->filter(fn (ServidorFuncaoAdministrativa $vinculo): bool => in_array(
                $vinculo->funcaoAdministrativa?->tipoEquipeGestora(),
                [FuncaoAdministrativa::TIPO_DIRECAO, FuncaoAdministrativa::TIPO_COORDENACAO],
                true,
            ))
            ->contains(fn (ServidorFuncaoAdministrativa $vinculo): bool =>
                (string) $vinculo->portaria !== (string) $normalizado['portaria']
            );

        if ($portariaMudou) {
            $vinculosComNovaPortaria = $ativos->filter(
                fn (ServidorFuncaoAdministrativa $vinculo): bool => in_array(
                    $vinculo->funcaoAdministrativa?->tipoEquipeGestora(),
                    [FuncaoAdministrativa::TIPO_DIRECAO, FuncaoAdministrativa::TIPO_COORDENACAO],
                    true,
                ),
            );
            $this->validarInicioPosteriorAosVinculos($normalizado['data_inicio'], $vinculosComNovaPortaria);
            $dataFimAnterior = $this->dataFimAnteriorAoInicio($normalizado['data_inicio']);

            foreach ($vinculosComNovaPortaria as $vinculo) {
                $this->encerrarVinculo($vinculo, $dataFimAnterior);
            }
            $ativos = $this->vinculosGestoresAtivos($pessoa);
        }

        foreach ($ativos as $vinculo) {
            $tipo = $vinculo->funcaoAdministrativa?->tipoEquipeGestora();
            if (! $tipo || ! $tiposDesejados->has($tipo)) {
                $this->encerrarVinculo($vinculo);
            }
        }

        $funcoes = [
            FuncaoAdministrativa::TIPO_DIRECAO => FuncaoAdministrativa::direcaoPadrao(),
            FuncaoAdministrativa::TIPO_COORDENACAO => FuncaoAdministrativa::coordenacaoPadrao(),
            FuncaoAdministrativa::TIPO_SECRETARIA => FuncaoAdministrativa::secretariaPadrao(),
        ];

        foreach ($tiposDesejados->keys() as $tipo) {
            $funcao = $funcoes[$tipo];
            $vinculosDoTipo = $this->vinculosGestoresAtivos($pessoa)
                ->filter(fn (ServidorFuncaoAdministrativa $vinculo): bool =>
                    $vinculo->funcaoAdministrativa?->tipoEquipeGestora() === $tipo
                );
            $vinculo = $vinculosDoTipo->first(
                fn (ServidorFuncaoAdministrativa $candidato): bool =>
                    (int) $candidato->funcao_administrativa_id === (int) $funcao->id
                    && (int) $candidato->id_escola === (int) $escola->id
            );

            foreach ($vinculosDoTipo as $duplicado) {
                if ($vinculo && (int) $duplicado->id === (int) $vinculo->id) {
                    continue;
                }

                $this->encerrarVinculo($duplicado);
            }

            $novo = ! $vinculo;

            $vinculo ??= new ServidorFuncaoAdministrativa([
                'servidor_id' => $pessoa->id,
                'funcao_administrativa_id' => $funcao->id,
                'origem' => 'equipe_gestora',
            ]);

            $principalDirecao = false;
            if ($tipo === FuncaoAdministrativa::TIPO_DIRECAO) {
                $principalDirecao = $this->resolverPrincipalDirecao(
                    $vinculo,
                    (int) $escola->id,
                    $normalizado['diretor_principal'],
                    $normalizado['diretor_principal_informado'],
                    $novo,
                );
            }

            $vinculo->fill([
                'matricula' => $matriculas->first()?->matricula,
                'id_escola' => $escola->id,
                'setor_id' => $escola->setor_id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'portaria' => $tipo === FuncaoAdministrativa::TIPO_SECRETARIA
                    ? null
                    : $normalizado['portaria'],
                'principal' => $principalDirecao,
                'data_inicio' => $normalizado['data_inicio'],
                'data_fim' => null,
            ])->save();

            if ($tipo === FuncaoAdministrativa::TIPO_COORDENACAO) {
                $this->sincronizarTurmasCoordenacao(
                    $vinculo,
                    $normalizado['turma_ids'],
                    $normalizado['turmas_principais_ids'],
                    $normalizado['turmas_vacancia_ids'],
                    $normalizado['data_inicio'],
                );
            }
        }

        $pessoa->update([
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'matricula' => $matriculas->first()?->matricula,
        ]);

        $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());

        return $this->carregar($pessoa->fresh());
    }

    /** @return array<string, mixed> */
    private function normalizarEValidar(array $dados): array
    {
        $cargos = collect($dados['cargos'] ?? [])
            ->map(fn ($cargo): string => mb_strtolower(trim((string) $cargo)))
            ->values();
        $diretor = $this->cargoAtivo($dados['diretor'] ?? null, $cargos, ['diretor', 'direcao']);
        $coordenador = $this->cargoAtivo($dados['coordenador'] ?? null, $cargos, ['coordenador', 'coordenacao']);
        $secretario = $this->cargoAtivo($dados['secretario'] ?? null, $cargos, ['secretario', 'secretaria']);

        if (! $diretor && ! $coordenador && ! $secretario) {
            throw ValidationException::withMessages([
                'cargos' => 'Selecione ao menos um cargo da Equipe Gestora.',
            ]);
        }

        if ($secretario && ($diretor || $coordenador)) {
            throw ValidationException::withMessages([
                'cargos' => 'Secretário é um cargo exclusivo e não pode coexistir com Direção ou Coordenação.',
            ]);
        }

        $escolaId = (int) ($dados['id_escola'] ?? 0);
        $escola = Escola::query()->ativas()->find($escolaId);
        if (! $escola || blank($escola->setor_id)) {
            throw ValidationException::withMessages([
                'id_escola' => 'Selecione uma escola ativa que possua setor vinculado.',
            ]);
        }

        $validator = Validator::make($dados, [
            'data_inicio' => ['required', 'date'],
        ], [
            'data_inicio.required' => 'Informe o início da vigência do cargo.',
            'data_inicio.date' => 'O início da vigência é inválido.',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $dataInicio = CarbonImmutable::parse($dados['data_inicio'])->toDateString();

        $portaria = filled($dados['portaria'] ?? null)
            ? preg_replace('/\s+/', ' ', trim((string) $dados['portaria']))
            : null;
        if (($diretor || $coordenador) && blank($portaria)) {
            throw ValidationException::withMessages([
                'portaria' => 'A portaria é obrigatória para Direção e Coordenação.',
            ]);
        }
        if ($portaria && (mb_strlen($portaria) > 255 || ! preg_match('/^[\pL\pN][\pL\pN .\/_-]*$/u', $portaria))) {
            throw ValidationException::withMessages([
                'portaria' => 'A portaria deve conter apenas letras, números, espaços, ponto, barra, hífen ou sublinhado.',
            ]);
        }

        $matriculas = $this->normalizarMatriculas(
            $dados['matriculas'] ?? $dados['matriculas_pessoa'] ?? $dados['matriculas_professor'] ?? [],
        );

        $coordenacao = is_array($dados['coordenador'] ?? null) ? $dados['coordenador'] : [];
        $turmaIds = collect(
            $coordenacao['turma_ids']
                ?? $dados['turma_ids']
                ?? $dados['turmas']
                ?? [],
        )->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($coordenador && ($coordenacao['selecionar_todas'] ?? $dados['selecionar_todas_turmas'] ?? false)) {
            $turmaIds = Turma::query()
                ->where('id_escola', $escola->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();
        }

        if ($coordenador && $turmaIds->isEmpty()) {
            throw ValidationException::withMessages([
                'turma_ids' => 'Coordenador ativo precisa estar vinculado a pelo menos uma turma existente.',
            ]);
        }

        if ($coordenador) {
            $turmasValidas = Turma::query()
                ->whereIn('id', $turmaIds->all())
                ->where('id_escola', $escola->id)
                ->count();
            if ($turmasValidas !== $turmaIds->count()) {
                throw ValidationException::withMessages([
                    'turma_ids' => 'Todas as turmas da Coordenação devem existir e pertencer à escola selecionada.',
                ]);
            }
        }

        $principais = collect(
            $coordenacao['turmas_principais_ids']
                ?? $dados['turmas_principais_ids']
                ?? [],
        )->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        if ($principais->diff($turmaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'turmas_principais_ids' => 'Uma turma principal precisa estar entre as turmas vinculadas ao Coordenador.',
            ]);
        }
        $vacancias = collect(
            $coordenacao['turmas_vacancia_ids']
                ?? $dados['turmas_vacancia_ids']
                ?? [],
        )->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        if ($vacancias->diff($turmaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'turmas_vacancia_ids' => 'Uma vacância de Coordenação precisa estar entre as turmas vinculadas.',
            ]);
        }
        if ($vacancias->intersect($principais)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'turmas_vacancia_ids' => 'A mesma turma não pode ser marcada como principal e vacante.',
            ]);
        }

        $direcao = is_array($dados['diretor'] ?? null) ? $dados['diretor'] : [];
        $principalInformado = array_key_exists('principal', $direcao)
            || array_key_exists('diretor_principal', $dados);

        return [
            'escola' => $escola,
            'matriculas' => $matriculas,
            'diretor' => $diretor,
            'coordenador' => $coordenador,
            'secretario' => $secretario,
            'portaria' => $portaria,
            'data_inicio' => $dataInicio,
            'turma_ids' => $turmaIds,
            'turmas_principais_ids' => $principais,
            'turmas_vacancia_ids' => $vacancias,
            'diretor_principal' => (bool) ($direcao['principal'] ?? $dados['diretor_principal'] ?? false),
            'diretor_principal_informado' => $principalInformado,
        ];
    }

    private function cargoAtivo(mixed $valor, Collection $cargos, array $aliases): bool
    {
        if (is_array($valor)) {
            return (bool) ($valor['ativo'] ?? true);
        }

        if ($valor !== null) {
            return (bool) $valor;
        }

        return $cargos->contains(fn (string $cargo): bool => in_array($cargo, $aliases, true));
    }

    /** @return Collection<int, array{id: int|null, matricula: string, turno: string}> */
    private function normalizarMatriculas(array $matriculas): Collection
    {
        $normalizadas = collect($matriculas)
            ->map(function ($item): ?array {
                if (! is_array($item) || blank($item['matricula'] ?? null) || blank($item['turno'] ?? null)) {
                    return null;
                }

                return [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'matricula' => trim((string) $item['matricula']),
                    'turno' => (string) $item['turno'],
                ];
            })
            ->filter()
            ->values();

        if ($normalizadas->isEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Informe ao menos uma matrícula da pessoa.',
            ]);
        }

        PessoaMatricula::assertConjuntoTurnosValido($normalizadas->pluck('turno')->all());

        $duplicadas = $normalizadas
            ->pluck('matricula')
            ->map(fn (string $matricula): string => mb_strtolower($matricula))
            ->duplicates();
        if ($duplicadas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Não é permitido repetir o número da matrícula.',
            ]);
        }

        return $normalizadas;
    }

    /** @param Collection<int, array{id: int|null, matricula: string, turno: string}> $dados */
    private function sincronizarMatriculas(Servidor $pessoa, Collection $dados): Collection
    {
        $mantidas = collect();

        foreach ($dados as $item) {
            $matricula = null;
            if ($item['id']) {
                $matricula = PessoaMatricula::query()
                    ->where('servidor_id', $pessoa->id)
                    ->whereKey($item['id'])
                    ->first();
            }
            $matricula ??= PessoaMatricula::query()
                ->where('servidor_id', $pessoa->id)
                ->where('matricula', $item['matricula'])
                ->first();
            $matricula ??= new PessoaMatricula(['servidor_id' => $pessoa->id]);

            $matricula->fill([
                'matricula' => $item['matricula'],
                'turno' => $item['turno'],
            ])->save();
            $mantidas->push($matricula->fresh());
        }

        $referenciadaAusente = PessoaMatricula::query()
            ->where('servidor_id', $pessoa->id)
            ->whereNotIn('id', $mantidas->pluck('id')->all())
            ->whereHas('professores')
            ->first();
        if ($referenciadaAusente) {
            throw ValidationException::withMessages([
                'matriculas' => "A matrícula {$referenciadaAusente->matricula} possui histórico de lotação e deve permanecer vinculada à Pessoa.",
            ]);
        }

        PessoaMatricula::query()
            ->where('servidor_id', $pessoa->id)
            ->whereNotIn('id', $mantidas->pluck('id')->all())
            ->whereDoesntHave('professores')
            ->delete();

        return $mantidas;
    }

    private function resolverPrincipalDirecao(
        ServidorFuncaoAdministrativa $vinculo,
        int $escolaId,
        bool $solicitado,
        bool $informado,
        bool $novo,
    ): bool {
        $outrosPrincipais = ServidorFuncaoAdministrativa::query()
            ->where('id_escola', $escolaId)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->where('principal', true)
            ->when($vinculo->exists, fn ($query) => $query->whereKeyNot($vinculo->id))
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->direcao())
            ->lockForUpdate();

        if ($informado && $solicitado) {
            $outrosPrincipais->update(['principal' => false, 'updated_at' => now()]);

            return true;
        }

        if ($informado) {
            return false;
        }

        if (! $novo && $vinculo->principal) {
            return true;
        }

        return ! $outrosPrincipais->exists();
    }

    private function sincronizarTurmasCoordenacao(
        ServidorFuncaoAdministrativa $vinculo,
        Collection $turmaIds,
        Collection $turmasPrincipaisIds,
        Collection $turmasVacanciaIds,
        string $dataInicio,
    ): void {
        $ativos = ServidorFuncaoTurma::query()
            ->where('servidor_funcao_administrativa_id', $vinculo->id)
            ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
            ->lockForUpdate()
            ->get()
            ->keyBy('turma_id');

        foreach ($ativos->except($turmaIds->all()) as $registro) {
            $registro->update([
                'principal' => false,
                'status' => ServidorFuncaoTurma::STATUS_INATIVO,
                'data_fim' => now()->toDateString(),
            ]);
        }

        foreach ($turmaIds as $turmaId) {
            // Lock da entidade pai evita corrida na criação do primeiro principal:
            // lockForUpdate em uma consulta vazia de pivô não serializa concorrentes.
            Turma::query()->whereKey($turmaId)->lockForUpdate()->firstOrFail();

            /** @var ServidorFuncaoTurma|null $registro */
            $registro = $ativos->get($turmaId);
            $novo = ! $registro;
            $registro ??= new ServidorFuncaoTurma([
                'servidor_funcao_administrativa_id' => $vinculo->id,
                'turma_id' => $turmaId,
            ]);

            $principalSolicitado = $turmasPrincipaisIds->contains($turmaId);
            $vacanciaSolicitada = $turmasVacanciaIds->contains($turmaId);
            $outrosPrincipais = ServidorFuncaoTurma::query()
                ->where('turma_id', $turmaId)
                ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
                ->where('principal', true)
                ->when($registro->exists, fn ($query) => $query->whereKeyNot($registro->id))
                ->whereHas('servidorFuncaoAdministrativa', function ($vinculos): void {
                    $vinculos
                        ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                        ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->coordenacao());
                })
                ->lockForUpdate();

            if ($principalSolicitado) {
                $outrosPrincipais->update(['principal' => false, 'updated_at' => now()]);
            }

            $principal = match (true) {
                $principalSolicitado => true,
                $vacanciaSolicitada => false,
                ! $novo => (bool) $registro->principal,
                default => ! $outrosPrincipais->exists(),
            };

            $registro->fill([
                'principal' => $principal,
                'status' => ServidorFuncaoTurma::STATUS_ATIVO,
                'data_inicio' => $dataInicio,
                'data_fim' => null,
            ])->save();
        }
    }

    /** @param Collection<int, ServidorFuncaoAdministrativa> $vinculos */
    private function validarInicioPosteriorAosVinculos(string $novoInicio, Collection $vinculos): void
    {
        $novoInicio = CarbonImmutable::parse($novoInicio)->startOfDay();
        $inicioInvalido = $vinculos->contains(function (ServidorFuncaoAdministrativa $vinculo) use ($novoInicio): bool {
            if (! $vinculo->data_inicio) {
                return false;
            }

            return ! $novoInicio->isAfter(CarbonImmutable::parse($vinculo->data_inicio)->startOfDay());
        });

        if ($inicioInvalido) {
            throw ValidationException::withMessages([
                'data_inicio' => 'O início da nova vigência deve ser posterior ao início dos vínculos que serão encerrados.',
            ]);
        }
    }

    private function dataFimAnteriorAoInicio(string $novoInicio): string
    {
        return CarbonImmutable::parse($novoInicio)->subDay()->toDateString();
    }

    /** @return Collection<int, ServidorFuncaoAdministrativa> */
    private function vinculosGestoresAtivos(Servidor $pessoa): Collection
    {
        return ServidorFuncaoAdministrativa::query()
            ->with('funcaoAdministrativa')
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
            ->lockForUpdate()
            ->get();
    }

    private function carregar(Servidor $pessoa): Servidor
    {
        return $pessoa->fresh([
            'matriculas',
            'professores',
            'user',
            'vinculosAtivos.funcaoAdministrativa.rolesPadrao',
            'vinculosAtivos.vinculosTurmaAtivos.turma',
        ]);
    }

    /** @return array<string, mixed> */
    private function dadosPessoa(array $dados, bool $statusPadraoAtivo = false): array
    {
        $normalizados = collect($dados)
            ->only([
                'cpf',
                'user_id',
                'nome',
                'email',
                'telefone',
                'status',
                'observacoes',
            ])
            ->all();

        if ($statusPadraoAtivo && ! array_key_exists('status', $normalizados)) {
            $normalizados['status'] = Pessoa::STATUS_ATIVO;
        }

        return $normalizados;
    }

    /** @return array<string, mixed> */
    private function dadosPessoaAtual(Servidor $pessoa): array
    {
        return collect($pessoa->getAttributes())
            ->only(['cpf', 'user_id', 'nome', 'email', 'telefone', 'status', 'observacoes'])
            ->all();
    }
}
