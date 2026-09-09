<?php

namespace Idpromogroup\LaravelGemini\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgTemplateFile extends Model
{
    protected $table = 'lg_template_files';

    protected $fillable = [
        'template_id',
        'file_url',
        'file_name',
        'file_type',
        'file_size',
        'file_hash',
        'mime_type',
        'gemini_file_uri',
        'upload_status',
        'error_message',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_UPLOADING = 'uploading';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function template(): BelongsTo
    {
        return $this->belongsTo(LgTemplate::class, 'template_id');
    }
}
