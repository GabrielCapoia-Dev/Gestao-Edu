<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ProfilePreviewService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyProfilePreviewUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $preview = app(ProfilePreviewService::class);

        if (! $preview->targetUserId()) {
            return $next($request);
        }

        $realUser = $preview->realUser();
        $targetUser = $preview->targetUser();

        if (! $realUser instanceof User || ! $targetUser instanceof User) {
            $preview->stop();

            return $next($request);
        }

        Auth::setUser($targetUser);

        try {
            return $next($request);
        } finally {
            Auth::setUser($realUser);
        }
    }
}
