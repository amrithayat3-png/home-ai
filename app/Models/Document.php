<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'file_hash',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'classified_at' => 'datetime',
        ];
    }

    /**
     * Accounts limited to some sections only see documents linked to a matter in those sections,
     * plus the documents they uploaded themselves. Administrators and unrestricted accounts see everything.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('visible', function (Builder $builder) {
            $sections = Matter::restrictedSections();

            if ($sections === null) {
                return;
            }

            $userId = auth()->id();

            $builder->where(function (Builder $query) use ($sections, $userId) {
                $query->whereIn('documents.matter_id', Matter::withoutGlobalScopes()->whereIn('section', $sections)->select('id'))
                    ->orWhere('documents.uploaded_by', $userId);
            });
        });
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
