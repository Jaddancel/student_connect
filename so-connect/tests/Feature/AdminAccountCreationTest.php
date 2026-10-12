<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

it('allows superadmin to create an admin account with a signature', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    Mail::fake();

    $superAdmin = User::query()->create([
        'user_email' => 'superadmin-creator@example.test',
        'user_password' => 'password12345',
        'user_type' => User::TYPE_SUPERADMIN,
        'profile' => null,
        'profile_pending' => false,
    ]);

    // Create a 100x100 white image with a dark stroke for signature extraction
    $im = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $white);
    imagesetthickness($im, 3);
    imageline($im, 10, 50, 90, 50, $black);
    imageline($im, 50, 10, 50, 90, $black);
    ob_start();
    imagepng($im);
    $pngData = ob_get_clean();
    imagedestroy($im);

    $file = UploadedFile::fake()->createWithContent('signature.png', $pngData);

    $response = $this->actingAs($superAdmin)
        ->post(route('superadmin.accounts.store'), [
            'email' => 'new-admin@example.test',
            'first_name' => 'John',
            'last_name' => 'Admin',
            'contact_number' => '09171234567',
            'signature_file' => $file,
        ]);

    $response->assertRedirect(route('superadmin.accounts.create'));

    $newAdmin = User::query()->where('user_email', 'new-admin@example.test')->first();
    expect($newAdmin)->not()->toBeNull();
    expect($newAdmin->user_type)->toBe(2);

    $profile = Profile::find($newAdmin->profile);
    expect($profile)->not()->toBeNull();
    expect($profile->signature_path)->not()->toBeNull();
    Storage::disk('public')->assertExists($profile->signature_path);
});

it('allows superadmin to create another superadmin through the invitation workflow', function () {
    Mail::fake();

    $superAdmin = User::query()->create([
        'user_email' => 'superadmin-owner@example.test',
        'user_password' => 'password12345',
        'user_type' => User::TYPE_SUPERADMIN,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('superadmin.superadmins.create'))
        ->assertOk()
        ->assertSee('Create Superadmin Account');

    $response = $this->actingAs($superAdmin)
        ->post(route('superadmin.superadmins.store'), [
            'email' => 'new-superadmin@example.test',
            'first_name' => 'Sam',
            'last_name' => 'Superadmin',
            'contact_number' => '09171234567',
        ]);

    $response->assertRedirect(route('superadmin.superadmins.create'));

    $newSuperadmin = User::query()->where('user_email', 'new-superadmin@example.test')->firstOrFail();
    expect((int) $newSuperadmin->user_type)->toBe(User::TYPE_SUPERADMIN)
        ->and($newSuperadmin->force_password_change)->toBeTrue()
        ->and($newSuperadmin->email_verified_at)->toBeNull()
        ->and($newSuperadmin->profile->occupation)->toBe('SuperAdmin')
        ->and(DB::table('invitation_tokens')->where('user_email', $newSuperadmin->user_email)->exists())->toBeTrue();

    $invitation = null;
    Mail::assertSent(\App\Mail\AdminInvitationMail::class, function ($mail) use (&$invitation) {
        $invitation = $mail;

        return $mail->accountRole === 'Superadmin';
    });
    parse_str((string) parse_url($invitation->activationUrl, PHP_URL_QUERY), $query);

    auth()->logout();
    $this->get(route('invitation.verify', ['token' => $query['token']]))
        ->assertRedirect(route('password.change'));
    $this->assertAuthenticatedAs($newSuperadmin);
});

it('does not allow admins to access superadmin account creation', function () {
    $admin = User::query()->create([
        'user_email' => 'admin-noncreator@example.test',
        'user_password' => 'password12345',
        'user_type' => User::TYPE_ADMIN,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('superadmin.superadmins.create'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('superadmin.superadmins.store'), [])
        ->assertForbidden();
});
