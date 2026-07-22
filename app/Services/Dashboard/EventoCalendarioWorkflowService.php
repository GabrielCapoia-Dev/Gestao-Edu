<?php

namespace App\Services\Dashboard;

use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioHistorico;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventoCalendarioWorkflowService
{
    public function __construct(
        private readonly EventoTransporteDisponibilidadeService $disponibilidade,
    ) {}

    public function publicar(EventoCalendario $evento, User $ator): EventoCalendario
    {
        return DB::transaction(function () use ($evento, $ator): EventoCalendario {
            $evento = $this->bloquear($evento);
            Gate::forUser($ator)->authorize('publish', $evento);

            if ($evento->possuiTransporte()) {
                if ($evento->status === EventoCalendarioStatus::REJEITADO) {
                    throw ValidationException::withMessages([
                        'evento' => 'Edite o evento rejeitado para reenviá-lo à análise antes de publicar.',
                    ]);
                }

                $this->disponibilidade->validarCoberturaCompletaDoEvento($evento);
                $this->disponibilidade->validarAlocacoesAtivasDoEvento($evento, bloquear: true);
            }

            if ($evento->status === EventoCalendarioStatus::PUBLICADO && $evento->ativo) {
                return $evento;
            }

            $anterior = $evento->status;
            $aprovacaoTransporte = $evento->possuiTransporte()
                && $anterior === EventoCalendarioStatus::PENDENTE_APROVACAO;
            $acao = match (true) {
                $aprovacaoTransporte => EventoCalendarioHistoricoAcao::APROVADO,
                $this->jaFoiPublicado($evento) => EventoCalendarioHistoricoAcao::REPUBLICADO,
                default => EventoCalendarioHistoricoAcao::PUBLICADO,
            };

            $this->atualizarEstado($evento, EventoCalendarioStatus::PUBLICADO, $ator);
            $this->registrar($evento, $ator, $acao, $anterior, EventoCalendarioStatus::PUBLICADO);

            return $evento->refresh();
        });
    }

    public function desativar(EventoCalendario $evento, User $ator): EventoCalendario
    {
        return DB::transaction(function () use ($evento, $ator): EventoCalendario {
            $evento = $this->bloquear($evento);
            Gate::forUser($ator)->authorize('deactivate', $evento);

            if ($evento->status !== EventoCalendarioStatus::PUBLICADO || ! $evento->ativo) {
                throw ValidationException::withMessages([
                    'evento' => 'Somente eventos publicados podem ser desativados.',
                ]);
            }

            $anterior = $evento->status;
            $this->atualizarEstado($evento, EventoCalendarioStatus::INATIVO, $ator);
            $this->registrar(
                $evento,
                $ator,
                EventoCalendarioHistoricoAcao::DESATIVADO,
                $anterior,
                EventoCalendarioStatus::INATIVO,
            );

            return $evento->refresh();
        });
    }

    public function rejeitar(EventoCalendario $evento, User $ator, ?string $motivo = null): EventoCalendario
    {
        return DB::transaction(function () use ($evento, $ator, $motivo): EventoCalendario {
            $evento = $this->bloquear($evento);
            Gate::forUser($ator)->authorize('reject', $evento);

            if (! in_array($evento->status, [
                EventoCalendarioStatus::PENDENTE_APROVACAO,
                EventoCalendarioStatus::PUBLICADO,
            ], true)) {
                throw ValidationException::withMessages([
                    'evento' => 'Somente eventos de transporte pendentes ou publicados podem ser rejeitados.',
                ]);
            }

            $motivo = $this->normalizarMotivo($motivo);
            $anterior = $evento->status;
            $this->atualizarEstado($evento, EventoCalendarioStatus::REJEITADO, $ator);
            $this->registrar(
                $evento,
                $ator,
                EventoCalendarioHistoricoAcao::REJEITADO,
                $anterior,
                EventoCalendarioStatus::REJEITADO,
                $motivo,
            );

            return $evento->refresh();
        });
    }

    public function registrarCriacao(EventoCalendario $evento, User $ator): void
    {
        $this->registrar(
            $evento,
            $ator,
            EventoCalendarioHistoricoAcao::CRIADO,
            null,
            $evento->status,
        );
    }

    public function registrarAtualizacao(
        EventoCalendario $evento,
        User $ator,
        EventoCalendarioStatus $statusAnterior,
        bool $transporteAlterado = false,
    ): void {
        $this->registrar(
            $evento,
            $ator,
            $transporteAlterado && $evento->status === EventoCalendarioStatus::PENDENTE_APROVACAO
                ? EventoCalendarioHistoricoAcao::REENVIADO_ANALISE
                : EventoCalendarioHistoricoAcao::ATUALIZADO,
            $statusAnterior,
            $evento->status,
        );
    }

    public function registrarTransporte(
        EventoCalendario $evento,
        User $ator,
        EventoCalendarioHistoricoAcao $acao,
        ?string $motivo = null,
    ): void {
        $this->registrar(
            $evento,
            $ator,
            $acao,
            $evento->status,
            $evento->status,
            $motivo,
        );
    }

    private function bloquear(EventoCalendario $evento): EventoCalendario
    {
        return EventoCalendario::query()
            ->with([
                'escolasAgendadas:id,evento_calendario_id,escola_id,precisa_transporte,escopo_transporte',
                'escolasAgendadas.series:id',
                'escolasAgendadas.turmas:id',
            ])
            ->lockForUpdate()
            ->findOrFail($evento->getKey());
    }

    private function jaFoiPublicado(EventoCalendario $evento): bool
    {
        return $evento->historicos()
            ->where('status_novo', EventoCalendarioStatus::PUBLICADO->value)
            ->exists();
    }

    private function atualizarEstado(
        EventoCalendario $evento,
        EventoCalendarioStatus $status,
        User $ator,
    ): void {
        $evento->forceFill([
            'status' => $status,
            'ativo' => $status === EventoCalendarioStatus::PUBLICADO,
            'atualizado_por_id' => $ator->getKey(),
        ])->save();
    }

    private function registrar(
        EventoCalendario $evento,
        User $ator,
        EventoCalendarioHistoricoAcao $acao,
        ?EventoCalendarioStatus $anterior,
        EventoCalendarioStatus $novo,
        ?string $motivo = null,
    ): void {
        EventoCalendarioHistorico::query()->create([
            'evento_calendario_id' => $evento->getKey(),
            'usuario_id' => $ator->getKey(),
            'acao' => $acao,
            'status_anterior' => $anterior,
            'status_novo' => $novo,
            'motivo' => $motivo,
        ]);
    }

    private function normalizarMotivo(?string $motivo): ?string
    {
        $motivo = trim((string) $motivo);

        if (mb_strlen($motivo) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'O motivo deve possuir no máximo 1.000 caracteres.',
            ]);
        }

        return $motivo !== '' ? $motivo : null;
    }
}
