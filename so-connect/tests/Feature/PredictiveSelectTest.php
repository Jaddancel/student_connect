<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

it('renders a reusable accessible predictive select', function () {
    $html = Blade::render(<<<'BLADE'
        <x-common.predictive-select
            name="organization_id"
            selected="2"
            placeholder="All organizations"
            search-placeholder="Search organization…"
            :options="[
                ['value' => 1, 'label' => 'Computer Science Society'],
                ['value' => 2, 'label' => 'Student Council', 'keywords' => 'SSC'],
            ]" />
    BLADE);

    expect($html)
        ->toContain('role="combobox"')
        ->toContain('aria-autocomplete="list"')
        ->toContain('role="listbox"')
        ->toContain('name="organization_id"')
        ->toContain('predictiveSelect');
});

it('uses predictive selection on high-volume admin filters', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)->get(route('admin.request-records.index'))
        ->assertOk()
        ->assertSee('predictiveSelect', false)
        ->assertSee('Search organization', false);
});
