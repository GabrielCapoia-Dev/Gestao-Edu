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
        [$dados, $agendamentos] = $this->prepararDados($dados, $ator);
        $publico = $this->publicoPadrao($dados, $agendamentos, $ator);

        if ($publicarAutomaticamente && $origem === EventoCalendarioOrigem::MANUAL) {
            $dados['ativo'] = true;
        } elseif (! Gate::forUser($ator)->allows('publish', EventoCalendario::class)) {
            $dados['ativo'] = false;
        }

        return DB::transaction(function () use ($dados, $agendamentos, $publico, $ator, $origem, $importacao): EventoCalendario {
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
        Gate::forUser($ator)->authorize('update', $evento);
        $evento->loadMissing('publicoAlvo');
        $this->rejeitarPublicoParalelo($publico ?? [], $dados);
        [$dados, $agendamentos] = $this->prepararDados($dados, $ator);

        if (! Gate::forUser($ator)->allows('publish', $evento)) {
            Arr::forget($dados, 'ativo');
        }

        return DB::transaction(function () use (
            $evento,
            $dados,
            $agendamentos,
            $ator,
            $importacao,
        ): EventoCalendario {
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

            return $evento->refresh()->load('escolasAgendadas');
        });
    }

    public function duplicar(EventoCalendario $evento, User $ator): EventoCalendario
    {
        Gate::forUser($ator)->authorize('duplicate', $evento);
        $evento->loadMissing([
            'escolasAgendadas.series:id',
            'escolasAgendadas.turmas:id',
        ]);

        $dados = Arr::except($evento->getAttributes(), [
            'id', 'publico_alvo_id', 'created_at', 'updated_at', 'deleted_at',
            'criado_por_id', 'atualizado_por_id', 'excluido_por_id',
            'fonte_externa', 'identificador_externo', 'ultima_importacao_id',
        ]);
        $dados['titulo'] = mb_substr('Cópia de '.$evento->titulo, 0, 160);
        $dados['ativo'] = false;
        $dados['escolas_agendadas'] = $this->escolas->paraFormulario($evento);

        return $this->criar(
            $dados,
            [],
            $ator,
            EventoCalendarioOrigem::MANUAL,
            null,
            false,
        );
    }

    public function alternarPublicacao(EventoCalendario $evento, User $ator): EventoCalendario
    {
        Gate::forUser($ator)->authorize('publish', $evento);

        $evento->forceFill([
            'ativo' => ! $evento->ativo,
            'atualizado_por_id' => $ator->getKey(),
        ])->save();

        return $evento->refresh();
    }

    /** @param iterable<EventoCalendario> $eventos */
    public function desativarEmMassa(iterable $eventos, User $ator): int
    {
        $eventos = collect($eventos)
            ->filter(fn ($evento): bool => $evento instanceof EventoCalendario)
            ->unique(fn (EventoCalendario $evento): int => (int) $evento->getKey())
            ->values();

        foreach ($eventos as $evento) {
            Gate::forUser($ator)->authorize('publish', $evento);
        }

        return DB::transaction(function () use ($eventos, $ator): int {
            $alterados = 0;

            foreach ($eventos as $evento) {
                if (! $evento->ativo) {
                    continue;
                }

                $evento->forceFill([
                    'ativo' => false,
                    'atualizado_por_id' => $ator->getKey(),
                ])->save();
                $alterados++;
            }

            return $alterados;
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function prepararDados(array $dados, User $ator): array
    {
        $dataEvento = Arr::pull($dados, 'data_evento');
        $horaInicio = Arr::pull($dados, 'hora_inicio');
        $horaFim = Arr::pull($dados, 'hora_fim');
        Arr::forget($dados, 'periodo');
        Arr::forget($dados, 'enviar_todos_usuarios');

        if (filled($dataEvento) || filled($horaInicio) || filled($horaFim)) {
            $data = trim((string) $dataEvento);
            $inicio = trim((string) $horaInicio);
            $fim = trim((string) $horaFim);

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
                'link_acao' => 'Informe uma URL HTTP(S) ou um caminho interno iniciado por /.',
            ]);
        }

        $enviarEspecificas = Arr::pull($dados, 'enviar_escolas_especificas');

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
            );
        }

        $dados['enviar_todas_escolas'] = $enviarTodas;
        $dados['escola_id'] = null;
        $dados['setor_id'] = null;
        $dados['assunto'] = null;
        $dados['progresso'] = null;
        $dados['status'] = EventoCalendarioStatus::AGENDADO->value;
        $dados['prioridade'] = DashboardPrioridade::Normal->value;

        $validator = validator($dados, [
            'titulo' => ['required', 'string', 'max:160'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'categoria' => ['required', Rule::enum(EventoCalendarioCategoria::class)],
            'prioridade' => ['required', Rule::enum(DashboardPrioridade::class)],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'link_acao' => ['nullable', 'string', 'max:2048'],
            'texto_botao' => ['nullable', 'string', 'max:80'],
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
    private function publicoPadrao(
        array $dados,
        array $agendamentos,
        User $ator,
    ): array {
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

        if ($publico !== []) {
            throw ValidationException::withMessages([
                'publico_alvo' => 'Eventos manuais devem ser distribuídos exclusivamente por escola.',
            ]);
        }
    }

    private function linkSeguro(mixed $link): bool
    {
        $link = trim((string) $link);

        if ($link === '') {
            return false;
        }

        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return true;
        }

        return in_array(strtolower((string) parse_url($link, PHP_URL_SCHEME)), ['http', 'https'], true);
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
