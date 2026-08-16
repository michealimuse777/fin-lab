<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityLog
{
    public static function info(string $event, Request $request, int $status, ?string $targetType = null, mixed $targetId = null, array $details = [], ?User $actor = null): void
    {
        self::write('info', $event, $request, $status, $targetType, $targetId, $details, $actor);
    }

    public static function warning(string $event, Request $request, int $status, ?string $targetType = null, mixed $targetId = null, array $details = [], ?User $actor = null): void
    {
        self::write('warning', $event, $request, $status, $targetType, $targetId, $details, $actor);
    }

    public static function error(string $event, Request $request, int $status, ?string $targetType = null, mixed $targetId = null, array $details = [], ?User $actor = null): void
    {
        self::write('error', $event, $request, $status, $targetType, $targetId, $details, $actor);
    }

    private static function write(string $level, string $event, Request $request, int $status, ?string $targetType, mixed $targetId, array $details, ?User $actor): void
    {
        if ($actor === null) {
            $actor = $request->user();
        }

        $context = [
            'schema_version' => 1,
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'http_method' => $request->method(),
            'endpoint' => self::endpoint($request),
            'http_status' => $status,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'request_id' => $request->headers->get('X-Request-Id'),
            'details' => $details,
        ];

        if ($level === 'error') {
            Log::channel('security')->error($event, $context);
            return;
        }

        if ($level === 'warning') {
            Log::channel('security')->warning($event, $context);
            return;
        }

        Log::channel('security')->info($event, $context);
    }

    private static function endpoint(Request $request): string
    {
        $path = $request->path();

        if ($path === '/') {
            return '/';
        }

        return '/'.$path;
    }
}
