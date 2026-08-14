<?php

use App\Mail\AccountRequestReceivedMail;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\IdTemplate;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\SignatureReference;
use App\Models\User;
use App\Services\SignatureReferenceService;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * End-to-end exercise of the signature recognition system against three real
 * signature images (tests/Fixtures/signatures), driving the live OCR sidecar
 * rather than a stubbed one — the point is to measure what the matcher actually
 * says, not to re-assert a fake.
 *
 * Scenario 1 — an officer (user type 3) who has a signature of their own signs
 * a form with a DIFFERENT officer's signature. The verifier must name the
 * signature's true owner, not the person holding the pen.
 *
 * Scenario 2 — a brand new applicant's signature is lifted off their ID by the
 * scanner, their address is confirmed by email and their sign-up approved by an
 * admin. Their signature must then be recognized as theirs when somebody else
 * uses it, exactly as in scenario 1.
 *
 * Every test skips (rather than fails) when the sidecar is unreachable, so the
 * suite still runs on a machine with no OCR container.
 */
beforeEach(function () {
    if (! scenarioSidecarUp()) {
        test()->markTestSkipped('OCR sidecar unreachable at '.config('services.ocr.url'));
    }

    Storage::fake('public');
    config(['documents.disk' => 'public']);
});

/** Whether the OCR sidecar that does the matching is actually answering. */
function scenarioSidecarUp(): bool
{
    try {
        return Http::timeout(5)
            ->get(rtrim((string) config('services.ocr.url'), '/').'/health')
            ->successful();
    } catch (\Throwable) {
        return false;
    }
}

function scenarioSignatureBytes(string $file): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/signatures/'.$file));
}

function scenarioSignatureDataUrl(string $file): string
{
    return 'data:image/png;base64,'.base64_encode(scenarioSignatureBytes($file));
}

/**
 * The same signature captured a second time: rotated a little, scaled, lit
 * unevenly and JPEG-compressed — a phone photo of the paper, or a screenshot
 * passed around. A matcher that only recognizes byte-identical files would
 * sail through a same-file probe and fail every real one, so the impersonation
 * probes all go through this.
 */
function scenarioRephotograph(string $file, float $angle = 3.0, float $scale = 0.75): string
{
    $source = imagecreatefromstring(scenarioSignatureBytes($file));

    // The fixtures are RGBA; flatten onto paper white first so rotation and
    // JPEG encoding do not turn transparency into black.
    $paper = imagecreatetruecolor(imagesx($source), imagesy($source));
    imagefill($paper, 0, 0, imagecolorallocate($paper, 253, 252, 248));
    imagecopy($paper, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
    imagedestroy($source);

    $rotated = imagerotate($paper, $angle, imagecolorallocate($paper, 253, 252, 248));
    imagedestroy($paper);

    $scaled = imagescale($rotated, max(120, (int) round(imagesx($rotated) * $scale)));
    imagedestroy($rotated);

    // Uneven lighting + sensor noise across the frame.
    $width = imagesx($scaled);
    $height = imagesy($scaled);
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($scaled, $x, $y);
            $shift = (int) (-18 * ($x / $width)) + random_int(-4, 4);
            imagesetpixel($scaled, $x, $y, imagecolorallocate(
                $scaled,
                max(0, min(255, (($rgb >> 16) & 0xFF) + $shift)),
                max(0, min(255, (($rgb >> 8) & 0xFF) + $shift)),
                max(0, min(255, ($rgb & 0xFF) + $shift)),
            ));
        }
    }

    ob_start();
    imagejpeg($scaled, null, 82);
    imagedestroy($scaled);

    return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
}

/**
 * An officer (user type 3) whose profile carries a signature — stored the way
 * every capture surface stores one (ink extracted, not the raw picture) and
 * mirrored into the reference registry the verifier reads.
 */
function scenarioOfficer(string $first, string $last, string $signatureFile): User
{
    $path = SignatureImage::store(scenarioSignatureBytes($signatureFile), 'signatures/profile');
    expect($path)->not->toBeNull("the {$signatureFile} fixture holds no legible ink");

    $profile = Profile::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'middle_name' => 'T',
        'occupation' => 'Student',
        'signature_path' => $path,
    ]);

    app(SignatureReferenceService::class)->syncFromProfile($profile);

    $user = User::query()->create([
        'user_email' => Str::lower($first.'.'.$last).'@example.test',
        'user_password' => 'password',
        'user_type' => User::TYPE_OFFICER,
        'profile' => (int) $profile->getKey(),
    ]);

    scenarioJoinOrganization($user);

    return $user;
}

/** Builder forms are gated to organization officers; give the user a seat. */
function scenarioJoinOrganization(User $user, ?int $organizationId = null): int
{
    $organizationId ??= (int) scenarioOrganization()->getKey();

    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $organizationId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $organizationId;
}

function scenarioOrganization(): \App\Models\Organization
{
    static $organization = null;

    // One org per test process is enough; RefreshDatabase resets it each test.
    if ($organization === null || ! \App\Models\Organization::query()->whereKey($organization->getKey())->exists()) {
        $organization = recordsOrganization('Scenario Society', 'SS');
    }

    return $organization;
}

/** POST the live "whose signature is this?" endpoint behind the field badge. */
function scenarioVerify(User $actor, string $dataUrl): array
{
    return (array) test()->actingAs($actor)
        ->postJson(route('signature.verify'), ['signature' => $dataUrl])
        ->assertOk()
        ->json();
}

/** A form with one name field and one signature field, as the builder makes it. */
function scenarioWaiverForm(string $route = 'scenario-waiver'): Form
{
    $form = Form::query()->create([
        'name' => 'Scenario Waiver',
        'route_name' => $route,
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    foreach ([
        ['field_key' => 'printed_name', 'field_label' => 'Name of Signatory', 'field_type' => 'text'],
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ] as $order => $field) {
        FormDescription::query()->create(array_merge([
            'form_id' => $form->id,
            'is_required' => false,
            'field_order' => $order + 1,
        ], $field));
    }

    return $form;
}

/**
 * The sidecar's raw verdict for one probe against a candidate set, including
 * the threshold it is enforcing (which {@see OcrClient} does not surface).
 *
 * @param  array<int,array{id:int, image:string}>  $candidates
 * @return array<string,mixed>
 */
function scenarioIdentify(string $probe, array $candidates): array
{
    return (array) Http::timeout((int) config('services.ocr.timeout', 60))
        ->attach('probe', $probe, 'probe.png')
        ->post(rtrim((string) config('services.ocr.url'), '/').'/signature-identify', [
            'candidates' => json_encode($candidates),
        ])
        ->throw()
        ->json();
}

/** Print a measured line into the test output; these tests exist to report numbers. */
function scenarioReport(string $line): void
{
    fwrite(STDERR, "\n    · ".$line);
}

// ---------------------------------------------------------------------------
// Baseline: does the matcher recognize the signatures at all?
// ---------------------------------------------------------------------------

it('recognizes each registered officer signature as its own owner', function () {
    $lopez = scenarioOfficer('Miguel', 'Lopez', 'lopez.png');
    $silverton = scenarioOfficer('Dana', 'Silverton', 'silverton.png');

    $own = scenarioVerify($lopez, scenarioSignatureDataUrl('lopez.png'));
    $hers = scenarioVerify($silverton, scenarioSignatureDataUrl('silverton.png'));

    scenarioReport(sprintf('genuine (same capture): Lopez %.3f, Silverton %.3f', $own['score'] ?? -1, $hers['score'] ?? -1));

    expect($own['status'])->toBe('recognized')
        ->and($own['matched_user'])->toBe('Miguel Lopez')
        ->and($hers['status'])->toBe('recognized')
        ->and($hers['matched_user'])->toBe('Dana Silverton');
});

it('still recognizes a signature re-photographed at another angle and scale', function () {
    $lopez = scenarioOfficer('Miguel', 'Lopez', 'lopez.png');
    scenarioOfficer('Dana', 'Silverton', 'silverton.png');

    $result = scenarioVerify($lopez, scenarioRephotograph('lopez.png'));

    scenarioReport(sprintf(
        'genuine (re-photographed): %s → %s @ %.3f',
        $result['status'], $result['matched_user'] ?? '—', $result['score'] ?? -1,
    ));

    expect($result['status'])->toBe('recognized')
        ->and($result['matched_user'])->toBe('Miguel Lopez');
});

it('reports no match for a signature belonging to nobody on file', function () {
    $lopez = scenarioOfficer('Miguel', 'Lopez', 'lopez.png');
    scenarioOfficer('Dana', 'Silverton', 'silverton.png');

    // Navarro has not signed up yet: his signature is in no registry.
    $result = scenarioVerify($lopez, scenarioSignatureDataUrl('navarro.png'));

    scenarioReport(sprintf(
        'stranger: %s (best %s @ %.3f)',
        $result['status'], $result['matched_user'] ?? '—', $result['score'] ?? -1,
    ));

    expect($result['status'])->toBe('not_recognized')
        ->and($result['matched_user'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Scenario 1 — an officer signs with another officer's signature.
// ---------------------------------------------------------------------------

it('names the true owner when an officer signs with another officer\'s signature', function () {
    $lopez = scenarioOfficer('Miguel', 'Lopez', 'lopez.png');
    scenarioOfficer('Dana', 'Silverton', 'silverton.png');

    // Lopez, who has a signature of his own on file, puts Silverton's in the
    // signature field — a copy of it, not the exact file the system stored.
    $result = scenarioVerify($lopez, scenarioRephotograph('silverton.png'));

    scenarioReport(sprintf(
        'scenario 1 — Lopez signs as Silverton: %s → %s @ %.3f',
        $result['status'], $result['matched_user'] ?? '—', $result['score'] ?? -1,
    ));

    expect($result['status'])->toBe('recognized')
        // The signature is named for its owner, not for whoever submitted it.
        ->and($result['matched_user'])->toBe('Dana Silverton')
        ->and($result['matched_user'])->not->toBe('Miguel Lopez');
});

it('accepts the borrowed signature on submit and files it under the name typed on the form', function () {
    $lopez = scenarioOfficer('Miguel', 'Lopez', 'lopez.png');
    scenarioOfficer('Dana', 'Silverton', 'silverton.png');
    scenarioWaiverForm();

    $this->actingAs($lopez)
        ->post(route('forms.render.submit', 'scenario-waiver'), [
            'printed_name' => 'Dana Silverton',
            'sig' => scenarioRephotograph('silverton.png'),
        ])
        ->assertRedirect();

    $submission = DB::table('form_submissions')->orderByDesc('form_submission_id')->first();
    $payload = json_decode((string) $submission->payload, true);

    // Nothing here blocks the submission: verification is advisory only.
    expect($payload['sig'])->toBeString()
        ->and(Storage::disk('public')->exists($payload['sig']))->toBeTrue();

    scenarioReport('scenario 1 — submission stored at '.$payload['sig'].' (no gate on a mismatched signature)');

    // The capture is filed under the name typed next to it and recognized as
    // Silverton, so it is deduped into her existing profile rather than stacking
    // a second reference — and because she already had a signature, hers is left
    // untouched (a borrowed capture never overwrites the real owner's).
    $silvertonReferences = SignatureReference::query()->where('name', 'Dana Silverton')->get();
    expect($silvertonReferences->pluck('source')->unique()->values()->all())->toBe(['profile'])
        // Lopez's own profile signature is untouched by someone else's capture.
        ->and($lopez->profile()->first()->signature_path)->not->toBe($payload['sig']);
});

// ---------------------------------------------------------------------------
// Scenario 2 — a new user's signature comes off their ID, is confirmed and
// approved, then takes Silverton's place in scenario 1.
// ---------------------------------------------------------------------------

/**
 * A student ID photo with the applicant's signature printed on the signature
 * line: white card, printed name and ID number, the signature pasted inside a
 * zone with a margin (ink that runs off the edge of a zone is discarded by the
 * extractor as card artwork).
 */
function scenarioIdCard(string $signatureFile, string $printedName, string $studentId): string
{
    $card = imagecreatetruecolor(1000, 600);
    imagefill($card, 0, 0, imagecolorallocate($card, 250, 250, 252));
    $ink = imagecolorallocate($card, 25, 25, 35);
    $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    imagettftext($card, 26, 0, 60, 90, $ink, $font, 'SAMPLE STATE UNIVERSITY');
    imagettftext($card, 34, 0, 60, 200, $ink, $font, $printedName);
    imagettftext($card, 30, 0, 60, 270, $ink, $font, $studentId);
    imagettftext($card, 18, 0, 60, 560, $ink, $font, 'Signature');

    // The signature itself, scaled whole to sit inside the 60..940 x 380..540
    // zone with a margin: ink that touches the edge of a zone is discarded by
    // the extractor as card artwork, and a cropped signature is not the
    // signature.
    $signature = imagecreatefromstring(scenarioSignatureBytes($signatureFile));
    $flattened = imagecreatetruecolor(imagesx($signature), imagesy($signature));
    imagefill($flattened, 0, 0, imagecolorallocate($flattened, 250, 250, 252));
    imagecopy($flattened, $signature, 0, 0, 0, 0, imagesx($signature), imagesy($signature));
    imagedestroy($signature);

    $fit = min(760 / imagesx($flattened), 120 / imagesy($flattened));
    $target = imagescale($flattened, (int) round(imagesx($flattened) * $fit), (int) round(imagesy($flattened) * $fit));
    imagedestroy($flattened);
    imagecopy($card, $target, 130, 410, 0, 0, imagesx($target), imagesy($target));
    imagedestroy($target);

    ob_start();
    imagejpeg($card, null, 90);
    imagedestroy($card);

    return (string) ob_get_clean();
}

/** An ID template whose zones cover the printed number and the signature line. */
function scenarioIdTemplate(): IdTemplate
{
    return IdTemplate::query()->create([
        'name' => 'Scenario Student ID',
        'orientation' => 'horizontal',
        'image_path' => 'id-templates/scenario.jpg',
        'image_width' => 1000,
        'image_height' => 600,
        'is_active' => true,
        'is_default' => true,
        'zones' => [
            [
                'name' => 'zone_1', 'label' => 'Student number',
                'x1' => 50, 'y1' => 230, 'x2' => 600, 'y2' => 290,
                'regex' => null, 'field' => 'student_id', 'type' => 'text',
            ],
            [
                'name' => 'zone_2', 'label' => 'Signature',
                'x1' => 60, 'y1' => 380, 'x2' => 940, 'y2' => 540,
                'regex' => null, 'field' => 'signature', 'type' => 'signature',
            ],
        ],
    ]);
}

/** The sign-up form the applicant fills, ID-scan wizard and signature included. */
function scenarioSignupForm(): Form
{
    $form = Form::query()->create([
        'name' => 'Officer Sign Up',
        'route_name' => 'scenario-signup',
        'system_function' => 'sign_up',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>{{ first_name }}</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    foreach ([
        ['field_key' => 'organization_id', 'field_label' => 'Organization', 'field_type' => 'org-select'],
        ['field_key' => 'student_id', 'field_label' => 'Student ID number', 'field_type' => 'id-scan', 'universal_key' => 'student_id'],
        ['field_key' => 'first_name', 'field_label' => 'First name', 'field_type' => 'text', 'universal_key' => 'first_name'],
        ['field_key' => 'last_name', 'field_label' => 'Last name', 'field_type' => 'text', 'universal_key' => 'last_name'],
        ['field_key' => 'email', 'field_label' => 'Email', 'field_type' => 'email'],
        ['field_key' => 'position', 'field_label' => 'Position', 'field_type' => 'text'],
        ['field_key' => 'signature', 'field_label' => 'Signature', 'field_type' => 'signature', 'universal_key' => 'signature'],
        ['field_key' => 'password', 'field_label' => 'Password', 'field_type' => 'password'],
    ] as $order => $field) {
        FormDescription::query()->create(array_merge([
            'form_id' => $form->id,
            'is_required' => false,
            'field_order' => $order + 1,
        ], $field));
    }

    return $form;
}

/**
 * Run the applicant all the way through: ID scan → sign-up → email
 * confirmation → admin approval. Returns the promoted account.
 */
function scenarioApproveNavarro(int $organizationId): User
{
    scenarioIdTemplate();
    scenarioSignupForm();

    // 1. The wizard photographs the ID and the sidecar lifts the signature off it.
    $scan = test()->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->createWithContent(
            'id-front.jpg',
            // Digits only: the ID-scan field validates `^[0-9]+$`.
            scenarioIdCard('navarro.png', 'RAFAEL NAVARRO', '202104832'),
        ),
        'template_id' => IdTemplate::query()->value('id_template_id'),
    ])->assertOk()->json();

    $extracted = $scan['images']['signature'] ?? null;
    scenarioReport(sprintf(
        'scenario 2 — ID scan: student_id %s, signature %s',
        $scan['student_id'] ?? '—',
        is_string($extracted) ? strlen($extracted).' byte data-URL' : 'NOT EXTRACTED',
    ));

    expect($extracted)->toBeString()->toStartWith('data:image');

    // 2. The extracted signature rides into the sign-up form the way the wizard
    //    fills it, and files the account request.
    test()->post(route('forms.render.submit', 'scenario-signup'), [
        'organization_id' => $organizationId,
        // OCR misreads are the student's to correct; the flow under test is the
        // signature's, so fall back to the number printed on the card.
        'student_id' => preg_match('/^[0-9]+$/', (string) ($scan['student_id'] ?? '')) ? $scan['student_id'] : '202104832',
        'first_name' => 'Rafael',
        'last_name' => 'Navarro',
        'email' => 'rafael.navarro@example.test',
        'position' => 'Auditor',
        'signature' => $extracted,
        'password' => 'sup3rSecret!',
        'password_confirmation' => 'sup3rSecret!',
    ])->assertRedirect(route('signup.success'));

    $applicant = User::query()->where('user_email', 'rafael.navarro@example.test')->firstOrFail();
    expect((int) $applicant->user_type)->toBe(User::TYPE_GUEST);

    // 3. He confirms his email address from the link he was sent.
    $rawToken = null;
    Mail::assertSent(AccountRequestReceivedMail::class, function ($mail) use (&$rawToken) {
        parse_str((string) parse_url($mail->confirmUrl, PHP_URL_QUERY), $query);
        $rawToken = $query['token'] ?? null;

        return true;
    });
    test()->get(route('invitation.verify', ['token' => $rawToken]))->assertRedirect(route('guest.dashboard'));
    expect($applicant->fresh()->hasVerifiedEmail())->toBeTrue();

    // 4. An admin approves the sign-up, which promotes the same account.
    test()->actingAs(recordsUser(2))
        ->postJson('/api/requests/'.ActionRequest::query()->where('action_type', 11)->value('request_id').'/decision', [
            'decision' => 'approve',
        ])->assertOk();

    $applicant = $applicant->fresh();
    expect((int) $applicant->user_type)->toBe(User::TYPE_OFFICER);

    return $applicant;
}

it('lifts a new applicant\'s signature off their ID and keeps it through approval', function () {
    Mail::fake();
    $organizationId = (int) scenarioOrganization()->getKey();

    $navarro = scenarioApproveNavarro($organizationId);
    $profile = $navarro->profile()->first();

    // The signature the scanner extracted is on the approved account's profile…
    expect((string) $profile->signature_path)->not->toBe('')
        ->and(Storage::disk('public')->exists($profile->signature_path))->toBeTrue();

    // …and in the registry the verifier reads, under his name.
    $reference = SignatureReference::query()->where('name', 'Rafael Navarro')->first();
    scenarioReport(sprintf(
        'scenario 2 — registry entry: %s (source %s)',
        $reference?->name ?? 'MISSING', $reference?->source ?? '—',
    ));

    expect($reference)->not->toBeNull()
        ->and(Storage::disk('public')->exists($reference->signature_path))->toBeTrue();
});

it('names the newly approved applicant when an officer signs with his signature', function () {
    Mail::fake();
    $lopez = scenarioOfficer('Miguel', 'Lopez', 'lopez.png');
    scenarioApproveNavarro(scenarioJoinOrganization($lopez, (int) scenarioOrganization()->getKey()));

    // Scenario 1 again, with Navarro in Silverton's place: Lopez signs a form
    // with the signature that came off Navarro's ID.
    $result = scenarioVerify($lopez, scenarioRephotograph('navarro.png'));

    scenarioReport(sprintf(
        'scenario 2 — Lopez signs as Navarro: %s → %s @ %.3f',
        $result['status'], $result['matched_user'] ?? '—', $result['score'] ?? -1,
    ));

    expect($result['status'])->toBe('recognized')
        ->and($result['matched_user'])->toBe('Rafael Navarro');
});

// ---------------------------------------------------------------------------
// Accuracy: how far apart are genuine and impostor scores?
// ---------------------------------------------------------------------------

it('scores every signature against every reference and separates genuine from impostor', function () {
    $files = ['lopez.png', 'silverton.png', 'navarro.png'];

    // References: the stored (ink-extracted) form of each signature.
    $candidates = [];
    foreach ($files as $index => $file) {
        $path = SignatureImage::store(scenarioSignatureBytes($file), 'signatures/matrix');
        $candidates[] = ['id' => $index, 'image' => base64_encode(Storage::disk('public')->get($path))];
    }

    // The sidecar reports the threshold it is running with, so this stays
    // honest if SIGNATURE_MATCH_THRESHOLD is tuned.
    $threshold = (float) scenarioIdentify(scenarioSignatureBytes('lopez.png'), $candidates)['threshold'];
    $genuine = [];
    $impostor = [];

    scenarioReport(sprintf('accuracy matrix (probe → score per reference, threshold %.2f)', $threshold));

    foreach ($files as $probeIndex => $probeFile) {
        foreach (['same capture' => scenarioSignatureBytes($probeFile),
            're-photographed' => (string) base64_decode(explode(',', scenarioRephotograph($probeFile), 2)[1])] as $label => $probe) {
            $row = [];
            foreach ($candidates as $candidate) {
                $result = scenarioIdentify($probe, [$candidate]);
                $score = (float) ($result['best']['score'] ?? 0);
                $row[] = sprintf('%s %.3f', substr($files[$candidate['id']], 0, 4), $score);

                if ($candidate['id'] === $probeIndex) {
                    $genuine[] = $score;
                } else {
                    $impostor[] = $score;
                }
            }
            scenarioReport(sprintf('  %-14s %-15s → %s', substr($probeFile, 0, -4), '('.$label.')', implode('  ', $row)));
        }
    }

    $worstGenuine = min($genuine);
    $bestImpostor = max($impostor);
    scenarioReport(sprintf(
        'worst genuine %.3f | best impostor %.3f | margin %.3f',
        $worstGenuine, $bestImpostor, $worstGenuine - $bestImpostor,
    ));

    // Every genuine pairing clears the threshold and every impostor pairing
    // stays under it: the matcher separates these three signatures cleanly.
    expect($worstGenuine)->toBeGreaterThan($threshold)
        ->and($bestImpostor)->toBeLessThan($threshold);
});
