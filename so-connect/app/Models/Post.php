<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $primaryKey = 'post_id';

    protected $fillable = [
        'organization',
        'title',
        'excerpt',
        'body',
        'tag',
        'image_path',
        'image_paths',
        'video_path',
        'is_featured',
        'published_at',
        'status',
    ];

    protected $casts = [
        'is_featured' => 'bool',
        'published_at' => 'datetime',
        'image_paths' => 'array',
    ];

    public function scopeWithPublicOrganization(Builder $query): Builder
    {
        return $query->whereHas('organizationOfPost', function (Builder $organization) {
            $organization->where(fn (Builder $status) => $status
                ->where('accreditation_status', '!=', \App\Services\AccreditationService::STATUS_DISABLED)
                ->orWhereNull('accreditation_status'));
        });
    }

    public function imagePaths(): array
    {
        return array_values(array_unique(array_filter([
            $this->image_path,
            ...($this->image_paths ?? []),
        ])));
    }

    /**
     * Image paths a post borrows from a shared library (accomplishment copies
     * or original form uploads). The post never owns these files, so they
     * must not be deleted along with it.
     */
    public const SHARED_MEDIA_PREFIXES = ['posts/media/accomplishment/', 'form-uploads/'];

    public static function isSharedMediaPath(?string $path): bool
    {
        foreach (self::SHARED_MEDIA_PREFIXES as $prefix) {
            if (str_starts_with((string) $path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function organizationOfPost()
    {
        return $this->belongsTo(Organization::class, 'organization', 'organization_id');
    }
}
