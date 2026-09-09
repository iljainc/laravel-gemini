<?php

namespace Idpromogroup\LaravelGemini\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LgTemplate extends Model
{
    protected $table = 'lg_templates';

    protected static function boot()
    {
        parent::boot();

        static::created(function ($template) {
            $template->logState();
        });

        static::updated(function ($template) {
            $template->logState();
        });
    }

    protected $fillable = [
        'name',
        'instructions',
        'model',
        'tools',
        'temperature',
        'response_format',
        'json_schema',
        'api_key',
        'user_id',
    ];

    protected $casts = [
        'tools' => 'array',
        'json_schema' => 'array',
        'temperature' => 'float',
        'user_id' => 'integer',
    ];

    public function projectLogs(): HasMany
    {
        return $this->hasMany(LgTemplateLog::class, 'project_id');
    }

    public function templateFiles(): HasMany
    {
        return $this->hasMany(LgTemplateFile::class, 'template_id');
    }

    public function getApiKey(): string
    {
        return $this->api_key ?: (string) config('gemini.api_key');
    }

    public function logState(): void
    {
        LgTemplateLog::create([
            'project_id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'instructions' => $this->instructions,
            'model' => $this->model,
            'tools' => $this->tools,
            'temperature' => $this->temperature,
            'response_format' => $this->response_format,
            'json_schema' => $this->json_schema,
            'api_key' => $this->api_key,
        ]);
    }
}
