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
        if ($request->routeIs('profile-preview.start', 'profile-preview.stop')) {
            return $next($request);
        }

        $preview = app(ProfilePreviewService::class);

        if (! $preview->isActive()) {
            return $next($request);
        }

        $targetUser = $preview->targetUser();

        if (! $targetUser instanceof User) {
            return $next($request);
        }

        Auth::guard()->setUser($targetUser);
        $request->setUserResolver(fn (): User => $targetUser);

        return $next($request);
    }
}
