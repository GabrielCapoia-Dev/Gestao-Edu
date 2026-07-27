<?php

namespace App\Http\Controllers;

use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarExportService;
use App\Services\Dashboard\Calendar\CalendarNetworkEventLoader;
use App\Services\ProfilePreviewService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CalendarExportController extends Controller
{
    public function __invoke(
        Request $request,
        ProfilePreviewService $profilePreview,
        CalendarNetworkEventLoader $eventLoader,
        CalendarExportService $exporter,
    ): Response {
        $data = $request->validate([
            'formato' => ['required', Rule::in(['xlsx', 'pdf'])],
            'visualizacao' => ['required', Rule::in(['ano', 'mes', 'semana'])],
            'referencia' => ['required', 'date_format:Y-m-d'],
        ]);
        $user = $profilePreview->effectiveUser();

        abort_unless(
            $user instanceof User
                && $user->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label()),
            403,
        );

        $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));
        $referencia = CarbonImmutable::createFromFormat('!Y-m-d', $data['referencia'], $timezone);

        abort_unless($referencia !== false, 422);

        [$inicio, $fim] = $this->intervalo($referencia, $data['visualizacao']);
        $events = $eventLoader->load($user, $inicio, $fim)->events;

        return $data['formato'] === 'xlsx'
            ? $exporter->exportarXlsx($events, $inicio, $fim, $data['visualizacao'], $user)
            : $exporter->exportarPdf($events, $inicio, $fim, $data['visualizacao'], $user);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function intervalo(CarbonImmutable $referencia, string $visualizacao): array
    {
        return match ($visualizacao) {
            'ano' => [$referencia->startOfYear()->startOfDay(), $referencia->endOfYear()->endOfDay()],
            'semana' => [$referencia->startOfWeek()->startOfDay(), $referencia->endOfWeek()->endOfDay()],
            default => [$referencia->startOfMonth()->startOfDay(), $referencia->endOfMonth()->endOfDay()],
        };
    }
}
