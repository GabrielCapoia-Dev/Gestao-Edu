<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JogosController extends Controller
{
    private const FILE = 'jogos.json';

    public function show(): JsonResponse
    {
        if (!Storage::disk('local')->exists(self::FILE)) {
            return response()->json(['data' => null]);
        }

        $data = json_decode(Storage::disk('local')->get(self::FILE), true, 512, JSON_THROW_ON_ERROR);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'config' => ['required', 'array'],
            'periodos' => ['required', 'array'],
            'dias' => ['required', 'array'],
            'quadras' => ['required', 'array'],
            'modalidades' => ['required', 'array'],
            'estacoes' => ['required', 'array'],
            'equipes' => ['required', 'array'],
            'partidas' => ['required', 'array'],
            'logs' => ['required', 'array'],
        ]);

        file_put_contents(
            storage_path('app/'.self::FILE),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            LOCK_EX,
        );

        return response()->json(['ok' => true]);
    }
}
