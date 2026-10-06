<?php

namespace App\Http\Controllers;

use App\Models\AccomplishmentMedia;
use App\Models\Post;
use App\Services\AfterEventMediaGallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function index(Request $request, AfterEventMediaGallery $gallery)
    {
        // Load all organizations (admin sees everything)
        $orgs = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as org_name"), 'od.initials')
            ->orderBy('od.name')
            ->get();

        $orgIds = $orgs->pluck('organization_id');

        $query = Post::query()->with('organizationOfPost.detail');

        if ($request->filled('org') && $request->org !== 'all') {
            $query->where('organization', (int) $request->org);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $posts = $query->orderByDesc('created_at')->paginate(12)->appends($request->only(['org', 'search']));

        // Load accomplishment media library for the image picker
        $accomplishmentMedia = AccomplishmentMedia::query()
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('pages.posts.index', [
            'posts'               => $posts,
            'orgs'                => $orgs,
            'filterOrg'           => $request->org ?? 'all',
            'search'              => $request->search ?? '',
            'accomplishmentMedia' => $accomplishmentMedia,
            'afterEventMediaGallery'    => $gallery->items(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organization'       => ['required', 'integer', 'min:1'],
            'title'              => ['required', 'string', 'max:255'],
            'excerpt'            => ['required', 'string', 'max:280'],
            'body'               => ['nullable', 'string'],
            'tag'                => ['nullable', 'string', 'max:60'],
            'is_featured'        => ['nullable', 'boolean'],
            ...$this->mediaRules(null),
        ]);
        $this->ensureImageLimit($request, $data);

        $post = new Post;
        $post->organization = $data['organization'];
        $post->title        = $data['title'];
        $post->excerpt      = $data['excerpt'];
        $post->body         = $data['body'] ?? null;
        $post->tag          = $data['tag'] ?? null;
        $post->status       = 'published';
        $post->is_featured  = $request->boolean('is_featured');
        $post->published_at = now();

        $this->replaceMedia($post, $request, $data);

        $post->save();

        return redirect()->route('posts.index')->with('success', 'Post created successfully.');
    }

    public function update(Request $request, int $postId)
    {
        $post = Post::where('post_id', $postId)->firstOrFail();

        $data = $request->validate([
            'organization'       => ['required', 'integer', 'min:1'],
            'title'              => ['required', 'string', 'max:255'],
            'excerpt'            => ['required', 'string', 'max:280'],
            'body'               => ['nullable', 'string'],
            'tag'                => ['nullable', 'string', 'max:60'],
            'is_featured'        => ['nullable', 'boolean'],
            ...$this->mediaRules($post),
        ]);
        $this->ensureImageLimit($request, $data);

        $post->organization = $data['organization'];
        $post->title        = $data['title'];
        $post->excerpt      = $data['excerpt'];
        $post->body         = $data['body'] ?? null;
        $post->tag          = $data['tag'] ?? null;
        $post->status       = 'published';
        $post->is_featured  = $request->boolean('is_featured');

        if (! $post->published_at) {
            $post->published_at = now();
        }

        $this->replaceMedia($post, $request, $data);

        $post->save();

        return redirect()->route('posts.index')->with('success', 'Post updated successfully.');
    }

    public function destroy(int $postId)
    {
        $post = Post::where('post_id', $postId)->firstOrFail();

        foreach ($post->imagePaths() as $path) {
            $this->deleteOwnedFile($path);
        }
        if ($post->video_path) {
            Storage::disk('public')->delete($post->video_path);
        }

        $post->delete();

        return redirect()->route('posts.index')->with('success', 'Post deleted.');
    }

    private const MAX_IMAGES = 20;

    private function mediaRules(?Post $post): array
    {
        return [
            'image'              => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'images'             => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*'           => ['required', 'file', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'video'              => ['nullable', 'file', 'mimes:mp4,webm,mov,avi', 'max:102400'],
            'image_from_library' => $this->libraryImageRules($post),
            'image_set'          => ['nullable', 'boolean'],
            'keep_images'        => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'keep_images.*'      => ['distinct', ...$this->libraryImageRules($post)],
        ];
    }

    private function ensureImageLimit(Request $request, array $data): void
    {
        $total = count($data['keep_images'] ?? []) + count($request->file('images', []));

        if ($request->boolean('image_set') && $total > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'A post can have at most '.self::MAX_IMAGES.' images.',
            ]);
        }
    }

    /**
     * A library pick must be media the editor actually offers: an
     * accomplishment library copy, an eligible form upload, or an image the
     * post already uses.
     */
    private function libraryImageRules(?Post $post): array
    {
        return [
            'nullable',
            'string',
            'max:500',
            function (string $attribute, mixed $value, \Closure $fail) use ($post) {
                $path = (string) $value;

                $allowed = ($post !== null && in_array($path, $post->imagePaths(), true))
                    || AccomplishmentMedia::query()->where('file_path', $path)->exists()
                    || app(AfterEventMediaGallery::class)->allows($path);

                if (! $allowed) {
                    $fail('The selected library image is no longer available.');
                }
            },
        ];
    }

    private function deleteOwnedFile(?string $path): void
    {
        if ($path && ! Post::isSharedMediaPath($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function replaceMedia(Post $post, Request $request, array $data): void
    {
        $oldImages = $post->imagePaths();
        $oldVideo = $post->video_path;

        if ($request->hasFile('video')) {
            $post->video_path = $this->storeFile($request->file('video'), 'posts/videos');
            $post->image_path = null;
            $post->image_paths = [];
        } elseif ($request->boolean('image_set')) {
            $uploads = array_map(fn ($file) => $this->storeFile($file, 'posts/images'), $request->file('images', []));
            $paths = array_values(array_unique([...($data['keep_images'] ?? []), ...$uploads]));

            if ($paths !== $oldImages) {
                $post->image_paths = $paths;
                $post->image_path = $paths[0] ?? null;
                if ($paths !== []) {
                    $post->video_path = null;
                }
            }
        } elseif ($request->hasFile('images') || $request->hasFile('image')) {
            $files = $request->hasFile('images') ? $request->file('images') : [$request->file('image')];
            $paths = array_map(fn ($file) => $this->storeFile($file, 'posts/images'), $files);
            $post->image_paths = $paths;
            $post->image_path = $paths[0];
            $post->video_path = null;
        } elseif (! empty($data['image_from_library']) && $data['image_from_library'] !== $post->image_path) {
            $post->image_path = $data['image_from_library'];
            $post->image_paths = [$data['image_from_library']];
            $post->video_path = null;
        }

        foreach (array_diff($oldImages, $post->imagePaths()) as $path) {
            $this->deleteOwnedFile($path);
        }
        if ($oldVideo && $oldVideo !== $post->video_path) {
            Storage::disk('public')->delete($oldVideo);
        }
    }

    private function storeFile($file, string $dir): string
    {
        $dateDir = now()->format('Y/m');
        return $file->storeAs(
            "{$dir}/{$dateDir}",
            Str::lower(Str::random(16)) . '.' . $file->getClientOriginalExtension(),
            'public'
        );
    }
}
