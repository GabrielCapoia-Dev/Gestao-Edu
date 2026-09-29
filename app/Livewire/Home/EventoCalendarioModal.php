<?php

namespace App\Livewire\Home;

use App\Models\Aluno;
use App\Models\ComponenteCurricular;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\EventoCalendario;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Serie;
use App\Models\User;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\EventoCalendarioEscolaService;
use App\Services\Dashboard\EventoCalendarioPublicoService;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\ProfilePreviewService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

final class EventoCalendarioModal extends Component
{
    /** @var array<string, array{inicio: string, fim: string}> */
    private const PERIODOS = [
        'manha' => ['inicio' => '08:00', 'fim' => '12:00'],
        'tarde' => ['inicio' => '13:30', 'fim' => '17:30'],
        'noite' => ['inicio' => '19:00', 'fim' => '22:00'],
        'dia_todo' => ['inicio' => '08:00', 'fim' => '17:30'],
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int|string, string> */
    public array $escolasOpcoes = [];

    /** @var array<int|string, string> */
    public array $seriesOpcoes = [];

    /** @var array<int|string, string> */
    public array $funcoesOpcoes = [];

    /** @var array<int|string, string> */
    public array $componentesOpcoes = [];

    /** @var list<array<string, mixed>> */
    public array $participantes = [];

    /** @var list<array<string, mixed>> */
    public array $alunos = [];

    public bool $aberto = false;

    public bool $mostrarGatilho = true;

    public bool $podeCriar = false;

    public bool $somenteTransporte = false;

    public bool $participantesConsultados = false;

    public bool $alunosConsultados = false;

    public int $etapa = 1;

    public function mount(bool $mostrarGatilho = true): void
    {
        $this->mostrarGatilho = $mostrarGatilho;

        try {
            $preview = app(ProfilePreviewService::class);
            $usuario = $preview->effectiveUser();

            $this->podeCriar = $usuario instanceof User
                && ! $preview->isActive()
                && Gate::forUser($usuario)->allows('create', EventoCalendario::class);

            $this->somenteTransporte = $usuario instanceof User
                && Gate::forUser($usuario)->allows('requiresTransport', EventoCalendario::class);
        } catch (\Throwable $exception) {
            report($exception);
            $this->podeCriar = false;
            $this->somenteTransporte = false;
        }
    }

    #[On('abrir-evento-calendario')]
    public function abrirPorEvento(): void
    {
        if ($this->podeCriar) {
            $this->abrir();
        }
    }

    public function abrir(): void
    {
        $usuario = $this->autorizarCriacao();

        $this->carregarOpcoes($usuario);
        $this->data = $this->dadosIniciais();
        $this->participantes = [];
        $this->alunos = [];
        $this->participantesConsultados = false;
        $this->alunosConsultados = false;
        $this->etapa = 1;
        $this->resetValidation();
        $this->aberto = true;
    }

    public function fechar(): void
    {
        $this->aberto = false;
        $this->data = [];
        $this->participantes = [];
        $this->alunos = [];
        $this->participantesConsultados = false;
        $this->alunosConsultados = false;
        $this->etapa = 1;
        $this->resetValidation();
    }

    public function aplicarPeriodo(): void
    {
        $periodo = self::PERIODOS[$this->data['periodo'] ?? ''] ?? null;

        if (! $periodo) {
            return;
        }

        $this->data['hora_inicio'] = $periodo['inicio'];
        $this->data['hora_fim'] = $periodo['fim'];
        $this->data['transporte_turnos'] = match ($this->data['periodo']) {
            'manha' => ['manha'],
            'tarde' => ['tarde'],
            'noite' => ['noite'],
            'dia_todo' => ['manha', 'tarde', 'integral'],
            default => [],
        };
        $this->invalidarAlunos();
    }

    public function avancar(): void
    {
        if ($this->etapa === 1) {
            $this->validate($this->regrasDaEtapaUm());
            $this->etapa = 2;

            return;
        }

        if ($this->etapa === 2) {
            $this->etapa = 3;
        }
    }

    public function voltar(): void
    {
        $this->etapa = max(1, $this->etapa - 1);
    }

    public function aplicarFiltrosParticipantes(): void
    {
        $usuario = $this->autorizarCriacao();
        $this->resetErrorBag('data.publico_prefixos');

        try {
            $this->validate([
                'data.publico_prefixos' => ['array'],
                'data.publico_prefixos.*' => [Rule::in(['CMEI', 'ESCOLA'])],
            ]);
        } catch (ValidationException $exception) {
            $this->adicionarErros($exception);

            return;
        }

        $regra = $this->regraPublicoAtual();

        if ($this->strings($this->data['publico_prefixos'] ?? []) !== [] && $regra['escola_ids'] === []) {
            $this->addError('data.publico_prefixos', 'Não há escolas desse tipo disponíveis nos filtros selecionados.');
            $this->participantesConsultados = false;
            $this->participantes = [];

            return;
        }

        if (! $this->possuiFiltroPublico()) {
            $this->addError('data.publico_regras', 'Selecione ao menos um filtro antes de buscar participantes.');
            $this->participantesConsultados = false;
            $this->participantes = [];

            return;
        }

        try {
            $usuarios = app(EventoCalendarioPublicoService::class)->preview(
                $usuario,
                [$regra],
                $this->ids($this->data['publico_excecoes_ids'] ?? []),
            );
        } catch (ValidationException $exception) {
            $this->adicionarErros($exception);

            return;
        }

        $this->data['publico_regras'] = [$regra];
        $this->data['publico_preview_aplicado'] = true;
        $this->participantes = $usuarios->map(fn (User $pessoa): array => $this->normalizarParticipante($pessoa))->values()->all();
        $this->participantesConsultados = true;
        $this->resetErrorBag('data.publico_regras');
    }

    /** @param int|string $id */
    public function removerParticipante(int|string $id): void
    {
        $this->autorizarCriacao();

        $id = (int) $id;
        $participanteExiste = collect($this->participantes)->contains(
            fn (array $participante): bool => (int) ($participante['id'] ?? 0) === $id,
        );

        if (! $participanteExiste) {
            return;
        }

        $ids = $this->ids($this->data['publico_excecoes_ids'] ?? []);
        $ids[] = $id;
        $this->data['publico_excecoes_ids'] = array_values(array_unique($ids));
        $this->participantes = array_values(array_filter(
            $this->participantes,
            fn (array $participante): bool => (int) ($participante['id'] ?? 0) !== $id,
        ));
    }

    public function removerParticipantesDaEscola(string $escola): void
    {
        $this->autorizarCriacao();

        $idsRemovidos = collect($this->participantes)
            ->filter(fn (array $participante): bool => (string) ($participante['escola'] ?? '') === $escola)
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        if ($idsRemovidos === []) {
            return;
        }

        $excecoes = $this->ids($this->data['publico_excecoes_ids'] ?? []);
        $this->data['publico_excecoes_ids'] = array_values(array_unique([...$excecoes, ...$idsRemovidos]));
        $this->participantes = array_values(array_filter(
            $this->participantes,
            fn (array $participante): bool => (string) ($participante['escola'] ?? '') !== $escola,
        ));
    }

    public function definirTransporte(string $valor): void
    {
        $this->autorizarCriacao();

        if ($this->somenteTransporte) {
            $valor = 'sim';
        }

        $this->data['precisa_transporte_evento'] = $valor === 'sim' ? 'sim' : 'nao';
        $this->data['enviar_escolas_especificas'] = $valor === 'sim';

        if ($valor !== 'sim') {
            $this->data['escolas_agendadas'] = [];
            $this->data['transporte_excecoes_aluno_ids'] = [];
            $this->alunos = [];
            $this->alunosConsultados = false;
        }
    }

    public function invalidarAlunos(): void
    {
        $this->data['escolas_agendadas'] = [];
        $this->data['transporte_excecoes_aluno_ids'] = [];
        $this->alunos = [];
        $this->alunosConsultados = false;
        $this->resetErrorBag('data.escolas_agendadas');
    }

    public function buscarAlunos(): void
    {
        $usuario = $this->autorizarCriacao();

        if (($this->data['precisa_transporte_evento'] ?? 'nao') !== 'sim') {
            return;
        }

        if (! $this->possuiFiltroTransporte()) {
            $this->addError('data.escolas_agendadas', 'Selecione escola, tipo de escola, série ou turno para buscar os alunos.');
            $this->alunosConsultados = false;
            $this->alunos = [];

            return;
        }

        try {
            $linhas = $this->gerarDistribuicao($usuario);
            $this->data['escolas_agendadas'] = $linhas;
            $this->alunos = $this->consultarAlunos($linhas);
            $this->alunosConsultados = true;
            $this->resetErrorBag('data.escolas_agendadas');
        } catch (ValidationException $exception) {
            $this->adicionarErros($exception);
            $this->alunosConsultados = false;
            $this->alunos = [];
        }
    }

    /** @param int|string $id */
    public function removerAluno(int|string $id): void
    {
        $ids = $this->ids($this->data['transporte_excecoes_aluno_ids'] ?? []);
        $ids[] = (int) $id;
        $this->data['transporte_excecoes_aluno_ids'] = array_values(array_unique($ids));
        $this->alunos = array_values(array_filter(
            $this->alunos,
            fn (array $aluno): bool => (int) ($aluno['id'] ?? 0) !== (int) $id,
        ));
    }

    public function salvar(): void
    {
        $usuario = $this->autorizarCriacao();

        $this->validate($this->regrasCompletas());

        if (($this->data['precisa_transporte_evento'] ?? 'nao') === 'sim') {
            if (! $this->possuiFiltroTransporte()) {
                $this->addError('data.escolas_agendadas', 'Selecione ao menos um filtro de transporte.');

                return;
            }

            try {
                $this->data['escolas_agendadas'] = $this->gerarDistribuicao($usuario);
                $this->alunosConsultados = true;
            } catch (ValidationException $exception) {
                $this->adicionarErros($exception);

                return;
            }
        } else {
            $this->data['enviar_escolas_especificas'] = false;
            $this->data['escolas_agendadas'] = [];
        }

        $dados = [
            ...$this->data,
            'publico_tipo' => 'segmentado',
            'enviar_todas_escolas' => ! (bool) ($this->data['enviar_escolas_especificas'] ?? false),
            'publico_regras' => $this->data['publico_regras'] ?? [],
            'publico_excecoes_ids' => $this->ids($this->data['publico_excecoes_ids'] ?? []),
        ];
        unset($dados['publico_prefixos']);

        try {
            app(EventoCalendarioService::class)->criar($dados, [], $usuario);
        } catch (ValidationException $exception) {
            $this->adicionarErros($exception);

            return;
        }

        $this->fechar();
        $this->dispatch('evento-calendario-criado');

        Notification::make()
            ->title('Evento criado')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.home.evento-calendario-modal');
    }

    /** @return array<string, mixed> */
    private function dadosIniciais(): array
    {
        return [
            'titulo' => '',
            'descricao' => '',
            'local' => '',
            'endereco_mapa' => '',
            'latitude' => null,
            'longitude' => null,
            'cor' => EventoCalendarioCor::AZUL->value,
            'link_acao' => '',
            'texto_botao' => '',
            'categoria' => '',
            'categoria_detalhe' => '',
            'data_evento' => now()->toDateString(),
            'periodo' => '',
            'hora_inicio' => '',
            'hora_fim' => '',
            'publico_tipo' => 'segmentado',
            'publico_escola_ids' => [],
            'publico_prefixos' => [],
            'publico_funcao_ids' => [],
            'publico_turnos' => [],
            'publico_serie_ids' => [],
            'publico_componente_ids' => [],
            'publico_regras' => [],
            'publico_excecoes_ids' => [],
            'publico_preview_aplicado' => false,
            'precisa_transporte_evento' => $this->somenteTransporte ? 'sim' : 'nao',
            'enviar_escolas_especificas' => $this->somenteTransporte,
            'transporte_escola_ids' => [],
            'transporte_prefixos' => [],
            'transporte_serie_ids' => [],
            'transporte_turnos' => [],
            'escolas_agendadas' => [],
            'transporte_excecoes_aluno_ids' => [],
        ];
    }

    private function carregarOpcoes(User $usuario): void
    {
        $contexto = app(DashboardUserContextFactory::class)->make($usuario);
        $escolas = Escola::query()->where('ativo', true)->orderBy('nome');

        if (! $contexto->escopoGlobal) {
            $escolas->whereKey($contexto->escolaIds);
        }

        $this->escolasOpcoes = $escolas->pluck('nome', 'id')->all();
        $this->seriesOpcoes = Serie::query()
            ->when(! $contexto->escopoGlobal, fn (Builder $query): Builder => $query->whereHas(
                'turmas',
                fn (Builder $turmas): Builder => $turmas->whereIn('id_escola', $contexto->escolaIds),
            ))
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
        $this->funcoesOpcoes = FuncaoAdministrativa::query()
            ->where('ativo', true)
            ->where(function (Builder $funcoes): void {
                $funcoes
                    ->where('exige_professor', true)
                    ->orWhere('direcao_escolar', true)
                    ->orWhere('coordenacao_pedagogica', true)
                    ->orWhere('secretaria_escolar', true)
                    ->orWhereIn('nome', ['Manutenção', 'Obras', 'Motorista', 'Transporte', 'Assessoria Pedagógica']);
            })
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
        $this->componentesOpcoes = ComponenteCurricular::query()->orderBy('nome')->pluck('nome', 'id')->all();
    }

    /** @return array<string, mixed> */
    private function regraPublicoAtual(): array
    {
        $contexto = app(DashboardUserContextFactory::class)->make($this->autorizarCriacao());
        $escolas = $this->ids($this->data['publico_escola_ids'] ?? []);
        $prefixos = collect($this->data['publico_prefixos'] ?? [])
            ->map(fn ($prefixo): string => mb_strtoupper(trim((string) $prefixo)))
            ->filter(fn (string $prefixo): bool => in_array($prefixo, ['CMEI', 'ESCOLA'], true))
            ->unique()
            ->values();

        if ($prefixos->isNotEmpty()) {
            $escolasPorPrefixo = collect($this->escolasOpcoes)
                ->filter(fn (string $nome): bool => $prefixos->contains(
                    fn (string $prefixo): bool => str_starts_with(mb_strtoupper(trim($nome)), $prefixo),
                ))
                ->keys()
                ->map(fn ($id): int => (int) $id)
                ->all();

            $escolas = $escolas === []
                ? $escolasPorPrefixo
                : array_values(array_intersect($escolas, $escolasPorPrefixo));
        } elseif ($escolas === [] && ! $contexto->escopoGlobal) {
            $escolas = $contexto->escolaIds;
        }

        return [
            'escola_ids' => $escolas,
            'funcao_ids' => $this->ids($this->data['publico_funcao_ids'] ?? []),
            'turnos' => $this->strings($this->data['publico_turnos'] ?? []),
            'serie_ids' => $this->ids($this->data['publico_serie_ids'] ?? []),
            'componente_ids' => $this->ids($this->data['publico_componente_ids'] ?? []),
        ];
    }

    private function possuiFiltroPublico(): bool
    {
        return $this->ids($this->data['publico_escola_ids'] ?? []) !== []
            || $this->strings($this->data['publico_prefixos'] ?? []) !== []
            || $this->ids($this->data['publico_funcao_ids'] ?? []) !== []
            || $this->strings($this->data['publico_turnos'] ?? []) !== []
            || $this->ids($this->data['publico_serie_ids'] ?? []) !== []
            || $this->ids($this->data['publico_componente_ids'] ?? []) !== [];
    }

    private function possuiFiltroTransporte(): bool
    {
        return $this->ids($this->data['transporte_escola_ids'] ?? []) !== []
            || $this->strings($this->data['transporte_prefixos'] ?? []) !== []
            || $this->ids($this->data['transporte_serie_ids'] ?? []) !== []
            || $this->strings($this->data['transporte_turnos'] ?? []) !== [];
    }

    /** @return list<array<string, mixed>> */
    private function gerarDistribuicao(User $usuario): array
    {
        return app(EventoCalendarioEscolaService::class)->gerarPorFiltros([
            'selecionar_todas_escolas' => $this->ids($this->data['transporte_escola_ids'] ?? []) === [],
            'escola_ids' => $this->ids($this->data['transporte_escola_ids'] ?? []),
            'prefixos' => $this->strings($this->data['transporte_prefixos'] ?? []),
            'serie_ids' => $this->ids($this->data['transporte_serie_ids'] ?? []),
            'turnos' => $this->strings($this->data['transporte_turnos'] ?? []),
            'precisa_transporte' => true,
        ], $usuario, (string) ($this->data['hora_inicio'] ?? ''), (string) ($this->data['hora_fim'] ?? ''));
    }

    /** @param list<array<string, mixed>> $linhas @return list<array<string, mixed>> */
    private function consultarAlunos(array $linhas): array
    {
        $alunos = Aluno::query()
            ->select(['id', 'nome', 'id_turma'])
            ->with(['turma:id,nome,turno,id_escola,id_serie', 'turma.escola:id,nome', 'turma.serie:id,nome'])
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->where('status', Aluno::STATUS_MATRICULADO)
            ->whereHas('turma', function (Builder $turmas) use ($linhas): void {
                $turmas->where(function (Builder $filtros) use ($linhas): void {
                    foreach ($linhas as $linha) {
                        $filtros->orWhere(function (Builder $turma) use ($linha): void {
                            $turma->where('id_escola', (int) $linha['escola_id']);

                            if (($linha['escopo_transporte'] ?? null) === 'series') {
                                $turma->whereIn('id_serie', $this->ids($linha['series_ids'] ?? []));
                            }

                            if (($linha['escopo_transporte'] ?? null) === 'turmas') {
                                $turma->whereKey($this->ids($linha['turmas_ids'] ?? []));
                            }
                        });
                    }
                });
            })
            ->orderBy('nome')
            ->get();

        return $alunos->map(function (Aluno $aluno): array {
            return [
                'id' => (int) $aluno->id,
                'nome' => (string) $aluno->nome,
                'escola' => (string) ($aluno->turma?->escola?->nome ?? 'Escola não identificada'),
                'serie' => (string) ($aluno->turma?->serie?->nome ?? 'Série não identificada'),
                'turma' => (string) ($aluno->turma?->nome ?? 'Turma não identificada'),
                'turno' => $this->turnoLabel($aluno->turma?->turno),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function normalizarParticipante(User $usuario): array
    {
        $escolas = collect($usuario->escolas ?? [])
            ->merge($usuario->escola ? [$usuario->escola] : [])
            ->merge(collect($usuario->servidores ?? [])->flatMap(fn ($servidor) => $servidor->vinculosAtivos ?? [])->pluck('escola'))
            ->merge(collect($usuario->servidores ?? [])->flatMap(fn ($servidor) => $servidor->professores ?? [])->pluck('escola'))
            ->filter()
            ->unique('id')
            ->pluck('nome')
            ->values();
        $cargo = collect($usuario->servidores ?? [])
            ->flatMap(fn ($servidor) => $servidor->funcoesAtivas ?? [])
            ->pluck('nome')
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            'id' => (int) $usuario->id,
            'nome' => (string) $usuario->name,
            'email' => (string) ($usuario->email ?: 'Não informado'),
            'cargo' => $cargo ?: 'Professor',
            'escola' => (string) ($escolas->first() ?? 'Escola não identificada'),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function regrasDaEtapaUm(): array
    {
        return [
            'data.titulo' => ['required', 'string', 'max:160'],
            'data.descricao' => ['nullable', 'string', 'max:5000'],
            'data.local' => ['nullable', 'string', 'max:255'],
            'data.endereco_mapa' => ['nullable', 'string', 'max:500'],
            'data.categoria' => ['required', Rule::enum(\App\Models\Enums\EventoCalendarioCategoria::class)],
            'data.categoria_detalhe' => [
                'nullable',
                'string',
                'max:160',
                Rule::requiredIf(fn (): bool => ($this->data['categoria'] ?? null) === 'outro'),
            ],
            'data.cor' => ['required', Rule::enum(EventoCalendarioCor::class)],
            'data.link_acao' => ['nullable', 'string', 'max:2048', 'starts_with:https://'],
            'data.texto_botao' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => filled($this->data['link_acao'] ?? null))],
            'data.data_evento' => ['required', 'date'],
            'data.hora_inicio' => ['required', 'date_format:H:i'],
            'data.hora_fim' => ['required', 'date_format:H:i', 'after:data.hora_inicio'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function regrasCompletas(): array
    {
        return $this->regrasDaEtapaUm();
    }

    private function autorizarCriacao(): User
    {
        $preview = app(ProfilePreviewService::class);
        $usuario = $preview->effectiveUser();

        abort_unless($usuario instanceof User, 403);
        abort_unless(! $preview->isActive(), 403);
        Gate::forUser($usuario)->authorize('create', EventoCalendario::class);

        return $usuario;
    }

    /** @return list<int> */
    private function ids(mixed $valores): array
    {
        return collect(is_iterable($valores) ? $valores : [$valores])
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function strings(mixed $valores): array
    {
        return collect(is_iterable($valores) ? $valores : [$valores])
            ->map(fn ($valor): string => mb_strtolower(trim((string) $valor)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function turnoLabel(?string $turno): string
    {
        return [
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
        ][$turno ?? ''] ?? 'Turno não identificado';
    }

    private function adicionarErros(ValidationException $exception): void
    {
        foreach ($exception->errors() as $campo => $mensagens) {
            foreach ($mensagens as $mensagem) {
                $this->addError(str_starts_with($campo, 'data.') ? $campo : "data.{$campo}", $mensagem);
            }
        }
    }
}
