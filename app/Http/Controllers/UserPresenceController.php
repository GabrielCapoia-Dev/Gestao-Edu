<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserPresenceController extends Controller
{
    public function heartbeat(Request $request, UserPresenceService $presence): JsonResponse
    {
        $start = microtime(true);

        $user = $request->user();

        if ($user instanceof User) {
            $presence->touch($user);
        }

        $elapsed = (microtime(true) - $start) * 1000;

        if ($elapsed > 500 && app()->isLocal()) {
            Log::warning('Heartbeat lento', [
                'user_id' => $user?->id,
                'elapsed_ms' => round($elapsed, 2),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
