<?php

if (! function_exists('lg_debug')) {
    function lg_debug(...$vars)
    {
        if (config('gemini.debug_output')) {
            if (\Illuminate\Support\Facades\App::runningInConsole()) {
                foreach ($vars as $var) {
                    echo print_r($var, true) . PHP_EOL;
                }
            } else {
                foreach ($vars as $var) {
                    var_dump($var);
                }
            }
        }
    }
}

if (!function_exists('lg_debug_error')) {
    function lg_debug_error(...$vars)
    {
        if (config('gemini.debug_output')) {
            if (\Illuminate\Support\Facades\App::runningInConsole()) {
                foreach ($vars as $var) {
                    echo "\033[31m" . print_r($var, true) . "\033[0m" . PHP_EOL;
                }
            } else {
                echo '<span style="color:red">';
                foreach ($vars as $var) {
                    var_dump($var);
                }
                echo '</span>';
            }
        }
    }
}
