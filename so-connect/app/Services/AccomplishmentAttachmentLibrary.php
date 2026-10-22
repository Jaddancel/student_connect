<?php

namespace App\Services;

use App\Models\AccomplishmentMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class AccomplishmentAttachmentLibrary
{
    /** @return array<int,array<string,mixed>> */
    public function itemsForUser(?User $user, int $limit = 150): array
    {
        if (! $user) {
            return [];
        }

        $query = AccomplishmentMedia::query()->orderByDesc('submitted_at')->orderByDesc('id');
        $this->scopeForUser($query, $user);
        $disk = Storage::disk((string) config('documents.disk', 'public'));

        return $query->limit($limit)->get()
            ->filter(fn (AccomplishmentMedia $media) => $disk->exists((string) $media->file_path))
            ->map(fn (AccomplishmentMedia $media) => [
            'id' => (int) $media->getKey(),
            'path' => (string) $media->file_path,
            'url' => $disk->url((string) $media->file_path),
            'title' => (string) ($media->activity_title ?: $media->original_name ?: basename((string) $media->file_path)),
            'event_id' => $media->event_id ? (int) $media->event_id : null,
            'organization_id' => (int) $media->organization_id,
            'tags' => array_values((array) ($media->tags ?? [])),
            'date' => optional($media->submitted_at)->format('M j, Y'),
            'name' => (string) ($media->original_name ?: basename((string) $media->file_path)),
            ])->values()->all();
    }

    public function allows(?User $user, string $path): bool
    {
        if (! $user || trim($path) === '') {
            return false;
        }

        $query = AccomplishmentMedia::query()->where('file_path', $path);
        $this->scopeForUser($query, $user);

        return $query->exists()
            && Storage::disk((string) config('documents.disk', 'public'))->exists($path);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ((int) $user->user_type === 2 || (int) $user->user_type === 1) {
            return $query;
        }

        $organizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

        return $query->whereIn('organization_id', $organizationIds ?: [0]);
    }
}
