<?php

namespace App\Services\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\ImportacaoEventoCalendario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventoCalendarioService
{
    public function __construct(
        private readonly PublicoAlvoService $publicos,
        private readonly EventoCalendarioEscolaService $escolas,
        private readonly DashboardUserContextFactory $contextos,
        private readonly EventoCalendarioWorkflowService $workflow,
        private readonly EventoTransporteAlocacaoService $alocacoesTransporte,
        private readonly EventoTransporteDisponibilidadeService $disponibilidadeTransporte,
        private readonly EventoCalendarioPublicoService $publicoEventos,
    ) {}

    public function criar(
        array $dados,
        array $publico,
        User $ator,
        EventoCalendarioOrigem $origem = EventoCalendarioOrigem::MANUAL,
        ?ImportacaoEventoCalendario $importacao = null,
        bool $publicarAutomaticamente = true,
    ): EventoCalendario {
        Gate::forUser($ator)->authorize('create', EventoCalendario::class);
        $this->rejeitarPublicoParalelo($publico, $dados);
        $regrasPublico = $dados['publico_regras'] ?? [];
        $excecoesPublico = $dados['publico_excecoes_ids'] ?? [];
        $publicacaoSolicitada = array_key_exists('ativo', $dados)
            ? filter_var($dados['ativo'], FILTER_VALIDATE_BOOLEAN)
            : $publicarAutomaticamente;
        [$dados, $agendamentos] = $this->prepararDados($dados, $ator);
        $publico = $this->publicoPadrao($dados, $agendamentos, $ator);
        $possuiTransporte = $this->agendamentosPossuemTransporte($agendamentos);

        if (
            Gate::forUser($ator)->allows('requiresTransport', EventoCalendario::class)
            && ! $possuiTransporte
        ) {
            throw ValidationException::withMessages([
                'escolas_agendadas' => 'Seu nível de acesso permite criar somente eventos com transporte solicitado.',
            ]);
        }

        [$dados['status'], $dados['ativo']] = $this->estadoInicial(
            $possuiTransporte,
            $publicacaoSolicitada,
            $origem,
            $publicarAutomaticamente,
            $ator,
        );

        return DB::transaction(function () use ($dados, $agendamentos, $publico, $ator, $origem, $importacao, $regrasPublico, $excecoesPublico): EventoCalendario {
            $publicoAlvo = $this->publicos->criar($ator, $publico);
            $evento = EventoCalendario::query()->create([
                ...$dados,
                'publico_alvo_id' => $publicoAlvo->getKey(),
                'origem' => $origem,
                'ultima_importacao_id' => $importacao?->getKey(),
                'criado_por_id' => $ator->getKey(),
                'atualizado_por_id' => $ator->getKey(),
            ]);

            $this->escolas->sincronizar($evento, $agendamentos);
            $this->publicoEventos->sincronizar($evento, $regrasPublico, $excecoesPublico, $ator);
            $this->workflow->registrarCriacao($evento, $ator);

            return $evento->refresh()->load('escolasAgendadas');
        });
    }

    public function atualizar(
        EventoCalendario $evento,
        array $dados,
        ?array $publico,
        User $ator,
        ?ImportacaoEventoCalendario $importacao = null,
    ): EventoCalendario {
        return DB::transaction(function () use ($evento, $dados, $publico, $ator, $importacao): EventoCalendario {
            $evento = EventoCalendario::query()
                ->with([
                    'publicoAlvo',
                    'escolasAgendadas.series:id',
                    'escolasAgendadas.turmas:id',
                ])
                ->lockForUpdate()
                ->findOrFail($evento->getKey());

            Gate::forUser($ator)->authorize('update', $evento);

            $statusAnterior = $evento->status;
            $possuiaTransporte = $evento->possuiTransporte();
            $assinaturaAnterior = $this->assinaturaTransporteAtual($evento);
            $publicacaoSolicitada = array_key_exists('ativo', $dados)
                ? filter_var($dados['ativo'], FILTER_VALIDATE_BOOLEAN)
                : null;
            $this->rejeitarPublicoParalelo($publico ?? [], $dados);
            [$dados, $agendamentos] = $this->prepararDados($dados, $ator);
            $possuiTransporte = $this->agendamentosPossuemTransporte($agendamentos);
            $dataHorarioAlterado = ! $evento->data_inicio->equalTo($dados['data_inicio'])
                || ! $evento->data_fim->equalTo($dados['data_fim']);
            $transporteAlterado = ($possuiaTransporte || $possuiTransporte)
                && $assinaturaAnterior !== $this->assinaturaTransporteNova($dados, $agendamentos);
            [$dados['status'], $dados['ativo']] = $this->estadoAposAtualizacao(
                $evento,
                $statusAnterior,
                $possuiaTransporte,
                $possuiTransporte,
                $transporteAlterado,
                $publicacaoSolicitada,
                $ator,
            );

            if ($possuiaTransporte && ! $possuiTransporte) {
                $this->alocacoesTransporte->removerTodasDoEventoInternamente($evento, $ator);
            } elseif ($possuiTransporte
                && $dataHorarioAlterado
                && in_array($dados['status'], [
                    EventoCalendarioStatus::PENDENTE_APROVACAO,
                    EventoCalendarioStatus::PUBLICADO,
                ], true)) {
                $this->disponibilidadeTransporte->validarAlocacoesAtivasDoEvento(
                    $evento,
                    $dados['data_inicio'],
                    $dados['data_fim'],
                    true,
                );
            }

            $this->publicos->atualizar(
                $evento->publicoAlvo,
                $ator,
                $this->publicoPadrao($dados, $agendamentos, $ator),
            );

            $evento->fill([
                ...$dados,
                'atualizado_por_id' => $ator->getKey(),
                'ultima_importacao_id' => $importacao?->getKey() ?? $evento->ultima_importacao_id,
            ])->save();

            $this->escolas->sincronizar($evento, $agendamentos);
            $this->publicoEventos->sincronizar(
                $evento,
                $dados['publico_regras'] ?? [],
                $dados['publico_excecoes_ids'] ?? [],
                $ator,
            );
            $this->workflow->registrarAtualizacao(
                $evento,
                $ator,
                $statusAnterior,
                $transporteAlterado,
            );

            return $evento->refresh()->load('escolasAgendadas');
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function prepararDados(array $dados, User $ator): array
    {
        Arr::forget($dados, ['status', 'ativo']);
        Arr::forget($dados, ['publico_regras', 'publico_excecoes_ids']);
        $dataEvento = Arr::pull($dados, 'data_evento');
        $horaInicio = Arr::pull($dados, 'hora_inicio');
        $horaFim = Arr::pull($dados, 'hora_fim');
        Arr::forget($dados, 'periodo');
        Arr::forget($dados, 'enviar_todos_usuarios');

        if (filled($dataEvento) || filled($horaInicio) || filled($horaFim)) {
            $data = trim((string) $dataEvento);
            $inicio = trim((string) $horaInicio);
            $fim = trim((string) $horaFim);

            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $partes)) {
                $data = sprintf('%s-%s-%s', $partes[3], $partes[2], $partes[1]);
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                throw ValidationException::withMessages(['data_evento' => 'Informe uma data válida.']);
            }

            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $inicio)) {
                throw ValidationException::withMessages(['hora_inicio' => 'Informe um horário inicial válido.']);
            }

            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $fim) || $fim <= $inicio) {
                throw ValidationException::withMessages([
                    'hora_fim' => 'O horário final deve ser posterior ao horário inicial.',
                ]);
            }

            $dados['data_inicio'] = $this->dataHora($data, $inicio);
            $dados['data_fim'] = $this->dataHora($data, $fim);
        } else {
            try {
                if (! filled($dados['data_inicio'] ?? null) || ! filled($dados['data_fim'] ?? null)) {
                    throw new \InvalidArgumentException;
                }

                $dados['data_inicio'] = CarbonImmutable::parse($dados['data_inicio'], config('app.timezone'));
                $dados['data_fim'] = CarbonImmutable::parse($dados['data_fim'], config('app.timezone'));
            } catch (\Throwable) {
                throw ValidationException::withMessages(['data_evento' => 'Informe uma data válida.']);
            }
            $horaInicio = $dados['data_inicio']->format('H:i');
            $horaFim = $dados['data_fim']->format('H:i');
        }

        if (! $dados['data_inicio']->isSameDay($dados['data_fim']) || $dados['data_fim']->lte($dados['data_inicio'])) {
            throw ValidationException::withMessages([
                'data_evento' => 'O evento manual deve começar e terminar no mesmo dia.',
            ]);
        }

        $inserirLink = filter_var(Arr::pull($dados, 'inserir_link', filled($dados['link_acao'] ?? null)), FILTER_VALIDATE_BOOLEAN);

        if (! $inserirLink) {
            $dados['link_acao'] = null;
            $dados['texto_botao'] = null;
        } elseif (! $this->linkSeguro($dados['link_acao'] ?? null)) {
            throw ValidationException::withMessages([
                'link_acao' => 'Informe um link iniciado por https://.',
            ]);
        }

        if (($dados['categoria'] ?? null) !== EventoCalendarioCategoria::OUTRO->value) {
            $dados['categoria_detalhe'] = null;
        }

        $enviarEspecificas = Arr::pull($dados, 'enviar_escolas_especificas');
        $alunoExcecoes = Arr::pull($dados, 'transporte_excecoes_aluno_ids', []);

        if ($enviarEspecificas !== null) {
            $dados['enviar_todas_escolas'] = ! filter_var($enviarEspecificas, FILTER_VALIDATE_BOOLEAN);
        }

        $enviarTodas = filter_var($dados['enviar_todas_escolas'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $linhas = Arr::pull($dados, 'escolas_agendadas', []);

        if ($enviarTodas) {
            $contexto = $this->contextos->make($ator);

            if (
                ! $contexto->escopoGlobal
                && $contexto->escolaIds === []
            ) {
                throw ValidationException::withMessages([
                    'enviar_todas_escolas' => 'Seu usuário não possui escolas autorizadas para cadastrar este evento.',
                ]);
            }

            $agendamentos = [];
        } else {
            $agendamentos = $this->escolas->normalizar(
                is_array($linhas) ? $linhas : [],
                $ator,
                (string) $horaInicio,
                (string) $horaFim,
                is_array($alunoExcecoes) ? $alunoExcecoes : [],
            );
        }

        $dados['enviar_todas_escolas'] = $enviarTodas;
        $dados['escola_id'] = null;
        $dados['setor_id'] = null;
        $dados['assunto'] = null;
        $dados['progresso'] = null;
        $dados['prioridade'] = DashboardPrioridade::Normal->value;

        $validator = validator($dados, [
            'titulo' => ['required', 'string', 'max:160'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'local' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'categoria' => ['required', Rule::enum(EventoCalendarioCategoria::class)],
            'categoria_detalhe' => [
                'nullable',
                'string',
                'max:160',
                Rule::requiredIf(($dados['categoria'] ?? null) === EventoCalendarioCategoria::OUTRO->value),
            ],
            'prioridade' => ['required', Rule::enum(DashboardPrioridade::class)],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'link_acao' => ['nullable', 'string', 'max:2048'],
            'texto_botao' => [
                'nullable',
                'string',
                'max:80',
                Rule::requiredIf(filled($dados['link_acao'] ?? null)),
            ],
            'ativo' => ['boolean'],
            'cor' => ['required', Rule::enum(EventoCalendarioCor::class)],
        ], [
            'required' => 'Campo obrigatório.',
            'data_fim.after' => 'O horário final deve ser posterior ao horário inicial.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [$dados, $agendamentos];
    }

    /** @param list<array<string, mixed>> $agendamentos */
    private function agendamentosPossuemTransporte(array $agendamentos): bool
    {
        return collect($agendamentos)->contains(
            fn (array $agendamento): bool => (bool) ($agendamento['precisa_transporte'] ?? false),
        );
    }

    /** @return array{0: EventoCalendarioStatus, 1: bool} */
    private function estadoInicial(
        bool $possuiTransporte,
        bool $publicacaoSolicitada,
        EventoCalendarioOrigem $origem,
        bool $publicarAutomaticamente,
        User $ator,
    ): array {
        if ($possuiTransporte) {
            return [EventoCalendarioStatus::PENDENTE_APROVACAO, false];
        }

        if ($origem === EventoCalendarioOrigem::MANUAL && $publicarAutomaticamente) {
            return [EventoCalendarioStatus::PUBLICADO, true];
        }

        if ($publicacaoSolicitada) {
            Gate::forUser($ator)->authorize('publishCommon', EventoCalendario::class);

            return [EventoCalendarioStatus::PUBLICADO, true];
        }

        return [EventoCalendarioStatus::INATIVO, false];
    }

    /** @return array{0: EventoCalendarioStatus, 1: bool} */
    private function estadoAposAtualizacao(
        EventoCalendario $evento,
        EventoCalendarioStatus $statusAnterior,
        bool $possuiaTransporte,
        bool $possuiTransporte,
        bool $transporteAlterado,
        ?bool $publicacaoSolicitada,
        User $ator,
    ): array {
        if ($possuiTransporte) {
            $precisaNovaAnalise = ! $possuiaTransporte
                || $statusAnterior === EventoCalendarioStatus::REJEITADO
                || $transporteAlterado;

            if ($precisaNovaAnalise) {
                return [EventoCalendarioStatus::PENDENTE_APROVACAO, false];
            }

            return [
                $statusAnterior,
                $statusAnterior === EventoCalendarioStatus::PUBLICADO && (bool) $evento->ativo,
            ];
        }

        if ($publicacaoSolicitada === true) {
            Gate::forUser($ator)->authorize('publishCommon', EventoCalendario::class);

            return [EventoCalendarioStatus::PUBLICADO, true];
        }

        if ($publicacaoSolicitada === false) {
            if ($evento->ativo) {
                Gate::forUser($ator)->authorize('deactivate', $evento);
            }

            return [EventoCalendarioStatus::INATIVO, false];
        }

        if ($possuiaTransporte) {
            return $statusAnterior === EventoCalendarioStatus::PUBLICADO
                ? [EventoCalendarioStatus::PUBLICADO, true]
                : [EventoCalendarioStatus::INATIVO, false];
        }

        return [
            $statusAnterior,
            $statusAnterior === EventoCalendarioStatus::PUBLICADO && (bool) $evento->ativo,
        ];
    }

    private function assinaturaTransporteAtual(EventoCalendario $evento): string
    {
        return $this->assinaturaTransporte([
            'data_inicio' => $evento->data_inicio,
            'data_fim' => $evento->data_fim,
            'enviar_todas_escolas' => $evento->enviar_todas_escolas,
        ], $evento->escolasAgendadas
            ->map(fn ($agendamento): array => [
                'escola_id' => (int) $agendamento->escola_id,
                'precisa_transporte' => (bool) $agendamento->precisa_transporte,
                'escopo_transporte' => $agendamento->escopo_transporte?->value,
                'series_ids' => $agendamento->series->modelKeys(),
                'turmas_ids' => $agendamento->turmas->modelKeys(),
            ])
            ->all());
    }

    /** @param list<array<string, mixed>> $agendamentos */
    private function assinaturaTransporteNova(array $dados, array $agendamentos): string
    {
        return $this->assinaturaTransporte($dados, $agendamentos);
    }

    /** @param list<array<string, mixed>> $agendamentos */
    private function assinaturaTransporte(array $dados, array $agendamentos): string
    {
        $linhas = collect($agendamentos)
            ->map(fn (array $agendamento): array => [
                'escola_id' => (int) ($agendamento['escola_id'] ?? 0),
                'precisa_transporte' => (bool) ($agendamento['precisa_transporte'] ?? false),
                'escopo_transporte' => $agendamento['escopo_transporte'] ?? null,
                'series_ids' => collect($agendamento['series_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all(),
                'turmas_ids' => collect($agendamento['turmas_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all(),
            ])
            ->sortBy('escola_id')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'data_inicio' => $dados['data_inicio'] instanceof \DateTimeInterface
                ? $dados['data_inicio']->format('Y-m-d H:i:s')
                : (string) ($dados['data_inicio'] ?? ''),
            'data_fim' => $dados['data_fim'] instanceof \DateTimeInterface
                ? $dados['data_fim']->format('Y-m-d H:i:s')
                : (string) ($dados['data_fim'] ?? ''),
            'enviar_todas_escolas' => (bool) ($dados['enviar_todas_escolas'] ?? true),
            'escolas' => $linhas,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param list<array<string, mixed>> $agendamentos */
    private function publicoPadrao(
        array $dados,
        array $agendamentos,
        User $ator,
    ): array {
        $funcoes = collect($dados['funcoes_administrativas_ids'] ?? [])
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()->values()->all();

        if (($dados['publico_tipo'] ?? null) === 'segmentado') {
            $contexto = $this->contextos->make($ator);
            $ids = collect($dados['publico_regras'] ?? [])
                ->flatMap(fn ($regra): array => is_array($regra) ? ($regra['escola_ids'] ?? []) : [])
                ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id): int => (int) $id)->unique()->values()->all();

            if ($ids === []) {
                $ids = $contexto->escopoGlobal
                    ? Escola::query()->where('ativo', true)->pluck('id')->map(fn ($id): int => (int) $id)->all()
                    : $contexto->escolaIds;
            }

            return [
                'modo_correspondencia' => 'qualquer',
                'todos_usuarios' => false,
                'usuarios_ids' => [], 'roles_ids' => [], 'permissoes_ids' => [],
                'funcoes_administrativas_ids' => [],
                'escolas_ids' => $ids, 'setores_ids' => [],
            ];
        }

        if ($funcoes !== []) {
            return [
                'modo_correspondencia' => 'qualquer',
                'todos_usuarios' => false,
                'usuarios_ids' => [],
                'roles_ids' => [],
                'permissoes_ids' => [],
                'funcoes_administrativas_ids' => $funcoes,
                'escolas_ids' => [],
                'setores_ids' => [],
            ];
        }

        $todas = (bool) ($dados['enviar_todas_escolas'] ?? true);
        $escolaIds = [];

        if ($todas) {
            $contexto = $this->contextos->make($ator);
            $escolas = Escola::query()->where('ativo', true);

            if (! $contexto->escopoGlobal) {
                $escolas->whereKey($contexto->escolaIds);
            }

            $escolaIds = $escolas->pluck('id')->map(fn ($id): int => (int) $id)->all();
        } else {
            $escolaIds = collect($agendamentos)
                ->pluck('escola_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if ($escolaIds === []) {
            throw ValidationException::withMessages([
                'enviar_todas_escolas' => 'Selecione ao menos uma escola autorizada para este evento.',
            ]);
        }

        return [
            'modo_correspondencia' => 'qualquer',
            'todos_usuarios' => false,
            'usuarios_ids' => [],
            'roles_ids' => [],
            'permissoes_ids' => [],
            'funcoes_administrativas_ids' => [],
            'escolas_ids' => $escolaIds,
            'setores_ids' => [],
        ];
    }

    /** @param array<string, mixed> $publico @param array<string, mixed> $dados */
    private function rejeitarPublicoParalelo(array $publico, array $dados): void
    {
        if (filter_var($dados['enviar_todos_usuarios'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'enviar_todos_usuarios' => 'Eventos manuais devem ser distribuídos exclusivamente por escola.',
            ]);
        }

        // O público dos eventos manuais é tratado por PublicoAlvo e pelas
        // regras dinâmicas do evento. O argumento permanece por compatibilidade
        // com importações e chamadas antigas do serviço.
    }

    private function linkSeguro(mixed $link): bool
    {
        $link = trim((string) $link);

        if ($link === '') {
            return false;
        }

        return strtolower((string) parse_url($link, PHP_URL_SCHEME)) === 'https';
    }

    private function dataHora(string $data, string $hora): CarbonImmutable
    {
        try {
            $valor = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i',
                "{$data} {$hora}",
                config('app.timezone'),
            );
            $erros = \DateTimeImmutable::getLastErrors();

            if (
                ! $valor
                || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))
                || $valor->format('Y-m-d H:i') !== "{$data} {$hora}"
            ) {
                throw new \InvalidArgumentException;
            }

            return $valor;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['data_evento' => 'Informe uma data válida.']);
        }
    }
}
