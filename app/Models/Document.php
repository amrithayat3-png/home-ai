<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'original_name',
        'stored_path',
        'mime_type',
        'file_size',
        'category',
        'confidence',
        'classification_reason',
        'extracted_text',
        'review_status',
        'classified_at',
        'matter_id',
        'suggested_matter_id',
        'matter_link_source',
    ];

    protected function casts(): array
    {
        return [
            'classified_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class, 'matter_id');
    }

    public function suggestedMatter(): BelongsTo
    {
        return $this->belongsTo(Matter::class, 'suggested_matter_id');
    }
}
