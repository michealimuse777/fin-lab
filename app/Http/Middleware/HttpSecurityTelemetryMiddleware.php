<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpSecurityTelemetryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $endpoint = $this->endpoint($request);

        if ($this->isSensitiveFileProbe($endpoint)) {
            SecurityLog::warning('sensitive_file_probe', $request, $response->getStatusCode(), 'http_path', $endpoint, [
                'path' => $endpoint,
            ]);

            return $response;
        }

        if ($request->is('api/*') && $response->getStatusCode() === 404) {
            SecurityLog::warning('endpoint_enumeration', $request, 404, 'api_endpoint', $endpoint, [
                'path' => $endpoint,
            ]);
        }

        return $response;
    }

    private function isSensitiveFileProbe(string $endpoint): bool
    {
        $sensitivePaths = [
            '/.env',
            '/.git/config',
            '/config/.env',
            '/backup.zip',
            '/database.sql',
        ];

        return in_array($endpoint, $sensitivePaths, true);
    }

    private function endpoint(Request $request): string
    {
        $path = $request->path();

        if ($path === '/') {
            return '/';
        }

        return '/'.$path;
    }
}
