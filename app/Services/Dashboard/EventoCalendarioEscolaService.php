<?php

namespace App\Services\Dashboard;

use App\Models\Aluno;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioEscola;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\PessoaScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EventoCalendarioEscolaService
{
    public function __construct(private readonly PessoaScopeService $scope) {}

    /**
     * @param array<int, mixed> $linhas
     * @return list<array<string, mixed>>
     */
    public function normalizar(
        array $linhas,
        User $ator,
        string $horaInicioPadrao,
        string $horaFimPadrao,
    ): array {
        $horaInicioPadrao = $this->hora($horaInicioPadrao, 'hora_inicio');
        $horaFimPadrao = $this->hora($horaFimPadrao, 'hora_fim');
        $this->assertOrdemHorarios($horaInicioPadrao, $horaFimPadrao, 'hora_fim');

        $normalizadas = [];
        $escolasVistas = [];

        foreach (array_values($linhas) as $indice => $linha) {
            if (! is_array($linha)) {
                throw ValidationException::withMessages([
                    "escolas_agendadas.{$indice}" => 'Informe uma configuração de escola válida.',
                ]);
            }

            $prefixo = "escolas_agendadas.{$indice}";
            $escolaId = $this->idPositivo($linha['escola_id'] ?? null, "{$prefixo}.escola_id");

            if (isset($escolasVistas[$escolaId])) {
                throw ValidationException::withMessages([
                    "{$prefixo}.escola_id" => 'A mesma escola não pode ser adicionada mais de uma vez.',
                ]);
            }

            $this->assertEscolaAcessivel($ator, $escolaId, "{$prefixo}.escola_id");
            $escolasVistas[$escolaId] = true;

            $horaInicio = $this->hora(
                $linha['hora_inicio'] ?? $horaInicioPadrao,
                "{$prefixo}.hora_inicio",
            );
            $horaFim = $this->hora(
                $linha['hora_fim'] ?? $horaFimPadrao,
                "{$prefixo}.hora_fim",
            );
            $this->assertOrdemHorarios($horaInicio, $horaFim, "{$prefixo}.hora_fim");

            $precisaTransporte = filter_var(
                $linha['precisa_transporte'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );
            $escopo = null;
            $serieIds = [];
            $turmaIds = [];
            $estimativa = null;

            if ($precisaTransporte) {
                $escopo = $linha['escopo_transporte'] ?? null;
                $escopo = $escopo instanceof EventoCalendarioTransporteEscopo
                    ? $escopo
                    : EventoCalendarioTransporteEscopo::tryFrom((string) $escopo);

                if (! $escopo) {
                    throw ValidationException::withMessages([
                        "{$prefixo}.escopo_transporte" => 'Selecione como os estudantes serão considerados para o transporte.',
                    ]);
                }

                $serieIds = $escopo === EventoCalendarioTransporteEscopo::SERIES
                    ? $this->ids($linha['series_ids'] ?? [], "{$prefixo}.series_ids")
                    : [];
                $turmaIds = $escopo === EventoCalendarioTransporteEscopo::TURMAS
                    ? $this->ids($linha['turmas_ids'] ?? [], "{$prefixo}.turmas_ids")
                    : [];

                $this->assertSelecaoTransporte($escolaId, $escopo, $serieIds, $turmaIds, $prefixo);
                $estimativa = $this->contarEstudantes($escolaId, $escopo, $serieIds, $turmaIds);
            }

            $normalizadas[] = [
                'escola_id' => $escolaId,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'precisa_transporte' => $precisaTransporte,
                'escopo_transporte' => $escopo?->value,
                'quantidade_estimada_transporte' => $estimativa,
                'series_ids' => $serieIds,
                'turmas_ids' => $turmaIds,
            ];
        }

        if ($normalizadas === []) {
            throw ValidationException::withMessages([
                'escolas_agendadas' => 'Selecione ao menos uma escola para o evento.',
            ]);
        }

        return $normalizadas;
    }

    /** @param list<array<string, mixed>> $linhas */
    public function sincronizar(EventoCalendario $evento, array $linhas): void
    {
        $idsMantidos = [];

        foreach ($linhas as $linha) {
            $seriesIds = Arr::pull($linha, 'series_ids', []);
            $turmasIds = Arr::pull($linha, 'turmas_ids', []);

            $agendamento = EventoCalendarioEscola::query()->updateOrCreate(
                [
                    'evento_calendario_id' => $evento->getKey(),
                    'escola_id' => $linha['escola_id'],
                ],
                $linha,
            );

            $agendamento->series()->sync($seriesIds);
            $agendamento->turmas()->sync($turmasIds);
            $idsMantidos[] = (int) $agendamento->getKey();
        }

        $evento->escolasAgendadas()
            ->when($idsMantidos !== [], fn (Builder $query): Builder => $query->whereKeyNot($idsMantidos))
            ->delete();
    }

    /** @return list<array<string, mixed>> */
    public function paraFormulario(EventoCalendario $evento): array
    {
        $evento->loadMissing([
            'escolasAgendadas.series:id',
            'escolasAgendadas.turmas:id',
        ]);

        return $evento->escolasAgendadas
            ->map(fn (EventoCalendarioEscola $item): array => [
                'escola_id' => (int) $item->escola_id,
                'hora_inicio' => substr((string) $item->hora_inicio, 0, 5),
                'hora_fim' => substr((string) $item->hora_fim, 0, 5),
                'precisa_transporte' => (bool) $item->precisa_transporte,
                'escopo_transporte' => $item->escopo_transporte?->value,
                'series_ids' => $item->series->modelKeys(),
                'turmas_ids' => $item->turmas->modelKeys(),
            ])
            ->values()
            ->all();
    }

    /**
     * Retorno seguro para a prévia reativa do formulário. A validação definitiva
     * e a contagem persistida são repetidas dentro da transação de salvamento.
     */
    public function estimarParaFormulario(
        ?User $ator,
        mixed $escolaId,
        mixed $escopo,
        mixed $seriesIds,
        mixed $turmasIds,
    ): int {
        $id = filter_var($escolaId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tipo = $escopo instanceof EventoCalendarioTransporteEscopo
            ? $escopo
            : EventoCalendarioTransporteEscopo::tryFrom((string) $escopo);

        if (! $ator || ! $id || ! $tipo || ! $this->scope->canAccessEscola($ator, (int) $id)) {
            return 0;
        }

        $series = $this->ids(is_iterable($seriesIds) ? $seriesIds : []);
        $turmas = $this->ids(is_iterable($turmasIds) ? $turmasIds : []);

        try {
            $this->assertSelecaoTransporte((int) $id, $tipo, $series, $turmas, 'transporte');

            return $this->contarEstudantes((int) $id, $tipo, $series, $turmas);
        } catch (ValidationException) {
            return 0;
        }
    }

    private function contarEstudantes(
        int $escolaId,
        EventoCalendarioTransporteEscopo $escopo,
        array $serieIds,
        array $turmaIds,
    ): int {
        return Aluno::query()
            ->join('turmas', 'turmas.id', '=', 'alunos.id_turma')
            ->where('turmas.id_escola', $escolaId)
            ->where('alunos.tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->where('alunos.status', Aluno::STATUS_MATRICULADO)
            ->when(
                $escopo === EventoCalendarioTransporteEscopo::SERIES,
                fn (Builder $query): Builder => $query->whereIn('turmas.id_serie', $serieIds),
            )
            ->when(
                $escopo === EventoCalendarioTransporteEscopo::TURMAS,
                fn (Builder $query): Builder => $query->whereIn('turmas.id', $turmaIds),
            )
            ->distinct('alunos.id')
            ->count('alunos.id');
    }

    private function assertSelecaoTransporte(
        int $escolaId,
        EventoCalendarioTransporteEscopo $escopo,
        array $serieIds,
        array $turmaIds,
        string $prefixo,
    ): void {
        if ($escopo === EventoCalendarioTransporteEscopo::SERIES) {
            if ($serieIds === []) {
                throw ValidationException::withMessages([
                    "{$prefixo}.series_ids" => 'Selecione ao menos uma série.',
                ]);
            }

            $validas = Serie::query()
                ->whereKey($serieIds)
                ->whereHas('turmas', fn (Builder $query): Builder => $query->where('id_escola', $escolaId))
                ->count();

            if ($validas !== count($serieIds)) {
                throw ValidationException::withMessages([
                    "{$prefixo}.series_ids" => 'Selecione apenas séries que possuem turmas na escola informada.',
                ]);
            }
        }

        if ($escopo === EventoCalendarioTransporteEscopo::TURMAS) {
            if ($turmaIds === []) {
                throw ValidationException::withMessages([
                    "{$prefixo}.turmas_ids" => 'Selecione ao menos uma turma.',
                ]);
            }

            $validas = Turma::query()
                ->whereKey($turmaIds)
                ->where('id_escola', $escolaId)
                ->count();

            if ($validas !== count($turmaIds)) {
                throw ValidationException::withMessages([
                    "{$prefixo}.turmas_ids" => 'Selecione apenas turmas pertencentes à escola informada.',
                ]);
            }
        }
    }

    private function assertEscolaAcessivel(User $ator, int $escolaId, string $campo): void
    {
        $existe = Escola::query()->whereKey($escolaId)->where('ativo', true)->exists();

        if (! $existe || ! $this->scope->canAccessEscola($ator, $escolaId)) {
            throw ValidationException::withMessages([
                $campo => 'Selecione uma escola ativa pertencente ao seu contexto de acesso.',
            ]);
        }
    }

    private function hora(mixed $hora, string $campo): string
    {
        $hora = trim((string) $hora);

        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $hora)) {
            throw ValidationException::withMessages([$campo => 'Informe um horário válido.']);
        }

        return substr($hora, 0, 5);
    }

    private function assertOrdemHorarios(string $inicio, string $fim, string $campo): void
    {
        if ($fim <= $inicio) {
            throw ValidationException::withMessages([
                $campo => 'O horário final deve ser posterior ao horário inicial.',
            ]);
        }
    }

    private function idPositivo(mixed $id, string $campo): int
    {
        $normalizado = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! $normalizado) {
            throw ValidationException::withMessages([$campo => 'Selecione uma opção válida.']);
        }

        return (int) $normalizado;
    }

    /** @return list<int> */
    private function ids(iterable $ids, ?string $campo = null): array
    {
        $valores = collect($ids)->values();

        if ($campo && $valores->contains(
            fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false,
        )) {
            throw ValidationException::withMessages([
                $campo => 'A seleção contém um identificador inválido.',
            ]);
        }

        return $valores
            ->filter(fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
