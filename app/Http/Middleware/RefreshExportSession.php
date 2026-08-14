<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Exports\ExportSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshExportSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        app(ExportSessionService::class)->touchCurrentSession(
            $request,
            $user instanceof User ? $user : null,
        );

        return $response;
    }
}
