<?php

namespace App\Livewire\Pessoas;

use App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Escola;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\Turma;
use App\Services\PessoaEdicaoEscopadaService;
use App\Services\PessoaProfessorFormService;
use App\Services\PessoaScopeService;
use App\Services\ServidorService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class PessoaEditForm extends Component
{
    #[Locked]
    public int $pessoaId;

    #[Locked]
    public bool $gerenciaEstrutura = false;

    #[Locked]
    public bool $podeEditarDados = false;

    #[Locked]
    public bool $podeEditarTurmas = false;

    public string $nome = '';

    public ?string $cpf = null;

    public ?string $email = null;

    public ?string $telefone = null;

    public string $status = Pessoa::STATUS_ATIVO;

    public ?string $observacoes = null;

    public string $cargo = ServidorResource::CARGO_PROFESSOR;

    /** @var array<string, array<string, mixed>> */
    public array $matriculas = [];

    public ?string $matriculaAtiva = null;

    /** @var array<string, string|null> */
    public array $lotacoesAtivas = [];

    public ?int $idEscolaGestora = null;

    /** @var list<string> */
    public array $cargosGestores = [];

    public ?string $portaria = null;

    /** @var list<int|string> */
    public array $turmaIds = [];

    public function mount(int $pessoaId): void
    {
        $this->pessoaId = $pessoaId;
        $pessoa = $this->pessoaAutorizada();
        $this->atualizarCapacidades($pessoa);

        $user = Auth::user();
        $dados = $this->gerenciaEstrutura
            ? app(PessoaProfessorFormService::class)->dadosParaFormulario($pessoa)
            : app(PessoaProfessorFormService::class)->dadosEscopadosParaFormulario($pessoa, $user);

        $this->nome = (string) ($dados['nome'] ?? $pessoa->nome);
        $this->cpf = $dados['cpf'] ?? Pessoa::formatarCpf($pessoa->cpf);
        $this->email = $dados['email'] ?? $pessoa->email;
        $this->telefone = $dados['telefone'] ?? $pessoa->telefone;
        $this->status = (string) ($dados['status'] ?? $pessoa->status ?? Pessoa::STATUS_ATIVO);
        $this->observacoes = $dados['observacoes'] ?? $pessoa->observacoes;
        $this->cargo = ServidorResource::ehEquipeGestora($pessoa)
            ? ServidorResource::CARGO_EQUIPE_GESTORA
            : ServidorResource::CARGO_PROFESSOR;

        $matriculas = is_array($dados['matriculas_professor'] ?? null)
            ? $dados['matriculas_professor']
            : [];

        // A projeção restrita não pode revelar matrículas exclusivas de outra escola.
        if (! $this->gerenciaEstrutura) {
            $matriculas = array_values(array_filter(
                $matriculas,
                fn (mixed $item): bool => is_array($item) && ! empty($item['escolas']),
            ));
        }

        $this->matriculas = $this->normalizarEstadoMatriculas($matriculas);
        $this->matriculaAtiva = array_key_first($this->matriculas);
        foreach ($this->matriculas as $matriculaKey => $matricula) {
            $this->lotacoesAtivas[$matriculaKey] = array_key_first($matricula['escolas'] ?? []);
        }
        $this->idEscolaGestora = filled($dados['id_escola'] ?? null) ? (int) $dados['id_escola'] : null;
        $this->cargosGestores = array_values($dados['cargos_gestores'] ?? []);
        $this->portaria = $dados['portaria'] ?? null;
        $this->turmaIds = array_values($dados['turma_ids'] ?? []);
    }

    public function render(): View
    {
        $escolasOptions = $this->escolasOptionsSeguras();
        $turmasOptions = [];
        $componentesOptions = [];

        foreach ($this->matriculas as $matriculaKey => $matricula) {
            foreach (($matricula['escolas'] ?? []) as $lotacaoKey => $lotacao) {
                $escolaId = filled($lotacao['id_escola'] ?? null) ? (int) $lotacao['id_escola'] : null;
                $selecionadas = collect($lotacao['vinculos_turma_componente'] ?? [])
                    ->pluck('turma_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                $opcoesTurma = $this->turmasOptionsSeguras(
                    $escolaId,
                    (string) ($matricula['turno'] ?? ''),
                    $selecionadas,
                    $escolasOptions,
                );
                $turmasOptions[$matriculaKey][$lotacaoKey] = $opcoesTurma;

                foreach (($lotacao['vinculos_turma_componente'] ?? []) as $vinculoKey => $vinculo) {
                    $turmaId = filled($vinculo['turma_id'] ?? null) ? (int) $vinculo['turma_id'] : null;
                    $componentesOptions[$matriculaKey][$lotacaoKey][$vinculoKey] = $this->componentesOptionsSeguras(
                        $turmaId,
                        array_keys($opcoesTurma),
                    );
                }
            }
        }

        return view('livewire.pessoas.pessoa-edit-form', [
            'escolasOptions' => $escolasOptions,
            'turmasOptions' => $turmasOptions,
            'componentesOptions' => $componentesOptions,
            'turmasGestaoOptions' => $this->turmasGestaoOptions($escolasOptions),
            'statusOptions' => Pessoa::statusOptions(),
            'turnosOptions' => PessoaMatricula::turnosOptions(),
        ]);
    }

    public function adicionarMatricula(): void
    {
        $this->autorizarEstrutura();
        $this->resetErrorBag('matriculas');

        if (count($this->matriculas) >= PessoaMatricula::MAX_POR_PESSOA) {
            $this->addError('matriculas', 'Remova uma matrícula antes de adicionar outra.');

            return;
        }

        $key = $this->novaChave('m');
        $this->matriculas[$key] = [
            'id' => null,
            'matricula' => '',
            'turno' => '',
            'escolas' => [],
        ];
        $this->matriculaAtiva = $key;
        $this->lotacoesAtivas[$key] = null;
    }

    public function removerMatricula(string $matriculaKey): void
    {
        $this->autorizarEstrutura();
        $this->resetErrorBag('matriculas');

        if (! array_key_exists($matriculaKey, $this->matriculas)) {
            return;
        }

        if (count($this->matriculas) <= 1) {
            $this->addError('matriculas', 'Um professor deve permanecer com ao menos uma matrícula.');

            return;
        }

        unset($this->matriculas[$matriculaKey]);
        unset($this->lotacoesAtivas[$matriculaKey]);
        $this->matriculaAtiva = array_key_first($this->matriculas);
    }

    public function selecionarMatricula(string $matriculaKey): void
    {
        if (array_key_exists($matriculaKey, $this->matriculas)) {
            $this->matriculaAtiva = $matriculaKey;
        }
    }

    public function adicionarLotacao(string $matriculaKey): void
    {
        $this->autorizarEstrutura();

        if (! isset($this->matriculas[$matriculaKey])) {
            return;
        }

        $key = $this->novaChave('l');
        $this->matriculas[$matriculaKey]['escolas'][$key] = [
            'id' => null,
            'id_escola' => null,
            'vinculos_turma_componente' => [],
        ];
        $this->lotacoesAtivas[$matriculaKey] = $key;
    }

    public function removerLotacao(string $matriculaKey, string $lotacaoKey): void
    {
        $this->autorizarEstrutura();
        unset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]);
        $this->lotacoesAtivas[$matriculaKey] = array_key_first($this->matriculas[$matriculaKey]['escolas'] ?? []);
    }

    public function selecionarLotacao(string $matriculaKey, string $lotacaoKey): void
    {
        if (isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey])) {
            $this->lotacoesAtivas[$matriculaKey] = $lotacaoKey;
        }
    }

    public function adicionarVinculo(string $matriculaKey, string $lotacaoKey): void
    {
        $this->autorizarEdicaoTurmas();

        if (! isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey])) {
            return;
        }

        $key = $this->novaChave('v');
        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$key] = [
            'turma_id' => null,
            'componente_curricular_id' => null,
        ];
    }

    public function removerVinculo(string $matriculaKey, string $lotacaoKey, string $vinculoKey): void
    {
        $this->autorizarEdicaoTurmas();
        unset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey]);
    }

    public function escolaAlterada(string $matriculaKey, string $lotacaoKey, mixed $escolaId): void
    {
        $this->autorizarEstrutura();

        if (! isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey])) {
            return;
        }

        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['id_escola'] = filled($escolaId) ? (int) $escolaId : null;
        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'] = [];
    }

    public function turnoAlterado(string $matriculaKey, mixed $turno): void
    {
        $this->autorizarEstrutura();

        if (! isset($this->matriculas[$matriculaKey])) {
            return;
        }

        $this->matriculas[$matriculaKey]['turno'] = (string) $turno;
        foreach (array_keys($this->matriculas[$matriculaKey]['escolas'] ?? []) as $lotacaoKey) {
            $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'] = [];
        }
    }

    public function turmaAlterada(string $matriculaKey, string $lotacaoKey, string $vinculoKey, mixed $turmaId): void
    {
        $this->autorizarEdicaoTurmas();

        if (! isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey])) {
            return;
        }

        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey]['turma_id'] = filled($turmaId)
            ? (int) $turmaId
            : null;
        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey]['componente_curricular_id'] = null;
    }

    public function escolaGestoraAlterada(mixed $escolaId): void
    {
        $this->autorizarEstrutura();
        $this->idEscolaGestora = filled($escolaId) ? (int) $escolaId : null;
        $this->turmaIds = [];
        $this->portaria = null;
    }

    public function salvar(): void
    {
        $this->resetErrorBag();
        $pessoa = $this->pessoaAutorizada();
        $user = Auth::user();
        $gerenciaEstrutura = Gate::forUser($user)->allows('manageStructure', $pessoa);
        $podeEditarDados = Gate::forUser($user)->allows('editBasicData', $pessoa);
        $podeEditarTurmas = Gate::forUser($user)->allows('editTeachingAssignments', $pessoa);

        if (! $gerenciaEstrutura && ! $podeEditarDados && ! $podeEditarTurmas) {
            abort(403);
        }

        try {
            $this->validarDadosBasicos($gerenciaEstrutura || $podeEditarDados);

            if ($gerenciaEstrutura) {
                $this->validarEstrutura();
                $payload = $this->payloadEstrutural();
                [$dados, $vinculos] = ServidorResource::prepararDadosPersistencia($payload, $pessoa);
                app(ServidorService::class)->atualizarServidorComFuncoes($pessoa, $dados, $vinculos);
            } else {
                $payload = [];

                if ($podeEditarDados) {
                    $payload = [
                        'cpf' => $this->cpf,
                        'email' => $this->email,
                        'telefone' => $this->telefone,
                    ];
                }

                if ($podeEditarTurmas) {
                    $payload['matriculas_professor'] = $this->payloadMatriculas();
                }

                app(PessoaEdicaoEscopadaService::class)->atualizar($pessoa, $user, $payload);
            }

            Notification::make()
                ->title('Pessoa atualizada')
                ->success()
                ->send();

            $this->dispatch('pessoa-editor-salvo');
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Não foi possível salvar')
                ->body(collect($exception->errors())->flatten()->take(5)->implode(' '))
                ->danger()
                ->persistent()
                ->send();

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('formulario', 'Não foi possível salvar a pessoa. Revise os dados e tente novamente.');

            Notification::make()
                ->title('Erro ao salvar pessoa')
                ->body('A alteração não foi aplicada. Tente novamente.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function cancelar(): void
    {
        $this->dispatch('pessoa-editor-cancelar');
    }

    private function pessoaAutorizada(): Servidor
    {
        $pessoa = Servidor::query()->findOrFail($this->pessoaId);
        Gate::authorize('update', $pessoa);

        return $pessoa;
    }

    private function atualizarCapacidades(Servidor $pessoa): void
    {
        $user = Auth::user();
        $this->gerenciaEstrutura = Gate::forUser($user)->allows('manageStructure', $pessoa);
        $this->podeEditarDados = $this->gerenciaEstrutura || Gate::forUser($user)->allows('editBasicData', $pessoa);
        $this->podeEditarTurmas = $this->gerenciaEstrutura || Gate::forUser($user)->allows('editTeachingAssignments', $pessoa);
    }

    private function autorizarEstrutura(): void
    {
        Gate::authorize('manageStructure', $this->pessoaAutorizada());
    }

    private function autorizarEdicaoTurmas(): void
    {
        $pessoa = $this->pessoaAutorizada();

        if (! Gate::allows('manageStructure', $pessoa) && ! Gate::allows('editTeachingAssignments', $pessoa)) {
            abort(403);
        }
    }

    private function validarDadosBasicos(bool $editavel): void
    {
        if (! $editavel) {
            return;
        }

        $this->validate([
            'cpf' => ['nullable', 'string', 'max:14'],
            'email' => ['required', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:255'],
        ]);

        if (! Professor::emailInstitucionalValido($this->email)) {
            throw ValidationException::withMessages([
                'email' => 'Use somente e-mail institucional @edu.umuarama.pr.gov.br.',
            ]);
        }
    }

    private function validarEstrutura(): void
    {
        $rules = [
            'nome' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Pessoa::statusOptions()))],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'cargo' => ['required', Rule::in([
                ServidorResource::CARGO_PROFESSOR,
                ServidorResource::CARGO_EQUIPE_GESTORA,
            ])],
            'matriculas' => ['required', 'array', 'min:1', 'max:'.PessoaMatricula::MAX_POR_PESSOA],
            'matriculas.*.matricula' => ['required', 'string', 'max:255'],
            'matriculas.*.turno' => ['required', Rule::in(array_keys(PessoaMatricula::turnosOptions()))],
        ];

        if ($this->cargo === ServidorResource::CARGO_PROFESSOR) {
            $rules += [
                'matriculas.*.escolas' => ['array'],
                'matriculas.*.escolas.*.id_escola' => ['required', 'integer'],
                'matriculas.*.escolas.*.vinculos_turma_componente' => ['array'],
                'matriculas.*.escolas.*.vinculos_turma_componente.*.turma_id' => ['required', 'integer'],
                'matriculas.*.escolas.*.vinculos_turma_componente.*.componente_curricular_id' => ['required', 'integer'],
            ];
        } else {
            $rules += [
                'idEscolaGestora' => ['required', 'integer'],
                'cargosGestores' => ['required', 'array', 'min:1', 'max:2'],
                'cargosGestores.*' => [Rule::in([
                    ServidorEquipeGestoraForm::CARGO_DIRETOR,
                    ServidorEquipeGestoraForm::CARGO_COORDENADOR,
                    ServidorEquipeGestoraForm::CARGO_SECRETARIO,
                ])],
                'portaria' => ['nullable', 'string', 'max:255'],
                'turmaIds' => ['array'],
                'turmaIds.*' => ['integer'],
            ];
        }

        $this->validate($rules, attributes: [
            'idEscolaGestora' => 'escola',
            'cargosGestores' => 'cargos gestores',
            'turmaIds' => 'turmas da coordenação',
        ]);

        PessoaMatricula::assertConjuntoTurnosValido(
            collect($this->matriculas)->pluck('turno')->map(fn (mixed $turno): string => (string) $turno)->all(),
        );
    }

    /** @return array<string, mixed> */
    private function payloadEstrutural(): array
    {
        return [
            'nome' => trim($this->nome),
            'cpf' => $this->cpf,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'cargo' => $this->cargo,
            'matriculas_professor' => $this->payloadMatriculas(),
            'id_escola' => $this->idEscolaGestora,
            'cargos_gestores' => array_values($this->cargosGestores),
            'portaria' => $this->portaria,
            'turma_ids' => array_values($this->turmaIds),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function payloadMatriculas(): array
    {
        return collect($this->matriculas)
            ->map(function (array $matricula): array {
                return [
                    'id' => filled($matricula['id'] ?? null) ? (int) $matricula['id'] : null,
                    'matricula' => trim((string) ($matricula['matricula'] ?? '')),
                    'turno' => (string) ($matricula['turno'] ?? ''),
                    'escolas' => collect($matricula['escolas'] ?? [])
                        ->map(fn (array $lotacao): array => [
                            'id' => filled($lotacao['id'] ?? null) ? (int) $lotacao['id'] : null,
                            'id_escola' => filled($lotacao['id_escola'] ?? null) ? (int) $lotacao['id_escola'] : null,
                            'vinculos_turma_componente' => collect($lotacao['vinculos_turma_componente'] ?? [])
                                ->map(fn (array $vinculo): array => [
                                    'turma_id' => filled($vinculo['turma_id'] ?? null) ? (int) $vinculo['turma_id'] : null,
                                    'componente_curricular_id' => filled($vinculo['componente_curricular_id'] ?? null)
                                        ? (int) $vinculo['componente_curricular_id']
                                        : null,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $matriculas
     * @return array<string, array<string, mixed>>
     */
    private function normalizarEstadoMatriculas(array $matriculas): array
    {
        $estado = [];

        foreach ($matriculas as $matricula) {
            if (! is_array($matricula)) {
                continue;
            }

            $key = filled($matricula['id'] ?? null) ? 'm'.(int) $matricula['id'] : $this->novaChave('m');
            $lotacoes = [];

            foreach (($matricula['escolas'] ?? []) as $lotacao) {
                if (! is_array($lotacao)) {
                    continue;
                }

                $lotacaoKey = filled($lotacao['id'] ?? null) ? 'l'.(int) $lotacao['id'] : $this->novaChave('l');
                $vinculos = [];

                foreach (($lotacao['vinculos_turma_componente'] ?? []) as $vinculo) {
                    if (! is_array($vinculo)) {
                        continue;
                    }

                    $vinculos[$this->novaChave('v')] = [
                        'turma_id' => filled($vinculo['turma_id'] ?? null) ? (int) $vinculo['turma_id'] : null,
                        'componente_curricular_id' => filled($vinculo['componente_curricular_id'] ?? null)
                            ? (int) $vinculo['componente_curricular_id']
                            : null,
                    ];
                }

                $lotacoes[$lotacaoKey] = [
                    'id' => filled($lotacao['id'] ?? null) ? (int) $lotacao['id'] : null,
                    'id_escola' => filled($lotacao['id_escola'] ?? null) ? (int) $lotacao['id_escola'] : null,
                    'vinculos_turma_componente' => $vinculos,
                ];
            }

            $turno = (string) ($matricula['turno'] ?? '');
            if (! array_key_exists($turno, PessoaMatricula::turnosOptions())) {
                $turno = $this->inferirTurnoLegado($lotacoes);
            }

            $estado[$key] = [
                'id' => filled($matricula['id'] ?? null) ? (int) $matricula['id'] : null,
                'matricula' => (string) ($matricula['matricula'] ?? ''),
                'turno' => $turno,
                'escolas' => $lotacoes,
            ];
        }

        return $estado;
    }

    /** @param array<string, array<string, mixed>> $lotacoes */
    private function inferirTurnoLegado(array $lotacoes): string
    {
        $professorIds = collect($lotacoes)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
        if ($professorIds === []) {
            return '';
        }

        $turnos = Professor::query()
            ->whereIn('id', $professorIds)
            ->get()
            ->map(fn (Professor $professor): ?string => $professor->turnoEfetivo())
            ->filter(fn (?string $turno): bool => filled($turno) && array_key_exists($turno, PessoaMatricula::turnosOptions()))
            ->unique()
            ->values();

        return $turnos->count() === 1 ? (string) $turnos->first() : '';
    }

    /** @return array<int, string> */
    private function escolasOptionsSeguras(): array
    {
        return ServidorResource::escolasOptionsEscopadas();
    }

    /**
     * @param  list<int>  $selecionadas
     * @param  array<int, string>  $escolasOptions
     * @return array<int, string>
     */
    private function turmasOptionsSeguras(?int $escolaId, string $turno, array $selecionadas, array $escolasOptions): array
    {
        if (! $escolaId || ! array_key_exists($escolaId, $escolasOptions)) {
            return [];
        }

        $query = Turma::query()
            ->where('id_escola', $escolaId)
            ->with('serie:id,nome')
            ->orderBy('turno')
            ->orderBy('nome');

        $turnos = PessoaMatricula::turnosTurmaCompativeis($turno);
        if ($turnos !== []) {
            $query->where(function ($turmas) use ($turnos, $selecionadas): void {
                $turmas->whereIn('turno', $turnos);
                if ($selecionadas !== []) {
                    $turmas->orWhereIn('id', $selecionadas);
                }
            });
        }

        return $query->get()->mapWithKeys(function (Turma $turma): array {
            $nome = collect([$turma->serie?->nome, $turma->nome])->filter()->implode(' - ');
            $turno = Professor::turnosOptions()[$turma->turno] ?? $turma->turno;

            return [$turma->id => trim("{$nome} ({$turno})")];
        })->all();
    }

    /**
     * @param  list<int|string>  $turmasPermitidas
     * @return array<int, string>
     */
    private function componentesOptionsSeguras(?int $turmaId, array $turmasPermitidas): array
    {
        if (! $turmaId || ! in_array($turmaId, array_map('intval', $turmasPermitidas), true)) {
            return [];
        }

        $turma = Turma::query()->with('serie.componentesCurriculares')->find($turmaId);

        return $turma?->serie?->componentesCurriculares
            ?->sortBy('nome')
            ->mapWithKeys(fn ($componente): array => [$componente->id => $componente->nome])
            ->all() ?? [];
    }

    /**
     * @param  array<int, string>  $escolasOptions
     * @return array<int, string>
     */
    private function turmasGestaoOptions(array $escolasOptions): array
    {
        if (! $this->idEscolaGestora || ! array_key_exists($this->idEscolaGestora, $escolasOptions)) {
            return [];
        }

        return Turma::query()
            ->where('id_escola', $this->idEscolaGestora)
            ->with('serie:id,nome')
            ->orderBy('turno')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function (Turma $turma): array {
                $nome = collect([$turma->serie?->nome, $turma->nome])->filter()->implode(' - ');
                $turno = Professor::turnosOptions()[$turma->turno] ?? $turma->turno;

                return [$turma->id => trim("{$nome} ({$turno})")];
            })
            ->all();
    }

    private function novaChave(string $prefixo): string
    {
        return $prefixo.Str::lower(Str::random(12));
    }
}
