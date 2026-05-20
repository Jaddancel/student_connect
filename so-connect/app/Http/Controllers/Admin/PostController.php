<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index()
    {
        $organizations = Organization::query()
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
            ->orderBy('od.name')
            ->get([ 'organizations.organization_id as id', 'od.name as name' ]);

        $form = Form::query()->where('route_name', 'accomplishment-report')->first();
        $reportMedia = [];

        if ($form) {
            $submissions = FormSubmission::query()
                ->where('form_id', $form->id)
                ->whereNotNull('organization_id')
                ->orderByDesc('submitted_at')
                ->get();

            foreach ($submissions as $submission) {
                $payload = $submission->payload;
                if (! is_array($payload)) {
                    continue;
                }

                if (! empty($payload['photos'])) {
                    $reportMedia[$submission->organization_id][] = [
                        'path' => $payload['photos'],
                        'label' => 'Accomplishment photo',
                        'submitted_at' => $submission->submitted_at,
                    ];
                }

                if (! empty($payload['signature'])) {
                    $reportMedia[$submission->organization_id][] = [
                        'path' => $payload['signature'],
                        'label' => 'Report signature',
                        'submitted_at' => $submission->submitted_at,
                    ];
                }
            }
        }

        $posts = Post::query()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        return view('pages.admin.posts.index', compact('posts', 'organizations', 'reportMedia'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'organization' => ['required', 'integer', 'exists:organizations,organization_id'],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['required', 'string', 'max:280'],
            'body' => ['nullable', 'string'],
            'tag' => ['nullable', 'string', 'max:100'],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'header_image' => ['nullable', 'image', 'max:10240'],
            'selected_media' => ['nullable', 'array'],
            'selected_media.*' => ['string'],
        ]);

        $imagePath = null;
        if ($request->hasFile('header_image') && $request->file('header_image')->isValid()) {
            $file = $request->file('header_image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $imagePath = $file->storeAs('posts', $filename, 'public');
        }

        $form = Form::query()->where('route_name', 'accomplishment-report')->first();
        $allowedMedia = [];

        if ($form) {
            $allowedMedia = FormSubmission::query()
                ->where('form_id', $form->id)
                ->where('organization_id', $request->input('organization'))
                ->whereNotNull('organization_id')
                ->get()
                ->flatMap(function (FormSubmission $submission) {
                    $media = [];
                    $payload = $submission->payload;

                    if (is_array($payload)) {
                        if (! empty($payload['photos'])) {
                            $media[] = $payload['photos'];
                        }
                        if (! empty($payload['signature'])) {
                            $media[] = $payload['signature'];
                        }
                    }

                    return $media;
                })
                ->unique()
                ->values()
                ->all();
        }

        $galleryImages = collect($request->input('selected_media', []))
            ->filter(fn ($path) => in_array($path, $allowedMedia, true))
            ->values()
            ->all();

        Post::create([
            'organization' => $request->input('organization'),
            'title' => $request->input('title'),
            'excerpt' => $request->input('excerpt'),
            'body' => $request->input('body'),
            'tag' => $request->input('tag'),
            'image_path' => $imagePath,
            'gallery_images' => $galleryImages,
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => $request->input('published_at'),
        ]);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Organization post created successfully.');
    }
}
