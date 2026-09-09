<?php

namespace Idpromogroup\LaravelGemini\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgTemplateLog extends Model
{
    protected $table = 'lg_template_logs';

    protected $fillable = [
        'project_id',
        'user_id',
        'name',
        'instructions',
        'model',
        'tools',
        'temperature',
        'response_format',
        'json_schema',
        'api_key',
    ];

    protected $casts = [
        'tools' => 'array',
        'json_schema' => 'array',
        'temperature' => 'float',
        'project_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(LgTemplate::class, 'project_id');
    }
}
