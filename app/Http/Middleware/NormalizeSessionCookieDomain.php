<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeSessionCookieDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredDomain = config('session.domain');

        if (is_string($configuredDomain) && $configuredDomain !== '' && ! $this->domainMatchesHost($configuredDomain, $request->getHost())) {
            config(['session.domain' => null]);
        }

        return $next($request);
    }

    private function domainMatchesHost(string $configuredDomain, string $host): bool
    {
        $domain = strtolower(ltrim(trim($configuredDomain), '.'));
        $host = strtolower($host);

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
