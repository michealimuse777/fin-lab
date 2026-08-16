<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Policies\TransactionPolicy;
use App\Support\SecurityLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Transaction::class, TransactionPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.'|'.$request->ip())->response(function (Request $request, array $headers) {
                SecurityLog::warning('rate_limit_triggered', $request, 429, 'endpoint', '/'.$request->path(), [
                    'limiter' => 'login',
                ]);

                return response()->json([
                    'message' => 'Too many requests',
                ], 429, $headers);
            });
        });

        RateLimiter::for('sensitive', function (Request $request) {
            $user = $request->user();

            if ($user === null) {
                $key = $request->ip();
            } else {
                $key = 'user-'.$user->id;
            }

            return Limit::perMinute(60)->by($key)->response(function (Request $request, array $headers) {
                SecurityLog::warning('rate_limit_triggered', $request, 429, 'endpoint', '/'.$request->path(), [
                    'limiter' => 'sensitive',
                ]);

                return response()->json([
                    'message' => 'Too many requests',
                ], 429, $headers);
            });
        });
    }
}
