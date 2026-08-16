<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\HttpSecurityTelemetryMiddleware;
use App\Support\SecurityLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(HttpSecurityTelemetryMiddleware::class);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(function (Throwable $exception): void {
            $request = request();

            if (! $request instanceof Request) {
                return;
            }

            if (! $request->is('api/*')) {
                return;
            }

            if ($exception instanceof ValidationException) {
                return;
            }

            if ($exception instanceof AuthenticationException) {
                return;
            }

            if ($exception instanceof AuthorizationException) {
                return;
            }

            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return;
            }

            SecurityLog::error('application_error', $request, 500, 'application', null, [
                'exception_class' => get_class($exception),
            ]);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            SecurityLog::warning('validation_failed', $request, 422, 'request', null, [
                'fields' => array_keys($exception->errors()),
            ]);

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return null;
            }

            if ($exception instanceof AuthenticationException) {
                return null;
            }

            if ($exception instanceof AuthorizationException) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                return null;
            }

            return response()->json([
                'message' => 'Internal server error',
            ], 500);
        });
    })->create();
