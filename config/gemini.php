<?php

return [
    'debug_output' => env('GEMINI_DEBUG', true),

    'api_key' => env('GEMINI_API_KEY'),

    'default_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),

    'timeout' => env('GEMINI_TIMEOUT', 60),

    'function_handler' => env(
        'GEMINI_FUNCTION_HANDLER',
        \App\Services\AppLogicService::class
    ),

    'billing' => [
        'enabled' => env('GEMINI_BILLING', true),
    ],

    /**
     * USD за 1M токенов (input / cached_input / output).
     * Ключ — префикс response.model.
     */
    'prices' => [
        'gemini-2.5-pro' => ['input' => 1.25, 'cached_input' => 0.125, 'output' => 10.00],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'cached_input' => 0.01, 'output' => 0.40],
        'gemini-2.5-flash' => ['input' => 0.30, 'cached_input' => 0.03, 'output' => 2.50],
        'gemini-2.0-flash-lite' => ['input' => 0.075, 'cached_input' => 0.01875, 'output' => 0.30],
        'gemini-2.0-flash' => ['input' => 0.10, 'cached_input' => 0.025, 'output' => 0.40],
        'gemini-3-flash' => ['input' => 0.50, 'cached_input' => 0.05, 'output' => 3.00],
        'gemini-3-pro' => ['input' => 2.00, 'cached_input' => 0.20, 'output' => 12.00],
    ],
];
