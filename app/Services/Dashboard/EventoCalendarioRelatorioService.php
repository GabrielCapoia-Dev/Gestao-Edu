<?php

namespace App\Services\Dashboard;

use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Relatorios\RelatorioPdfRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EventoCalendarioRelatorioService
{
    public function __construct(
        private readonly EventoCalendarioListQueryService $listagem,
        private readonly EventoTransporteAlocacaoService $transportes,
        private readonly RelatorioPdfRenderer $pdf,
    ) {}

    public function download(User $user, EventoCalendario $evento): Response
    {
        $evento = $this->listagem->detalhes($user, (int) $evento->getKey());
        Gate::forUser($user)->authorize('report', $evento);

        $turmas = $evento->possuiTransporte()
            ? $this->transportes->turmasParticipantes($user, $evento)
            : collect();

        return $this->pdf->download(
            'relatorios.evento-calendario',
            [
                'evento' => $evento,
                'turmasPorEscola' => $turmas->groupBy('id_escola'),
                'reportTitle' => 'Relatório do evento',
                'reportSubtitle' => $evento->titulo,
                'usuarioExportacao' => $user,
                'orientation' => 'landscape',
            ],
            'evento-'.Str::slug($evento->titulo).'-'.$evento->getKey().'.pdf',
        );
    }
}
