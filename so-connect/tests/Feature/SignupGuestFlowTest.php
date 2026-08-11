<?php

use App\Mail\AccountRequestReceivedMail;
use App\Mail\SignupApprovedMail;
use App\Mail\SignupRejectedMail;
use App\Models\Approval;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Sign up -> success page -> confirm email -> guest dashboard -> admin decision.
 *
 * The account exists from the moment the form is submitted (as a pending guest),
 * so these tests follow one user row all the way through rather than watching
 * for a new one to appear at approval time.
 */
function guestSignupForm(): Form
{
    $form = Form::create([
        'name' => 'Officer Sign Up',
        'route_name' => 'guest-signup',
        'system_function' => 'sign_up',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>{{ first_name }}</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    foreach ([
        ['field_key' => 'organization_id', 'field_label' => 'Organization', 'field_type' => 'org-select'],
        ['field_key' => 'first_name', 'field_label' => 'First name', 'field_type' => 'text', 'universal_key' => 'first_name'],
        ['field_key' => 'last_name', 'field_label' => 'Last name', 'field_type' => 'text', 'universal_key' => 'last_name'],
        ['field_key' => 'email', 'field_label' => 'Email', 'field_type' => 'email'],
        ['field_key' => 'position', 'field_label' => 'Position', 'field_type' => 'text'],
        ['field_key' => 'contact_number', 'field_label' => 'Contact number', 'field_type' => 'text'],
        ['field_key' => 'password', 'field_label' => 'Password', 'field_type' => 'password'],
    ] as $order => $field) {
        FormDescription::create(array_merge([
            'form_id' => $form->id,
            'is_required' => false,
            'field_order' => $order + 1,
        ], $field));
    }

    return $form;
}

function guestSignupOrganization(): Organization
{
    $detailId = DB::table('organization_details')->insertGetId([
        'name' => 'Robotics Club',
        'detail_text' => 'Robotics Club',
        'initials' => 'RC',
    ]);

    return Organization::query()->create(['organization_type' => 1, 'detail' => $detailId]);
}

/**
 * @param  array<string,mixed>  $overrides
 * @return array<string,mixed>
 */
function guestSignupPayload(Organization $organization, array $overrides = []): array
{
    return array_merge([
        'organization_id' => (int) $organization->getKey(),
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria.santos@example.test',
        'position' => 'Secretary',
        'contact_number' => '9171234567',
        'password' => 'sup3rSecret!',
        'password_confirmation' => 'sup3rSecret!',
    ], $overrides);
}

/** Submit the sign-up form; returns the pending account it created. */
function submitGuestSignup(Organization $organization, array $overrides = []): User
{
    test()->post(route('forms.render.submit', 'guest-signup'), guestSignupPayload($organization, $overrides))
        ->assertRedirect(route('signup.success'));

    return User::query()->where('user_email', $overrides['email'] ?? 'maria.santos@example.test')->firstOrFail();
}

/** File and decide a request the way the Promotion Requests page does. */
function decideSignup(ActionRequest $actionRequest, string $decision, string $reason = ''): void
{
    test()->actingAs(recordsUser(2))
        ->postJson('/api/requests/'.$actionRequest->getKey().'/decision', array_filter([
            'decision' => $decision,
            'rejection_reason' => $reason !== '' ? $reason : null,
        ]))
        ->assertOk();
}

it('creates a pending guest account and emails a confirmation link', function () {
    Mail::fake();
    guestSignupForm();
    $organization = guestSignupOrganization();

    $applicant = submitGuestSignup($organization);

    expect((int) $applicant->user_type)->toBe(User::TYPE_GUEST)
        ->and($applicant->hasVerifiedEmail())->toBeFalse()
        ->and($applicant->profile()->first()?->first_name)->toBe('Maria')
        // No membership: that is what every officer gate keys on.
        ->and(DB::table('organization_officers')->where('user', $applicant->getKey())->exists())->toBeFalse();

    $signupRequest = ActionRequest::query()->where('action_type', 11)->firstOrFail();
    expect((int) $signupRequest->user)->toBe((int) $applicant->getKey())
        ->and((int) $signupRequest->payload['pending_user_id'])->toBe((int) $applicant->getKey());

    Mail::assertSent(AccountRequestReceivedMail::class, fn ($mail) => $mail->hasTo('maria.santos@example.test'));
});

it('shows the success page after signing up', function () {
    Mail::fake();
    guestSignupForm();
    submitGuestSignup(guestSignupOrganization());

    $this->get(route('signup.success'))
        ->assertOk()
        ->assertSee('Sign-up submitted')
        ->assertSee('maria.santos@example.test');
});

it('confirms the email, signs the applicant in and lands them on the guest dashboard', function () {
    Mail::fake();
    guestSignupForm();
    $applicant = submitGuestSignup(guestSignupOrganization());

    $rawToken = null;
    Mail::assertSent(AccountRequestReceivedMail::class, function ($mail) use (&$rawToken) {
        parse_str((string) parse_url($mail->confirmUrl, PHP_URL_QUERY), $query);
        $rawToken = $query['token'] ?? null;

        return true;
    });

    $this->get(route('invitation.verify', ['token' => $rawToken]))
        ->assertRedirect(route('guest.dashboard'));

    expect($applicant->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($applicant->fresh());

    $this->get(route('guest.dashboard'))
        ->assertOk()
        ->assertSee('Awaiting review')
        ->assertSee('Robotics Club');
});

it('keeps a guest out of officer pages and off the officer dashboard', function () {
    Mail::fake();
    guestSignupForm();
    $applicant = submitGuestSignup(guestSignupOrganization());

    Form::create([
        'name' => 'Officers Only',
        'route_name' => 'officers-only',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    $this->actingAs($applicant)->get(route('dashboard'))->assertRedirect(route('guest.dashboard'));
    $this->actingAs($applicant)->get(route('forms.render', 'officers-only'))->assertForbidden();
});

it('promotes the same account on approval instead of creating a second one', function () {
    Mail::fake();
    guestSignupForm();
    $organization = guestSignupOrganization();
    $applicant = submitGuestSignup($organization);

    decideSignup(ActionRequest::query()->where('action_type', 11)->firstOrFail(), 'approve');

    $applicant->refresh();

    expect(User::query()->where('user_email', 'maria.santos@example.test')->count())->toBe(1)
        ->and((int) $applicant->user_type)->toBe(User::TYPE_OFFICER)
        ->and(DB::table('organization_officers')
            ->where('user', $applicant->getKey())
            ->where('organization', $organization->getKey())
            ->where('role', 'officer')
            ->exists())->toBeTrue();

    Mail::assertSent(SignupApprovedMail::class, fn ($mail) => $mail->hasTo('maria.santos@example.test'));
    $this->actingAs($applicant)->get(route('guest.dashboard'))->assertRedirect(route('dashboard'));
});

it('tells a rejected applicant why, and shows the reason on their dashboard', function () {
    Mail::fake();
    guestSignupForm();
    $applicant = submitGuestSignup(guestSignupOrganization());

    decideSignup(
        ActionRequest::query()->where('action_type', 11)->firstOrFail(),
        'reject',
        'Your student ID photo is unreadable.',
    );

    Mail::assertSent(SignupRejectedMail::class, function ($mail) {
        return $mail->hasTo('maria.santos@example.test')
            && $mail->reason === 'Your student ID photo is unreadable.'
            && $mail->attemptsLeft === 2
            && $mail->accountDeleted === false;
    });

    $this->actingAs($applicant->fresh())
        ->get(route('guest.dashboard'))
        ->assertOk()
        ->assertSee('Not approved')
        ->assertSee('Your student ID photo is unreadable.')
        ->assertSee('2 attempts remaining', false);
});

it('prefills the sign-up form from the last submission when a rejected guest retries', function () {
    Mail::fake();
    guestSignupForm();
    $applicant = submitGuestSignup(guestSignupOrganization());
    decideSignup(ActionRequest::query()->where('action_type', 11)->firstOrFail(), 'reject', 'Wrong position.');

    $this->actingAs($applicant->fresh())
        ->get(route('forms.render', 'guest-signup'))
        ->assertOk()
        ->assertSee('value="Secretary"', false)
        ->assertSee('value="maria.santos@example.test"', false);
});

it('files a fresh request on resubmission without creating a second account', function () {
    Mail::fake();
    guestSignupForm();
    $organization = guestSignupOrganization();
    $applicant = submitGuestSignup($organization);
    decideSignup(ActionRequest::query()->where('action_type', 11)->firstOrFail(), 'reject', 'Wrong position.');

    $this->actingAs($applicant->fresh())
        ->post(route('forms.render.submit', 'guest-signup'), guestSignupPayload($organization, ['position' => 'Treasurer']))
        ->assertRedirect(route('signup.success'));

    expect(User::query()->where('user_email', 'maria.santos@example.test')->count())->toBe(1)
        ->and(ActionRequest::query()->where('action_type', 11)->count())->toBe(2)
        ->and($applicant->fresh()->profile()->first()?->position)->toBe('Treasurer');
});

it('lets a rejected guest resubmit without retyping their password', function () {
    Mail::fake();
    $form = guestSignupForm();
    // The real sign-up form marks the password mandatory — which must not
    // force a re-applying guest to invent a new one on every attempt.
    FormDescription::query()
        ->where('form_id', $form->id)
        ->where('field_key', 'password')
        ->update(['is_required' => true]);

    $organization = guestSignupOrganization();
    $applicant = submitGuestSignup($organization);
    $originalPassword = $applicant->user_password;
    decideSignup(ActionRequest::query()->where('action_type', 11)->firstOrFail(), 'reject', 'Wrong position.');

    // The field renders without its required marker, carrying the hint instead.
    $this->actingAs($applicant->fresh())
        ->get(route('forms.render', 'guest-signup'))
        ->assertOk()
        ->assertSee('Leave blank to keep your current password');

    $payload = guestSignupPayload($organization, ['position' => 'Treasurer']);
    $payload['password'] = '';
    unset($payload['password_confirmation']);

    $this->actingAs($applicant->fresh())
        ->post(route('forms.render.submit', 'guest-signup'), $payload)
        ->assertRedirect(route('signup.success'));

    expect($applicant->fresh()->user_password)->toBe($originalPassword)
        ->and(ActionRequest::query()->where('action_type', 11)->count())->toBe(2);
});

it('still requires a password from a brand new applicant', function () {
    Mail::fake();
    $form = guestSignupForm();
    FormDescription::query()
        ->where('form_id', $form->id)
        ->where('field_key', 'password')
        ->update(['is_required' => true]);

    $payload = guestSignupPayload(guestSignupOrganization());
    $payload['password'] = '';
    unset($payload['password_confirmation']);

    $this->post(route('forms.render.submit', 'guest-signup'), $payload)
        ->assertSessionHasErrors('password');

    expect(User::query()->where('user_email', 'maria.santos@example.test')->exists())->toBeFalse();
});

it('refuses a second request while one is still awaiting review', function () {
    Mail::fake();
    guestSignupForm();
    $organization = guestSignupOrganization();
    $applicant = submitGuestSignup($organization);

    $this->actingAs($applicant->fresh())
        ->post(route('forms.render.submit', 'guest-signup'), guestSignupPayload($organization))
        ->assertSessionHasErrors('form');

    expect(ActionRequest::query()->where('action_type', 11)->count())->toBe(1);
});

it('still creates the account at approval for a request filed before pending accounts', function () {
    Mail::fake();
    $organization = guestSignupOrganization();

    // No pending_user_id: a request already sitting in the queue when this
    // feature shipped. It must approve exactly as it did before.
    $legacyRequest = ActionRequest::query()->create([
        'action' => '0|'.$organization->getKey().'|new_officer',
        'action_type' => 11,
        'user' => null,
        'requested_at' => now(),
        'payload' => [
            'first_name' => 'Legacy',
            'last_name' => 'Applicant',
            'email' => 'legacy@example.test',
            'organization_id' => (int) $organization->getKey(),
            'position' => 'Auditor',
        ],
    ]);

    decideSignup($legacyRequest, 'approve');

    $created = User::query()->where('user_email', 'legacy@example.test')->first();

    expect($created)->not->toBeNull()
        ->and((int) $created->user_type)->toBe(User::TYPE_OFFICER)
        ->and($created->profile()->first()?->last_name)->toBe('Applicant');

    Mail::assertSent(App\Mail\OfficerActivationMail::class);
    Mail::assertNotSent(SignupApprovedMail::class);
});

it('closes the guest account after the third rejection', function () {
    Mail::fake();
    guestSignupForm();
    $organization = guestSignupOrganization();
    $applicant = submitGuestSignup($organization);
    $profileId = (int) $applicant->profile;

    foreach (range(1, 3) as $attempt) {
        $pending = ActionRequest::query()
            ->where('action_type', 11)
            ->whereNotIn('request_id', Approval::query()->pluck('request')->all())
            ->firstOrFail();

        decideSignup($pending, 'reject', "Attempt {$attempt} was incomplete.");

        if ($attempt < 3) {
            $this->actingAs($applicant->fresh())
                ->post(route('forms.render.submit', 'guest-signup'), guestSignupPayload($organization))
                ->assertRedirect(route('signup.success'));
        }
    }

    expect(User::query()->where('user_email', 'maria.santos@example.test')->exists())->toBeFalse()
        ->and(Profile::query()->where('profile_id', $profileId)->exists())->toBeFalse()
        // The record of what was submitted and refused survives the account.
        ->and(ActionRequest::query()->where('action_type', 11)->count())->toBe(3);

    Mail::assertSent(SignupRejectedMail::class, fn ($mail) => $mail->accountDeleted === true
        && $mail->attemptsLeft === 0
        // Nothing is left to correct, so the subject must not ask for changes.
        && $mail->envelope()->subject === 'Your StudentConnect sign-up was closed');

    Mail::assertSent(SignupRejectedMail::class, fn ($mail) => $mail->accountDeleted === false
        && $mail->envelope()->subject === 'Your StudentConnect sign-up needs changes');
});
