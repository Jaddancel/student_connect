<?php

use App\Forms\PdfTemplateRenderer;
use App\Forms\TemplateHtmlSanitizer;
use App\Models\Form\FormDescription;
use App\Models\Profile;
use Illuminate\Support\Collection;

it('replaces field tokens with submission values by key', function () {
    $fields = new Collection([
        new FormDescription(['field_key' => 'full_name', 'field_label' => 'Full Name', 'field_type' => 'text']),
        new FormDescription(['field_key' => 'choice', 'field_label' => 'Choice', 'field_type' => 'select',
            'field_options' => ['options' => [['value' => 'a', 'label' => 'Apple']]]]),
    ]);

    $html = '<p>Hi <span class="field-token" data-field="full_name" contenteditable="false">Full Name</span>, '
        .'you picked <span class="field-token" data-field="choice" contenteditable="false">Choice</span>.</p>';

    $out = (new PdfTemplateRenderer())->render($html, ['full_name' => 'Juan', 'choice' => 'a'], $fields);

    expect($out)->toContain('Hi Juan,');
    expect($out)->toContain('you picked Apple.');
    expect($out)->not->toContain('data-field');
});

it('renders a deleted field token as an empty placeholder, not an error', function () {
    $fields = new Collection([]); // field was removed

    $html = '<p>Value: <span class="field-token" data-field="gone" contenteditable="false">Gone</span></p>';

    $out = (new PdfTemplateRenderer())->render($html, [], $fields);

    expect($out)->toContain('Value:');
    expect($out)->not->toContain('data-field');
});

it('renders a data-universal token from the submitter profile', function () {
    $fields = new Collection([]); // universal tokens need no form field
    $profile = new Profile(['first_name' => 'Juan', 'student_id' => '21-1234-567']);

    $html = '<p>Name: <span class="field-token" data-universal="first_name" contenteditable="false">First Name</span>, '
        .'ID: <span class="field-token" data-universal="student_id" contenteditable="false">Student ID</span></p>';

    $out = (new PdfTemplateRenderer())->render($html, [], $fields, null, $profile);

    expect($out)->toContain('Name: Juan,')
        ->and($out)->toContain('ID: 21-1234-567')
        ->and($out)->not->toContain('data-universal');
});

it('renders data-universal tokens empty when there is no profile', function () {
    $fields = new Collection([]);

    $html = '<p>Name: <span class="field-token" data-universal="first_name" contenteditable="false">First Name</span>.</p>';

    $out = (new PdfTemplateRenderer())->render($html, [], $fields, null, null);

    expect($out)->toContain('Name: .')
        ->and($out)->not->toContain('data-universal');
});

it('resolves field and universal tokens together', function () {
    $fields = new Collection([
        new FormDescription(['field_key' => 'reason', 'field_label' => 'Reason', 'field_type' => 'text']),
    ]);
    $profile = new Profile(['first_name' => 'Ana']);

    $html = '<p><span data-universal="first_name">First</span> — <span data-field="reason">Reason</span></p>';

    $out = (new PdfTemplateRenderer())->render($html, ['reason' => 'field trip'], $fields, null, $profile);

    expect($out)->toContain('Ana — field trip')
        ->and($out)->not->toContain('data-universal')
        ->and($out)->not->toContain('data-field');
});

it('keeps data-universal through sanitization', function () {
    $dirty = '<p><span class="field-token" data-universal="first_name" onclick="x()" contenteditable="false">First Name</span></p>';

    $clean = TemplateHtmlSanitizer::sanitize($dirty);

    expect($clean)->toContain('data-universal="first_name"')
        ->and($clean)->not->toContain('onclick');
});

it('sanitizes template html to the allowed tag set', function () {
    $dirty = '<h1>Title</h1><script>alert(1)</script><p style="text-align: center; color: red">'
        .'<b>ok</b> <span class="field-token junk" data-field="k" onclick="x()">K</span></p>'
        .'<table><tr><td>strip me</td></tr></table>';

    $clean = TemplateHtmlSanitizer::sanitize($dirty);

    expect($clean)->toContain('<h1>Title</h1>');
    expect($clean)->not->toContain('script');
    expect($clean)->not->toContain('alert');
    expect($clean)->not->toContain('onclick');
    expect($clean)->not->toContain('color: red');
    expect($clean)->toContain('text-align: center');
    expect($clean)->toContain('data-field="k"');
    expect($clean)->toContain('class="field-token"'); // junk class dropped
    expect($clean)->toContain('strip me'); // table unwrapped, text kept
    expect($clean)->not->toContain('<table');
});

it('preserves font-size and font-family styling through sanitization', function () {
    $dirty = '<p><span style="font-size: 18px; font-family: Arial, sans-serif; color: blue">Big</span></p>';

    $clean = TemplateHtmlSanitizer::sanitize($dirty);

    expect($clean)->toContain('font-size: 18px');
    expect($clean)->toContain('font-family: Arial, sans-serif');
    expect($clean)->not->toContain('color: blue');
});
