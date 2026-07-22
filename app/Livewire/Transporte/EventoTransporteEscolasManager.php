<?php

namespace App\Livewire\Transporte;

use App\Models\EventoCalendario;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioListQueryService;
use App\Services\Dashboard\EventoTransporteAlocacaoService;
use App\Services\ProfilePreviewService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class EventoTransporteEscolasManager extends Component
{
    public int $eventoId;

    /** @var array<int, list<int|string>> */
    public array $turmasSelecionadas = [];

    /** @var array<int, int|string|null> */
    public array $veiculosSelecionados = [];

    /** @var array<int, int|string|null> */
    public array $motoristasSelecionados = [];

    /** @var array<int, bool> */
    public array $permitirSuperlotacao = [];

    public bool $mostrarRelacaoVeiculos = false;

    private ?EventoCalendario $eventoResolvido = null;

    public function mount(int $eventoId): void
    {
        $this->eventoId = $eventoId;
        $this->preencherTurmasDisponiveis();
    }

    public function alocar(int $agendamentoId): void
    {
        abort_unless($this->podeGerenciar(), 403);

        $veiculoId = (int) ($this->veiculosSelecionados[$agendamentoId] ?? 0);
        $alocacaoExistente = EventoCalendarioTransporteAlocacao::query()
            ->ativas()
            ->where('evento_calendario_id', $this->eventoId)
            ->where('veiculo_transporte_id', $veiculoId)
            ->exists();

        $this->validate([
            "turmasSelecionadas.{$agendamentoId}" => ['required', 'array', 'min:1'],
            "veiculosSelecionados.{$agendamentoId}" => ['required', 'integer'],
            "motoristasSelecionados.{$agendamentoId}" => [$alocacaoExistente ? 'nullable' : 'required', 'integer'],
            "permitirSuperlotacao.{$agendamentoId}" => ['boolean'],
        ], [
            "turmasSelecionadas.{$agendamentoId}.required" => 'Selecione ao menos uma turma.',
            "veiculosSelecionados.{$agendamentoId}.required" => 'Selecione o veículo.',
            "motoristasSelecionados.{$agendamentoId}.required" => 'Selecione o motorista.',
        ]);

        $this->service()->atribuirTurmasDaEscola(
            $this->usuarioEfetivo(),
            $this->evento(),
            $agendamentoId,
            $veiculoId,
            filled($this->motoristasSelecionados[$agendamentoId] ?? null)
                ? (int) $this->motoristasSelecionados[$agendamentoId]
                : null,
            $this->turmasSelecionadas[$agendamentoId] ?? [],
            $this->superlotacaoNecessaria($agendamentoId)
                && (bool) ($this->permitirSuperlotacao[$agendamentoId] ?? false),
        );

        $this->eventoResolvido = null;
        $this->mostrarRelacaoVeiculos = true;

        unset(
            $this->turmasSelecionadas[$agendamentoId],
            $this->veiculosSelecionados[$agendamentoId],
            $this->motoristasSelecionados[$agendamentoId],
            $this->permitirSuperlotacao[$agendamentoId],
        );
        $this->preencherTurmasDisponiveis();

        Notification::make()->title('Transporte atribuído à escola')->success()->send();
    }

    public function remover(int $alocacaoId, int $agendamentoId): void
    {
        abort_unless($this->podeGerenciar(), 403);

        $alocacao = EventoCalendarioTransporteAlocacao::query()
            ->ativas()
            ->where('evento_calendario_id', $this->eventoId)
            ->findOrFail($alocacaoId);

        $this->service()->removerTurmasDaEscola($this->usuarioEfetivo(), $alocacao, $agendamentoId);
        $this->eventoResolvido = null;
        $this->preencherTurmasDisponiveis();

        Notification::make()->title('Transporte removido da escola')->success()->send();
    }

    public function alternarRelacaoVeiculos(): void
    {
        $this->mostrarRelacaoVeiculos = ! $this->mostrarRelacaoVeiculos;
    }

    public function updatedTurmasSelecionadas(mixed $value, int|string $agendamentoId): void
    {
        if (! $this->superlotacaoNecessaria((int) $agendamentoId)) {
            $this->permitirSuperlotacao[(int) $agendamentoId] = false;
        }
    }

    public function updatedVeiculosSelecionados(mixed $veiculoId, int|string $agendamentoId): void
    {
        if (! $veiculoId || ! $this->podeGerenciar()) {
            $this->turmasSelecionadas[(int) $agendamentoId] = [];

            return;
        }

        $evento = $this->evento();
        $agendamento = $evento->escolasAgendadas->firstWhere('id', (int) $agendamentoId);
        $alocacoes = $this->service()->queryAtivas($this->usuarioEfetivo(), $evento)->get();
        $turmasParticipantes = $this->service()->turmasParticipantes($this->usuarioEfetivo(), $evento);
        $alocacaoExistente = $alocacoes->firstWhere('veiculo_transporte_id', (int) $veiculoId);
        $veiculo = $alocacaoExistente?->veiculo
            ?? $this->service()->veiculosDisponiveis($this->usuarioEfetivo(), $evento)
                ->firstWhere('id', (int) $veiculoId);

        if (! $agendamento || ! $veiculo) {
            $this->turmasSelecionadas[(int) $agendamentoId] = [];

            return;
        }

        $ocupadas = $alocacoes->flatMap->turmas->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $turmas = $turmasParticipantes
            ->where('id_escola', $agendamento->escola_id)
            ->whereNotIn('id', $ocupadas)
            ->values();
        $ocupacaoAtual = $alocacaoExistente
            ? (int) $turmasParticipantes->whereIn('id', $alocacaoExistente->turmas->modelKeys())
                ->sum('estudantes_transporte_count')
            : 0;

        $this->turmasSelecionadas[(int) $agendamentoId] = $this->melhorCombinacaoDeTurmas(
            $turmas,
            max(0, (int) $veiculo->capacidade_passageiros - $ocupacaoAtual),
        );
        $this->permitirSuperlotacao[(int) $agendamentoId] = false;
    }

    public function render(): View
    {
        $evento = $this->evento();
        $user = $this->usuarioEfetivo();
        $turmas = $this->service()->turmasParticipantes($user, $evento);
        $alocacoes = $this->service()->queryAtivas($user, $evento)->get();
        $ocupadas = $alocacoes->flatMap->turmas->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $podeGerenciar = $this->podeGerenciar();
        $veiculosNovos = $podeGerenciar ? $this->service()->veiculosDisponiveis($user, $evento) : collect();
        $motoristas = $podeGerenciar ? $this->service()->motoristaOptions($user, $evento) : [];
        $veiculos = $veiculosNovos->map(function ($veiculo) {
            $veiculo->setAttribute('lugares_disponiveis', (int) $veiculo->capacidade_passageiros);
            $veiculo->setAttribute('ja_alocado', false);

            return $veiculo;
        });

        foreach ($alocacoes->unique('veiculo_transporte_id') as $alocacao) {
            $ocupacao = (int) $turmas->whereIn('id', $alocacao->turmas->modelKeys())
                ->sum('estudantes_transporte_count');
            $veiculo = $alocacao->veiculo;
            if (! $veiculo) {
                continue;
            }

            $veiculo->setAttribute('lugares_disponiveis', (int) $veiculo->capacidade_passageiros - $ocupacao);
            $veiculo->setAttribute('ja_alocado', true);
            $veiculos->push($veiculo);
        }

        $motoristasPorVeiculo = $alocacoes->mapWithKeys(fn ($alocacao): array => [
            (int) $alocacao->veiculo_transporte_id => $alocacao->motorista?->nome ?? 'Motorista não informado',
        ])->all();
        $relacaoVeiculos = $alocacoes->map(function ($alocacao) use ($turmas): array {
            $turmasDaRota = $turmas->whereIn('id', $alocacao->turmas->modelKeys());
            $total = (int) $turmasDaRota->sum('estudantes_transporte_count');
            $capacidade = (int) $alocacao->veiculo?->capacidade_passageiros;

            return [
                'alocacao' => $alocacao,
                'total' => $total,
                'capacidade' => $capacidade,
                'diferenca' => $capacidade - $total,
                'escolas' => $turmasDaRota->groupBy('id_escola')->map(function ($turmasEscola): array {
                    return [
                        'nome' => $turmasEscola->first()?->escola?->nome ?? 'Escola não informada',
                        'turmas' => $turmasEscola->map(fn ($turma): string => trim(
                            ($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome,
                        ))->values()->all(),
                        'alunos' => (int) $turmasEscola->sum('estudantes_transporte_count'),
                    ];
                })->values(),
            ];
        })->values();

        $escolas = $evento->escolasAgendadas
            ->map(function ($agendamento) use ($turmas, $alocacoes, $ocupadas, $veiculos): array {
                $turmasDaEscola = $turmas->where('id_escola', $agendamento->escola_id)->values();
                $disponiveis = $turmasDaEscola->whereNotIn('id', $ocupadas)->values();
                $selecionadas = collect($this->turmasSelecionadas[$agendamento->getKey()] ?? [])
                    ->map(fn ($id): int => (int) $id);
                $totalSelecionado = (int) $disponiveis
                    ->whereIn('id', $selecionadas)
                    ->sum('estudantes_transporte_count');
                $veiculoSelecionado = (int) ($this->veiculosSelecionados[$agendamento->getKey()] ?? 0);
                $veiculoAtual = $veiculos->firstWhere('id', $veiculoSelecionado);
                $necessitaSuperlotacao = $veiculoAtual
                    && $totalSelecionado > (int) $veiculoAtual->lugares_disponiveis;
                $aceitaSuperlotacao = $necessitaSuperlotacao
                    && (bool) ($this->permitirSuperlotacao[$agendamento->getKey()] ?? false);

                return [
                    'agendamento' => $agendamento,
                    'turmas' => $turmasDaEscola,
                    'disponiveis' => $disponiveis,
                    'alocacoes' => $alocacoes->filter(fn ($alocacao): bool => $alocacao->turmas
                        ->contains('id_escola', $agendamento->escola_id))->values(),
                    'total_selecionado' => $totalSelecionado,
                    'veiculos' => $veiculos
                        ->when(! $aceitaSuperlotacao, fn ($lista) => $lista
                            ->filter(fn ($veiculo): bool => (
                                (int) $veiculo->lugares_disponiveis > 0
                                && (int) $veiculo->lugares_disponiveis >= $totalSelecionado
                            ) || (int) $veiculo->getKey() === $veiculoSelecionado))
                        ->values(),
                    'necessita_superlotacao' => $necessitaSuperlotacao,
                ];
            })->values();

        return view('livewire.transporte.evento-transporte-escolas-manager', compact(
            'escolas',
            'motoristas',
            'motoristasPorVeiculo',
            'podeGerenciar',
            'relacaoVeiculos',
            'turmas',
        ));
    }

    private function preencherTurmasDisponiveis(): void
    {
        $evento = $this->evento();
        $turmas = $this->service()->turmasParticipantes($this->usuarioEfetivo(), $evento);
        $ocupadas = $this->service()->queryAtivas($this->usuarioEfetivo(), $evento)
            ->get()->flatMap->turmas->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($evento->escolasAgendadas->where('precisa_transporte', true) as $agendamento) {
            $this->turmasSelecionadas[$agendamento->getKey()] = [];
            $this->permitirSuperlotacao[$agendamento->getKey()] ??= false;
        }
    }

    private function evento(): EventoCalendario
    {
        if ($this->eventoResolvido instanceof EventoCalendario) {
            return $this->eventoResolvido;
        }

        $evento = app(EventoCalendarioListQueryService::class)->detalhes(
            $this->usuarioEfetivo(),
            $this->eventoId,
        );

        abort_unless($evento->possuiTransporte(), 404);

        return $this->eventoResolvido = $evento;
    }

    private function usuarioEfetivo(): User
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function podeGerenciar(): bool
    {
        return Gate::forUser($this->usuarioEfetivo())->allows('manageTransport', $this->evento());
    }

    private function service(): EventoTransporteAlocacaoService
    {
        return app(EventoTransporteAlocacaoService::class);
    }

    private function superlotacaoNecessaria(int $agendamentoId): bool
    {
        $veiculoId = (int) ($this->veiculosSelecionados[$agendamentoId] ?? 0);
        $turmaIds = collect($this->turmasSelecionadas[$agendamentoId] ?? [])
            ->map(fn ($id): int => (int) $id)->filter()->unique();

        if (! $veiculoId || $turmaIds->isEmpty()) {
            return false;
        }

        $evento = $this->evento();
        $turmas = $this->service()->turmasParticipantes($this->usuarioEfetivo(), $evento);
        $alocacao = $this->service()->queryAtivas($this->usuarioEfetivo(), $evento)
            ->where('veiculo_transporte_id', $veiculoId)
            ->first();
        $veiculo = $alocacao?->veiculo
            ?? $this->service()->veiculosDisponiveis($this->usuarioEfetivo(), $evento)->firstWhere('id', $veiculoId);

        if (! $veiculo) {
            return false;
        }

        $ocupacaoAtual = $alocacao
            ? (int) $turmas->whereIn('id', $alocacao->turmas->modelKeys())->sum('estudantes_transporte_count')
            : 0;
        $novaOcupacao = (int) $turmas->whereIn('id', $turmaIds)->sum('estudantes_transporte_count');

        return ($ocupacaoAtual + $novaOcupacao) > (int) $veiculo->capacidade_passageiros;
    }

    /**
     * Seleciona turmas inteiras e maximiza a ocupação sem ultrapassar a capacidade.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Turma> $turmas
     * @return list<int>
     */
    private function melhorCombinacaoDeTurmas($turmas, int $capacidade): array
    {
        $combinacoes = [0 => []];

        foreach ($turmas as $turma) {
            $quantidade = max(0, (int) $turma->estudantes_transporte_count);
            $atuais = $combinacoes;

            foreach ($atuais as $total => $ids) {
                $novoTotal = $total + $quantidade;
                if ($novoTotal <= $capacidade && ! array_key_exists($novoTotal, $combinacoes)) {
                    $combinacoes[$novoTotal] = [...$ids, (int) $turma->getKey()];
                }
            }
        }

        krsort($combinacoes);

        return array_values(reset($combinacoes) ?: []);
    }
}
