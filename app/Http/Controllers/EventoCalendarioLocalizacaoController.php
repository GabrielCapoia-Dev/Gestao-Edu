<?php

namespace App\Http\Controllers;

use App\Models\EventoCalendario;
use App\Services\Dashboard\EventoCalendarioLocalizacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EventoCalendarioLocalizacaoController extends Controller
{
    public function buscar(Request $request, EventoCalendarioLocalizacaoService $localizacoes): JsonResponse
    {
        Gate::authorize('create', EventoCalendario::class);
        $dados = $request->validate(['q' => ['required', 'string', 'max:255'], 'somente_salvos' => ['nullable', 'boolean']]);
        return response()->json(['items' => $localizacoes->buscar($dados['q'], (bool) ($dados['somente_salvos'] ?? false))]);
    }

    public function reverter(Request $request, EventoCalendarioLocalizacaoService $localizacoes): JsonResponse
    {
        Gate::authorize('create', EventoCalendario::class);
        $dados = $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        return response()->json(['endereco' => $localizacoes->reverter((float) $dados['latitude'], (float) $dados['longitude'])]);
    }
}
