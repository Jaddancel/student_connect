<?php

use App\Models\Form;
use App\Models\ScoringCriterion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns to the criterion category section after editing its trigger', function () {
    $admin = recordsUser(2);
    $form = Form::create(['name' => 'Report', 'route_name' => 'report', 'is_active' => true, 'is_published' => true]);
    $criterion = ScoringCriterion::query()->create([
        'key' => 'custom_redirect', 'category_key' => 'cat3', 'label' => 'Redirect me', 'weight' => 1,
        'sort_order' => 1, 'is_system' => false, 'is_active' => true,
    ]);
    $back = route('admin.scoring.rules.index').'#category-cat3';

    $this->actingAs($admin)->get(route('admin.scoring.rules.edit', $criterion))
        ->assertOk()
        ->assertSee('href="'.$back.'"', false);

    $this->actingAs($admin)->putJson(route('admin.scoring.rules.update', $criterion), [
        'workspace' => null,
        'trigger' => [
            'when' => ['source' => 'form_submission', 'form_id' => $form->id, 'status' => 'approved'],
            'if' => null,
            'then' => ['add' => ['kind' => 'const', 'value' => 1]],
        ],
        'enabled' => true,
    ])->assertOk()->assertJson(['redirect' => $back]);

    $this->actingAs($admin)->patch(route('admin.scoring.rules.toggle', $criterion))->assertRedirect($back);

    $this->actingAs($admin)->get(route('admin.scoring.rules.index'))
        ->assertOk()
        ->assertSee('id="category-cat3"', false);
});
