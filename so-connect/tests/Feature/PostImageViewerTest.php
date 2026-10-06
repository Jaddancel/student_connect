<?php

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('stores multiple images in order and keeps the first as the feed thumbnail', function () {
    $org = recordsOrganization('Viewer organization');

    $this->actingAs(recordsUser(2))->post(route('posts.store'), [
        'organization' => $org->getKey(), 'title' => 'Featured gallery', 'excerpt' => 'Gallery summary',
        'is_featured' => 1,
        'images' => [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.png')],
    ])->assertSessionHasNoErrors();

    $post = Post::query()->sole();
    expect($post->imagePaths())->toHaveCount(2)
        ->and($post->image_path)->toBe($post->image_paths[0]);
    foreach ($post->imagePaths() as $path) {
        Storage::disk('public')->assertExists($path);
    }

    $this->get(route('organization-feed', ['organizationId' => $org->getKey()]))
        ->assertOk()
        ->assertSee('data-post-id="'.$post->getKey().'"', false)
        ->assertSee('aria-label="Next image"', false)
        ->assertSee('aria-labelledby="post-viewer-title"', false)
        ->assertSee('2 images');
});

it('retains the gallery on text edits and replaces it on new uploads', function () {
    $post = Post::create([
        'organization' => recordsOrganization('Viewer organization')->getKey(),
        'title' => 'Original', 'excerpt' => 'Summary', 'image_path' => 'posts/images/one.jpg',
        'image_paths' => ['posts/images/one.jpg', 'posts/images/two.jpg'],
    ]);
    foreach ($post->imagePaths() as $path) {
        Storage::disk('public')->put($path, 'old-image');
    }
    $user = recordsUser(2);
    $data = ['organization' => $post->organization, 'title' => 'Updated', 'excerpt' => 'Summary'];
    $this->actingAs($user)->patch(route('posts.update', $post->getKey()), $data)->assertSessionHasNoErrors();
    expect($post->fresh()->imagePaths())->toBe(['posts/images/one.jpg', 'posts/images/two.jpg']);

    $this->patch(route('posts.update', $post->getKey()), [
        ...$data, 'images' => [UploadedFile::fake()->image('replacement.jpg')],
    ])->assertSessionHasNoErrors();
    expect($post->fresh()->imagePaths())->toHaveCount(1);
    Storage::disk('public')->assertMissing(['posts/images/one.jpg', 'posts/images/two.jpg']);
});

it('preserves borrowed images when deleting all owned gallery attachments', function () {
    $post = Post::create([
        'organization' => recordsOrganization('Viewer organization')->getKey(),
        'title' => 'Gallery', 'excerpt' => 'Summary', 'image_path' => 'form-uploads/report/shared.jpg',
        'image_paths' => ['form-uploads/report/shared.jpg', 'posts/images/owned.jpg'],
    ]);
    foreach ($post->imagePaths() as $path) {
        Storage::disk('public')->put($path, 'image');
    }
    $this->actingAs(recordsUser(2))->delete(route('posts.destroy', $post->getKey()))->assertRedirect();
    Storage::disk('public')->assertExists('form-uploads/report/shared.jpg');
    Storage::disk('public')->assertMissing('posts/images/owned.jpg');
});

it('supports legacy single images without a gallery column value', function () {
    $post = new Post(['image_path' => 'posts/images/legacy.jpg']);
    expect($post->imagePaths())->toBe(['posts/images/legacy.jpg']);
});

it('rejects oversized image sets and non-image attachments', function () {
    $data = [
        'organization' => recordsOrganization('Viewer organization')->getKey(),
        'title' => 'Gallery', 'excerpt' => 'Summary',
    ];
    $this->actingAs(recordsUser(2))->post(route('posts.store'), [
        ...$data, 'images' => array_map(fn ($i) => UploadedFile::fake()->image("photo-$i.jpg"), range(1, 21)),
    ])->assertSessionHasErrors('images');
    $this->post(route('posts.store'), [
        ...$data, 'images' => [UploadedFile::fake()->create('document.pdf', 1, 'application/pdf')],
    ])->assertSessionHasErrors('images.0');
    expect(Post::count())->toBe(0);
});

it('clears gallery images when replaced by a video', function () {
    $post = Post::create([
        'organization' => recordsOrganization('Viewer organization')->getKey(),
        'title' => 'Gallery', 'excerpt' => 'Summary', 'image_path' => 'posts/images/one.jpg',
        'image_paths' => ['posts/images/one.jpg', 'posts/images/two.jpg'],
    ]);
    foreach ($post->imagePaths() as $path) {
        Storage::disk('public')->put($path, 'image');
    }
    $this->actingAs(recordsUser(2))->patch(route('posts.update', $post->getKey()), [
        'organization' => $post->organization, 'title' => 'Video', 'excerpt' => 'Summary',
        'video' => UploadedFile::fake()->create('video.mp4', 10, 'video/mp4'),
    ])->assertSessionHasNoErrors();
    expect($post->fresh()->imagePaths())->toBe([])
        ->and($post->fresh()->video_path)->not->toBeNull();
    Storage::disk('public')->assertMissing(['posts/images/one.jpg', 'posts/images/two.jpg']);
});
