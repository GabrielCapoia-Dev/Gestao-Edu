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
    private const TURNOS = ['manha', 'tarde', 'noite', 'integral'];

    private const PREFIXOS_ESCOLA = ['CMEI', 'ESCOLA'];

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
        array $alunoExcecoes = [],
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

            // A distribuição escolar usa o horário geral do evento. Os campos são
            // mantidos na tabela apenas por compatibilidade e auditoria histórica.
            $horaInicio = $horaInicioPadrao;
            $horaFim = $horaFimPadrao;

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

        $estimativas = $this->contarEstudantesPorEscola($normalizadas, $alunoExcecoes);

        foreach ($normalizadas as &$linha) {
            if ($linha['precisa_transporte']) {
                $linha['quantidade_estimada_transporte'] = (int) ($estimativas[$linha['escola_id']] ?? 0);
            }
        }
        unset($linha);

        return $normalizadas;
    }

    /**
     * Resolve filtros acadêmicos em uma fotografia de escolas participantes.
     * A mesma rotina atende o formulário e a importação por planilha.
     *
     * @param array<string, mixed> $filtros
     * @return list<array<string, mixed>>
     */
    public function gerarPorFiltros(
        array $filtros,
        User $ator,
        string $horaInicioPadrao,
        string $horaFimPadrao,
    ): array {
        $todasEscolas = filter_var(
            $filtros['selecionar_todas_escolas'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $escolaIdsEntrada = $filtros['escola_ids'] ?? [];
        $serieIdsEntrada = $filtros['serie_ids'] ?? [];
        $turmaIdsEntrada = $filtros['turma_ids'] ?? [];
        $prefixosEntrada = $filtros['prefixos'] ?? [];

        $escolaIds = $this->ids(is_iterable($escolaIdsEntrada) ? $escolaIdsEntrada : [], 'escolas_filtro_ids');
        $serieIds = $this->ids(is_iterable($serieIdsEntrada) ? $serieIdsEntrada : [], 'series_filtro_ids');
        $turmaIds = $this->ids(is_iterable($turmaIdsEntrada) ? $turmaIdsEntrada : [], 'turmas_filtro_ids');
        $prefixos = collect(is_iterable($prefixosEntrada) ? $prefixosEntrada : [])
            ->map(fn ($prefixo): string => mb_strtoupper(trim((string) $prefixo)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $turnosEntrada = $filtros['turnos'] ?? [];
        $turnos = collect(is_iterable($turnosEntrada) ? $turnosEntrada : [])
            ->map(fn ($turno): string => mb_strtolower(trim((string) $turno)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (array_diff($turnos, self::TURNOS) !== []) {
            throw ValidationException::withMessages([
                'turnos_filtro' => 'Selecione apenas turnos válidos.',
            ]);
        }

        if (array_diff($prefixos, self::PREFIXOS_ESCOLA) !== []) {
            throw ValidationException::withMessages([
                'prefixos_filtro' => 'Selecione apenas tipos de escola válidos.',
            ]);
        }

        $escolasAcessiveis = Escola::query()->where('ativo', true)->orderBy('nome');
        $this->scope->applyEscolaScope($escolasAcessiveis, $ator, 'id');

        if ($prefixos !== []) {
            $escolasAcessiveis->where(function (Builder $query) use ($prefixos): void {
                foreach ($prefixos as $prefixo) {
                    $query->orWhere('nome', 'like', $prefixo.'%');
                }
            });
        }

        if (! $todasEscolas) {
            if ($escolaIds === []) {
                throw ValidationException::withMessages([
                    'escolas_filtro_ids' => 'Selecione ao menos uma escola ou marque a opção de considerar todas.',
                ]);
            }

            $encontradas = (clone $escolasAcessiveis)->whereKey($escolaIds)->count();

            if ($encontradas !== count($escolaIds)) {
                throw ValidationException::withMessages([
                    'escolas_filtro_ids' => 'A seleção contém escola inativa ou fora do seu contexto de acesso.',
                ]);
            }

            $escolasAcessiveis->whereKey($escolaIds);
        }

        $idsNoEscopo = $escolasAcessiveis->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($idsNoEscopo === []) {
            throw ValidationException::withMessages([
                'escolas_filtro_ids' => 'Nenhuma escola disponível no seu contexto de acesso.',
            ]);
        }

        $possuiFiltroAcademico = $serieIds !== [] || $turmaIds !== [] || $turnos !== [];
        $turmas = Turma::query()
            ->whereIn('id_escola', $idsNoEscopo)
            ->when($serieIds !== [], fn (Builder $query): Builder => $query->whereIn('id_serie', $serieIds))
            ->when($turnos !== [], fn (Builder $query): Builder => $query->whereIn('turno', $turnos))
            ->when($turmaIds !== [], fn (Builder $query): Builder => $query->whereKey($turmaIds))
            ->get(['id', 'id_escola', 'id_serie', 'turno']);

        if ($serieIds !== [] && $turmas->pluck('id_serie')->unique()->count() !== count($serieIds)) {
            throw ValidationException::withMessages([
                'series_filtro_ids' => 'Uma ou mais séries não possuem turmas nos filtros e escolas informados.',
            ]);
        }

        if ($turmaIds !== [] && $turmas->pluck('id')->unique()->count() !== count($turmaIds)) {
            throw ValidationException::withMessages([
                'turmas_filtro_ids' => 'Uma ou mais turmas não pertencem às escolas, séries ou turnos informados.',
            ]);
        }

        $escolasSelecionadas = $possuiFiltroAcademico
            ? $turmas->pluck('id_escola')->map(fn ($id): int => (int) $id)->unique()->values()->all()
            : $idsNoEscopo;

        if ($escolasSelecionadas === []) {
            throw ValidationException::withMessages([
                'escolas_agendadas' => 'Nenhuma escola possui turmas compatíveis com os filtros informados.',
            ]);
        }

        $precisaTransporte = filter_var(
            $filtros['precisa_transporte'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $linhas = collect($escolasSelecionadas)->map(function (int $escolaId) use (
            $horaInicioPadrao,
            $horaFimPadrao,
            $precisaTransporte,
            $serieIds,
            $turmaIds,
            $turnos,
            $turmas,
        ): array {
            $turmasDaEscola = $turmas->where('id_escola', $escolaId);
            $usarTurmas = $turmaIds !== [] || $turnos !== [];
            $usarSeries = ! $usarTurmas && $serieIds !== [];

            return [
                'escola_id' => $escolaId,
                'hora_inicio' => $horaInicioPadrao,
                'hora_fim' => $horaFimPadrao,
                'precisa_transporte' => $precisaTransporte,
                'escopo_transporte' => ! $precisaTransporte
                    ? null
                    : ($usarTurmas
                        ? EventoCalendarioTransporteEscopo::TURMAS->value
                        : ($usarSeries
                            ? EventoCalendarioTransporteEscopo::SERIES->value
                            : EventoCalendarioTransporteEscopo::TODA_UNIDADE->value)),
                'series_ids' => $precisaTransporte && $usarSeries
                    ? $turmasDaEscola->pluck('id_serie')->map(fn ($id): int => (int) $id)->unique()->values()->all()
                    : [],
                'turmas_ids' => $precisaTransporte && $usarTurmas
                    ? $turmasDaEscola->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all()
                    : [],
            ];
        })->values()->all();

        return $this->normalizar($linhas, $ator, $horaInicioPadrao, $horaFimPadrao);
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
                'precisa_transporte' => (bool) $item->precisa_transporte,
                'escopo_transporte' => $item->escopo_transporte?->value,
                'quantidade_estimada_transporte' => $item->quantidade_estimada_transporte,
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

    /**
     * @param list<array<string, mixed>> $linhas
     * @return array<int, int>
     */
    private function contarEstudantesPorEscola(array $linhas, array $alunoExcecoes = []): array
    {
        $transportes = collect($linhas)->where('precisa_transporte', true)->values();

        if ($transportes->isEmpty()) {
            return [];
        }

        return Aluno::query()
            ->join('turmas', 'turmas.id', '=', 'alunos.id_turma')
            ->where('alunos.tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->where('alunos.status', Aluno::STATUS_MATRICULADO)
            ->when(
                $alunoExcecoes !== [],
                fn (Builder $query): Builder => $query->whereNotIn('alunos.id', collect($alunoExcecoes)->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique()->all()),
            )
            ->where(function (Builder $selecoes) use ($transportes): void {
                foreach ($transportes as $linha) {
                    $selecoes->orWhere(function (Builder $escola) use ($linha): void {
                        $escola->where('turmas.id_escola', (int) $linha['escola_id']);

                        if ($linha['escopo_transporte'] === EventoCalendarioTransporteEscopo::SERIES->value) {
                            $escola->whereIn('turmas.id_serie', $linha['series_ids']);
                        }

                        if ($linha['escopo_transporte'] === EventoCalendarioTransporteEscopo::TURMAS->value) {
                            $escola->whereIn('turmas.id', $linha['turmas_ids']);
                        }
                    });
                }
            })
            ->selectRaw('turmas.id_escola, COUNT(DISTINCT alunos.id) AS total')
            ->groupBy('turmas.id_escola')
            ->pluck('total', 'turmas.id_escola')
            ->map(fn ($total): int => (int) $total)
            ->all();
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
