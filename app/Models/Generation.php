<?php

namespace App\Models;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pixelforge_id',
        'idempotency_key',
        'status',
        'prompt',
        'size',
        'quality',
        'reference_images',
        'metadata',
        'image_url',
        'image_url_expires_at',
        'estimated_cost',
        'actual_cost',
        'provider_cost',
        'rates',
        'usage',
        'error_code',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => GenerationSize::class,
            'quality' => GenerationQuality::class,
            'reference_images' => 'array',
            'metadata' => 'array',
            'image_url_expires_at' => 'datetime',
            'rates' => 'array',
            'usage' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Determine if the signed image URL has expired.
     */
    public function isImageExpired(): bool
    {
        return $this->image_url_expires_at !== null && $this->image_url_expires_at->isPast();
    }

    /**
     * The user that owns the generation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
