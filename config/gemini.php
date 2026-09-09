<?php

return [
    'debug_output' => env('GEMINI_DEBUG', true),

    'api_key' => env('GEMINI_API_KEY'),

    'default_model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),

    'timeout' => env('GEMINI_TIMEOUT', 60),

    'function_handler' => env(
        'GEMINI_FUNCTION_HANDLER',
        \App\Services\AppLogicService::class
    ),

    'billing' => [
        'enabled' => env('GEMINI_BILLING', true),
    ],

    /**
     * Standard paid, USD за 1M токенов (input / cached_input / output).
     * Источник: https://ai.google.dev/gemini-api/docs/pricing (2026-09-09).
     * Ключ — префикс response.model (матчится самый длинный).
     * 3.8 / 3.7 / 3.6 Flash: intro-цена до 2026-12-31, с 2027-01-01 ×2.
     */
    'prices' => [
        'gemini-3.8-flash' => ['input' => 0.75, 'cached_input' => 0.075, 'output' => 3.75],
        'gemini-3.7-flash' => ['input' => 0.75, 'cached_input' => 0.075, 'output' => 3.75],
        'gemini-3.6-flash' => ['input' => 0.75, 'cached_input' => 0.075, 'output' => 3.75],
        'gemini-3.5-flash-lite' => ['input' => 0.30, 'cached_input' => 0.03, 'output' => 2.50],
        'gemini-3.5-flash' => ['input' => 1.50, 'cached_input' => 0.15, 'output' => 9.00],
        'gemini-3.1-flash-lite-image' => ['input' => 0.25, 'cached_input' => 0.025, 'output' => 1.50],
        'gemini-3.1-flash-image' => ['input' => 0.50, 'cached_input' => 0.05, 'output' => 3.00],
        'gemini-3.1-flash-lite' => ['input' => 0.25, 'cached_input' => 0.025, 'output' => 1.50],
        'gemini-3.1-pro' => ['input' => 2.00, 'cached_input' => 0.20, 'output' => 12.00],
        'gemini-3-pro-image' => ['input' => 2.00, 'cached_input' => 0.20, 'output' => 12.00],
        'gemini-3-flash' => ['input' => 0.50, 'cached_input' => 0.05, 'output' => 3.00],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'cached_input' => 0.01, 'output' => 0.40],
        'gemini-2.5-flash' => ['input' => 0.30, 'cached_input' => 0.03, 'output' => 2.50],
        'gemini-2.5-pro' => ['input' => 1.25, 'cached_input' => 0.125, 'output' => 10.00],
    ],
];
