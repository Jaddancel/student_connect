<?php

namespace App\Http\Controllers;

use App\Models\AccomplishmentMedia;
use App\Services\AccomplishmentAttachmentLibrary;
use App\Services\ActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttachmentLibraryController extends Controller
{
    public function index(Request $request, AccomplishmentAttachmentLibrary $library): View
    {
        $user = $request->user();
        abort_unless($user && in_array((int) $user->user_type, [1, 2, 3], true), 403);

        $query = AccomplishmentMedia::query()->with('organization.detail');
        $library->scopeForUser($query, $user);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('activity_title', 'like', '%'.$search.'%')
                ->orWhere('original_name', 'like', '%'.$search.'%')
                ->orWhere('tags', 'like', '%'.$search.'%'));
        }

        $tag = trim((string) $request->query('tag', ''));
        if ($tag !== '') {
            $query->where('tags', 'like', '%"'.$tag.'"%');
        }

        $tags = (clone $query)->get(['tags'])->flatMap(fn ($item) => (array) $item->tags)
            ->filter()->unique()->sort()->values();

        $media = $query->orderByDesc('submitted_at')->orderByDesc('id')
            ->paginate(24)->withQueryString();

        return view('pages.attachment-library.index', compact('media', 'tags', 'search', 'tag'))
            ->with('title', 'Attachment Library');
    }

    public function update(Request $request, AccomplishmentMedia $media, AccomplishmentAttachmentLibrary $library): RedirectResponse
    {
        abort_unless($library->allows($request->user(), (string) $media->file_path), 403);

        $validated = $request->validate([
            'activity_title' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $tags = collect(explode(',', (string) ($validated['tags'] ?? '')))
            ->map(fn ($value) => strtolower(trim($value)))
            ->filter()->unique()->take(20)->values()->all();

        $media->update(['activity_title' => $validated['activity_title'], 'tags' => $tags]);

        ActionLogger::log(
            ActionLogger::CATEGORY_REPORTS,
            'accomplishment_attachment_metadata_updated',
            'Updated attachment metadata for '.$validated['activity_title'],
            ['tags' => $tags, 'organization_id' => (int) $media->organization_id],
            $media,
        );

        return back()->with('success', 'Attachment tags updated.');
    }
}
