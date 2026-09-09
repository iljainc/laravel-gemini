<?php

namespace Idpromogroup\LaravelGemini;

use Idpromogroup\LaravelGemini\Contracts\LlmApiClient;
use Idpromogroup\LaravelGemini\Services\GeminiApiService;
use Idpromogroup\LaravelGemini\Services\UsageBillingService;
use Illuminate\Support\ServiceProvider;

class LaravelGeminiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gemini.php', 'gemini');

        $this->app->bind(LlmApiClient::class, function ($app, array $params = []) {
            $apiKey = $params['apiKey'] ?? config('gemini.api_key') ?: env('GEMINI_API_KEY');
            if (empty($apiKey)) {
                throw new \RuntimeException('Missing Gemini API key: set GEMINI_API_KEY in your .env');
            }

            return new GeminiApiService($apiKey, $params['timeout'] ?? null);
        });

        $this->app->singleton(UsageBillingService::class, static fn () => new UsageBillingService());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations/2026_09_09_000000_create_lg_tables.php' => database_path('migrations/2026_09_09_000000_create_lg_tables.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/../config/gemini.php' => config_path('gemini.php'),
        ], 'config');

        if (file_exists(__DIR__ . '/helpers.php')) {
            require __DIR__ . '/helpers.php';
        }
    }
}
