<?php

use App\Forms\FieldInputPolicy;
use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Http\Controllers\Auth\GoogleLinkController;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

// The payloads OWASP ZAP's SQL Injection rule sent to the public sign-up form.
const ZAP_SQLI_PAYLOADS = [
    "ZAP' AND '1'='1' -- ",
    "ZAP' OR '1'='1' -- ",
    'ZAP AND 1=1 -- ',
    'ZAP AND 1=2 -- ',
    "ZAP'; DROP TABLE users; -- ",
    "1' UNION SELECT user_email, user_password FROM users -- ",
];

function sqliSignUpForm(): Form
{
    $form = Form::query()->create([
        'name' => 'SQLi Sign Up',
        'route_name' => 'sqli-signup-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'is_published' => true,
        'system_function' => SystemFunction::SIGN_UP,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>Sign up</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    foreach ([
        ['first_name', FieldType::TEXT],
        ['last_name', FieldType::TEXT],
        ['contact_number', FieldType::TEXT],
        ['school_year', FieldType::TEXT],
        ['birthday', FieldType::DATE],
        ['student_id', FieldType::ID_SCAN],
        ['age', FieldType::NUMBER],
    ] as $i => [$key, $type]) {
        FormDescription::query()->create([
            'form_id' => $form->id,
            'field_key' => $key,
            'field_label' => Str::headline($key),
            'field_type' => $type,
            'is_required' => false,
            'field_order' => $i + 1,
        ]);
    }

    return $form;
}

it('rejects every ZAP SQL injection payload on every sign-up field before anything is stored', function (string $field, string $payload) {
    $form = sqliSignUpForm();
    $before = FormSubmission::query()->count();

    $this->from(route('forms.render', $form->route_name))
        ->post(route('forms.render.submit', $form->route_name), [$field => $payload])
        ->assertRedirect(route('forms.render', $form->route_name))
        ->assertSessionHasErrors($field);

    expect(FormSubmission::query()->count())->toBe($before);
})->with(['first_name', 'last_name', 'contact_number', 'school_year', 'birthday', 'student_id', 'age'])
    ->with(ZAP_SQLI_PAYLOADS);

it('answers boolean-true and boolean-false payloads identically', function () {
    $form = sqliSignUpForm();
    $url = route('forms.render.submit', $form->route_name);
    $back = route('forms.render', $form->route_name);

    $true = $this->from($back)->post($url, ['birthday' => 'ZAP AND 1=1 -- ']);
    $trueErrors = session('errors')->getBag('default')->toArray();
    $this->flushSession();
    $false = $this->from($back)->post($url, ['birthday' => 'ZAP AND 1=2 -- ']);
    $falseErrors = session('errors')->getBag('default')->toArray();

    expect($true->getStatusCode())->toBe($false->getStatusCode())
        ->and($trueErrors)->toBe($falseErrors);
});

it('allow-lists names, phone numbers and school years by field key', function () {
    $field = fn (string $key) => new FormDescription(['field_key' => $key, 'field_label' => $key, 'field_type' => FieldType::TEXT]);
    $passes = fn (string $key, string $value) => Validator::make(
        [$key => $value],
        [$key => array_merge(FieldType::validationRules(FieldType::TEXT, true), FieldInputPolicy::rulesFor($field($key)))],
    )->passes();

    foreach (['Juan', 'Ma. Clara', "O'Neil", 'Dela Cruz-Santos', 'Peña', 'Jr., Rizal'] as $name) {
        expect($passes('first_name', $name))->toBeTrue("name {$name}");
    }
    foreach (['09171234567', '+63 917 123 4567', '(045) 982-0110', '0917-123-4567 / 0918-765-4321'] as $phone) {
        expect($passes('contact_number', $phone))->toBeTrue("phone {$phone}");
    }
    foreach (['2025-2026', '2025–2026', '2025 - 2026'] as $year) {
        expect($passes('school_year', $year))->toBeTrue("school year {$year}");
    }
    foreach (ZAP_SQLI_PAYLOADS as $payload) {
        expect($passes('first_name', $payload))->toBeFalse()
            ->and($passes('contact_number', $payload))->toBeFalse()
            ->and($passes('school_year', $payload))->toBeFalse();
    }

    // Keys without a known format keep the generic text rules only.
    expect(FieldInputPolicy::rulesFor($field('home_address')))->toBe([])
        ->and(FieldInputPolicy::rulesFor(new FormDescription(['field_key' => 'first_name', 'field_type' => FieldType::SELECT])))->toBe([]);
});

it('rejects control characters in free text', function () {
    $rules = ['v' => FieldType::validationRules(FieldType::TEXTAREA, true)];

    expect(Validator::make(['v' => "line one\nline two\ttabbed"], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['v' => "abc\x00def"], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['v' => "abc\x1b[2J"], $rules)->passes())->toBeFalse();
});

it('uses real prepared statements and refuses stacked queries', function () {
    expect(DB::connection()->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES))->toBeFalse();

    expect(fn () => DB::unprepared('SELECT 1; SELECT 2'))->toThrow(\Illuminate\Database\QueryException::class);

    $payload = "x' OR '1'='1";
    expect(DB::selectOne('select ? as v', [$payload])->v)->toBe($payload);
});

it('only trusts a google_id this session verified through the OAuth popup', function () {
    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());

    expect(GoogleLinkController::isVerified($request, '1234567890'))->toBeFalse();

    GoogleLinkController::rememberVerified($request, '1234567890');

    expect(GoogleLinkController::isVerified($request, '1234567890'))->toBeTrue()
        ->and(GoogleLinkController::isVerified($request, '999'))->toBeFalse()
        ->and(GoogleLinkController::isVerified($request, ''))->toBeFalse();
});

it('answers a rate-limited submission exactly like any other rejected one', function () {
    $form = sqliSignUpForm();
    $url = route('forms.render.submit', $form->route_name);
    $back = route('forms.render', $form->route_name);
    \Illuminate\Support\Facades\RateLimiter::clear('form-submit:127.0.0.1');

    $first = $this->from($back)->post($url, ['birthday' => '1 AND 1=1 -- ']);
    for ($i = 0; $i < 25; $i++) {
        $last = $this->from($back)->post($url, ['birthday' => '1 AND 1=2 -- ']);
    }

    expect($last->getStatusCode())->toBe($first->getStatusCode())
        ->and($last->headers->get('Location'))->toBe($first->headers->get('Location'))
        ->and($last->getContent())->toBe($first->getContent());
    $last->assertSessionHasErrors(['form' => 'Too many submissions. Please wait a minute and try again.']);
});
