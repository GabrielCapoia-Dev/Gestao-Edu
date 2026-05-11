<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPresenceController extends Controller
{
    public function heartbeat(Request $request, UserPresenceService $presence): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $presence->touch($user);
        }

        return response()->json(['ok' => true]);
    }
}
