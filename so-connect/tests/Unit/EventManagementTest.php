<?php

use App\Models\EventPlan;

it('identifies child event plans as event requests', function () {
    $requestPlan = new EventPlan(['parent_plan_id' => 12]);
    $standalonePlan = new EventPlan(['parent_plan_id' => null]);

    expect($requestPlan->isEventRequest())->toBeTrue()
        ->and($standalonePlan->isEventRequest())->toBeFalse();
});

it('casts event plan form values to their domain types', function () {
    $plan = new EventPlan([
        'activity_types' => ['Seminar', 'Workshop'],
        'extension_services' => '1',
        'related_to_organization' => '0',
        'cosponsor_count' => '3',
    ]);

    expect($plan->activity_types)->toBe(['Seminar', 'Workshop'])
        ->and($plan->extension_services)->toBeTrue()
        ->and($plan->related_to_organization)->toBeFalse()
        ->and($plan->cosponsor_count)->toBe(3);
});
