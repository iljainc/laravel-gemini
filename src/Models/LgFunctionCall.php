<?php

namespace Idpromogroup\LaravelGemini\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgFunctionCall extends Model
{
    protected $table = 'lg_function_calls';

    protected $fillable = [
        'request_log_id',
        'external_key',
        'function_name',
        'arguments',
        'output',
        'status',
        'error_message',
        'execution_time',
    ];

    protected $casts = [
        'arguments' => 'array',
        'output' => 'array',
        'execution_time' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    public function requestLog(): BelongsTo
    {
        return $this->belongsTo(LgRequestLog::class, 'request_log_id');
    }
}
