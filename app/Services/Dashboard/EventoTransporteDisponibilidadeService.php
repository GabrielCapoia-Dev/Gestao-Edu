<?php

namespace App\Services\Dashboard;

use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\VeiculoTransporte;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventoTransporteDisponibilidadeService
{
    /**
     * @param list<int> $veiculoIds
     * @param list<int> $motoristaIds
     * @return array{veiculos: list<int>, motoristas: list<int>}
     */
    public function recursosIndisponiveis(
        CarbonInterface $inicio,
        CarbonInterface $fim,
        array $veiculoIds,
        array $motoristaIds,
        ?int $eventoIgnoradoId = null,
        bool $bloquear = false,
    ): array {
        $this->validarIntervalo($inicio, $fim);
        $veiculoIds = $this->ids($veiculoIds);
        $motoristaIds = $this->ids($motoristaIds);

        if ($veiculoIds === [] && $motoristaIds === []) {
            return ['veiculos' => [], 'motoristas' => []];
        }

        $query = EventoCalendarioTransporteAlocacao::query()
            ->ativas()
            ->whereHas('evento', function (Builder $eventos) use ($inicio, $fim): void {
                $eventos
                    ->whereNull('deleted_at')
                    ->whereIn('status', [
                        EventoCalendarioStatus::PENDENTE_APROVACAO->value,
                        EventoCalendarioStatus::PUBLICADO->value,
                    ])
                    ->where('data_inicio', '<', $fim)
                    ->where('data_fim', '>', $inicio);
            })
            ->where(function (Builder $recursos) use ($veiculoIds, $motoristaIds): void {
                if ($veiculoIds !== []) {
                    $recursos->whereIn('veiculo_transporte_id', $veiculoIds);
                }

                if ($motoristaIds !== []) {
                    $method = $veiculoIds === [] ? 'whereIn' : 'orWhereIn';
                    $recursos->{$method}('motorista_id', $motoristaIds);
                }
            })
            ->select(['veiculo_transporte_id', 'motorista_id']);

        if ($eventoIgnoradoId !== null) {
            $query->where('evento_calendario_id', '!=', $eventoIgnoradoId);
        }

        if ($bloquear) {
            $query->lockForUpdate();
        }

        /** @var Collection<int, EventoCalendarioTransporteAlocacao> $alocacoes */
        $alocacoes = $query->get();

        return [
            'veiculos' => $alocacoes->pluck('veiculo_transporte_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => in_array($id, $veiculoIds, true))
                ->unique()->values()->all(),
            'motoristas' => $alocacoes->pluck('motorista_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => in_array($id, $motoristaIds, true))
                ->unique()->values()->all(),
        ];
    }

    public function validarDisponibilidade(
        CarbonInterface $inicio,
        CarbonInterface $fim,
        array $veiculoIds,
        array $motoristaIds,
        ?int $eventoIgnoradoId = null,
        bool $bloquear = false,
    ): void {
        $ocupados = $this->recursosIndisponiveis(
            $inicio,
            $fim,
            $veiculoIds,
            $motoristaIds,
            $eventoIgnoradoId,
            $bloquear,
        );

        $erros = [];

        if ($ocupados['veiculos'] !== []) {
            $erros['veiculo'] = 'Um veículo selecionado já está reservado para este período.';
        }

        if ($ocupados['motoristas'] !== []) {
            $erros['motorista'] = 'Um motorista selecionado já está reservado para este período.';
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }
    }

    public function validarAlocacoesAtivasDoEvento(
        EventoCalendario $evento,
        ?CarbonInterface $inicio = null,
        ?CarbonInterface $fim = null,
        bool $bloquear = false,
    ): void {
        $alocacoes = $evento->alocacoesTransporteAtivas()
            ->select(['id', 'veiculo_transporte_id', 'motorista_id'])
            ->get();

        if ($alocacoes->isEmpty()) {
            return;
        }

        $veiculoIds = $alocacoes->pluck('veiculo_transporte_id')->all();
        $motoristaIds = $alocacoes->pluck('motorista_id')->all();

        if ($bloquear) {
            VeiculoTransporte::query()->whereKey($veiculoIds)->orderBy('id')->lockForUpdate()->get(['id']);
            Pessoa::query()->whereKey($motoristaIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }

        $haRecursoInativo = EventoCalendarioTransporteAlocacao::query()
            ->ativas()
            ->where('evento_calendario_id', $evento->getKey())
            ->where(function (Builder $recursos): void {
                $recursos
                    ->whereHas('veiculo', fn (Builder $veiculos): Builder => $veiculos->where('ativo', false))
                    ->orWhereHas('motorista', function (Builder $motoristas): void {
                        $motoristas
                            ->where('status', '!=', \App\Models\Pessoa::STATUS_ATIVO)
                            ->orWhereDoesntHave('servidorFuncoes', fn (Builder $vinculos): Builder => $vinculos
                                ->ativos()
                                ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes
                                    ->motorista()->where('ativo', true)));
                    });
            })
            ->exists();

        if ($haRecursoInativo) {
            throw ValidationException::withMessages([
                'transporte' => 'Uma alocação de transporte possui um recurso inativo.',
            ]);
        }
    }

    public function validarCoberturaCompletaDoEvento(EventoCalendario $evento): void
    {
        $evento->loadMissing([
            'escolasAgendadas.series:id',
            'escolasAgendadas.turmas:id',
        ]);
        $agendamentos = $evento->escolasAgendadas->where('precisa_transporte', true);

        if ($agendamentos->isEmpty()) {
            return;
        }

        $alocacoes = $evento->alocacoesTransporteAtivas()
            ->with('turmas:id')
            ->get(['id', 'evento_calendario_id', 'veiculo_transporte_id', 'motorista_id']);

        if ($alocacoes->isEmpty()) {
            throw ValidationException::withMessages([
                'transporte' => 'Atribua os veículos e motoristas antes de publicar o evento.',
            ]);
        }

        $turmasEsperadas = Turma::query()
            ->where(function (Builder $selecoes) use ($agendamentos): void {
                foreach ($agendamentos as $agendamento) {
                    $selecoes->orWhere(function (Builder $turmas) use ($agendamento): void {
                        $turmas->where('id_escola', $agendamento->escola_id);

                        if ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::SERIES) {
                            $turmas->whereIn('id_serie', $agendamento->series->modelKeys());
                        }

                        if ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::TURMAS) {
                            $turmas->whereKey($agendamento->turmas->modelKeys());
                        }
                    });
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique();
        $turmasAlocadas = $alocacoes->flatMap->turmas
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique();
        $pendentes = $turmasEsperadas->diff($turmasAlocadas);

        if ($pendentes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'transporte' => sprintf(
                    'Ainda existem %d turma(s) sem veículo e motorista atribuídos.',
                    $pendentes->count(),
                ),
            ]);
        }
    }

    /** @param array<mixed> $ids @return list<int> */
    private function ids(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()->values()->all();
    }

    private function validarIntervalo(CarbonInterface $inicio, CarbonInterface $fim): void
    {
        if ($fim->lte($inicio)) {
            throw ValidationException::withMessages([
                'periodo' => 'O período da alocação de transporte é inválido.',
            ]);
        }
    }
}
