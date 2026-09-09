<?php

namespace Idpromogroup\LaravelGemini\Facades;

use Illuminate\Support\Facades\Facade;

class Lg extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Idpromogroup\LaravelGemini\LgService::class;
    }

    public static function usage(): \Illuminate\Database\Eloquent\Builder
    {
        return \Idpromogroup\LaravelGemini\Models\LgRequestLog::query();
    }
}
