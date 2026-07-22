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
        );

        $this->eventoResolvido = null;
        $this->mostrarRelacaoVeiculos = true;

        unset(
            $this->turmasSelecionadas[$agendamentoId],
            $this->veiculosSelecionados[$agendamentoId],
            $this->motoristasSelecionados[$agendamentoId],
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

    public function updatedVeiculosSelecionados(mixed $veiculoId, int|string $agendamentoId): void
    {
        if (! $veiculoId || ! $this->podeGerenciar()) {
            $this->turmasSelecionadas[(int) $agendamentoId] = [];
            unset($this->motoristasSelecionados[(int) $agendamentoId]);

            return;
        }

        $evento = $this->evento();
        $agendamento = $evento->escolasAgendadas->firstWhere('id', (int) $agendamentoId);
        $alocacoes = $this->service()->queryAtivas($this->usuarioEfetivo(), $evento)->get();
        $alocacaoExistente = $alocacoes->firstWhere('veiculo_transporte_id', (int) $veiculoId);
        $veiculo = $alocacaoExistente?->veiculo
            ?? $this->service()->veiculosDisponiveis($this->usuarioEfetivo(), $evento)
                ->firstWhere('id', (int) $veiculoId);

        if (! $agendamento || ! $veiculo) {
            $this->turmasSelecionadas[(int) $agendamentoId] = [];
            unset($this->motoristasSelecionados[(int) $agendamentoId]);

            return;
        }

        if ($alocacaoExistente) {
            $this->motoristasSelecionados[(int) $agendamentoId] = (int) $alocacaoExistente->motorista_id;
        } else {
            unset($this->motoristasSelecionados[(int) $agendamentoId]);
        }

        $this->turmasSelecionadas[(int) $agendamentoId] ??= [];
    }

    public function render(): View
    {
        $evento = $this->evento();
        $user = $this->usuarioEfetivo();
        $turmas = $this->service()->turmasParticipantes($user, $evento);
        $alocacoes = $this->service()->queryAtivas($user, $evento)->get();
        $podeGerenciar = $this->podeGerenciar();
        $veiculosNovos = $podeGerenciar ? $this->service()->veiculosDisponiveis($user, $evento) : collect();
        $motoristas = $podeGerenciar ? $this->service()->motoristaOptions($user, $evento) : [];
        $veiculos = $veiculosNovos->mapWithKeys(function ($veiculo): array {
            $veiculo->setAttribute('lugares_disponiveis', (int) $veiculo->capacidade_passageiros);
            $veiculo->setAttribute('ja_alocado', false);

            return [(int) $veiculo->getKey() => $veiculo];
        });

        foreach ($alocacoes->unique('veiculo_transporte_id') as $alocacao) {
            $ocupacao = (int) $turmas->whereIn('id', $alocacao->turmas->modelKeys())
                ->sum('estudantes_transporte_count');
            $veiculo = $veiculos->get((int) $alocacao->veiculo_transporte_id) ?? $alocacao->veiculo;
            if (! $veiculo) {
                continue;
            }

            $veiculo->setAttribute('lugares_disponiveis', (int) $veiculo->capacidade_passageiros - $ocupacao);
            $veiculo->setAttribute('ja_alocado', true);
            $veiculos->put((int) $veiculo->getKey(), $veiculo);
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
            ->map(function ($agendamento) use ($turmas, $alocacoes, $veiculos): array {
                $turmasDaEscola = $turmas->where('id_escola', $agendamento->escola_id)->values();
                $disponiveis = $turmasDaEscola;
                $selecionadas = collect($this->turmasSelecionadas[$agendamento->getKey()] ?? [])
                    ->map(fn ($id): int => (int) $id);
                $totalSelecionado = (int) $disponiveis
                    ->whereIn('id', $selecionadas)
                    ->sum('estudantes_transporte_count');
                return [
                    'agendamento' => $agendamento,
                    'turmas' => $turmasDaEscola,
                    'disponiveis' => $disponiveis,
                    'alocacoes' => $alocacoes->filter(fn ($alocacao): bool => $alocacao->turmas
                        ->contains('id_escola', $agendamento->escola_id))->values(),
                    'total_selecionado' => $totalSelecionado,
                    'veiculos' => $veiculos->values(),
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
        $alocacoes = $this->service()->queryAtivas($this->usuarioEfetivo(), $evento)->get();

        if ($alocacoes->isNotEmpty()) {
            $this->mostrarRelacaoVeiculos = true;
        }

        foreach ($evento->escolasAgendadas->where('precisa_transporte', true) as $agendamento) {
            $agendamentoId = (int) $agendamento->getKey();
            $alocacaoDaEscola = $alocacoes->first(
                fn (EventoCalendarioTransporteAlocacao $alocacao): bool => $alocacao->turmas
                    ->contains('id_escola', $agendamento->escola_id),
            );

            $this->turmasSelecionadas[$agendamentoId] = [];

            if ($alocacaoDaEscola) {
                $this->veiculosSelecionados[$agendamentoId] = (int) $alocacaoDaEscola->veiculo_transporte_id;
                $this->motoristasSelecionados[$agendamentoId] = (int) $alocacaoDaEscola->motorista_id;

                continue;
            }

            unset(
                $this->veiculosSelecionados[$agendamentoId],
                $this->motoristasSelecionados[$agendamentoId],
            );
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

}
