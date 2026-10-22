<?php

use App\Helpers\FormTemplateHelper;
use App\Helpers\NotificationBellHelper;
use App\Models\Request as ActionRequest;

function notificationRequestVisibility(
    ActionRequest $request,
    int $userId,
    bool $isSuperAdmin = false,
    array $officerOrganizationIds = [],
    array $presidentOrganizationIds = [],
): bool {
    $method = new ReflectionMethod(NotificationBellHelper::class, 'canUserSeeRequest');

    return $method->invoke(
        null,
        $request,
        $userId,
        $isSuperAdmin,
        $officerOrganizationIds,
        $presidentOrganizationIds,
    );
}

it('shows a request to its requester but never to a superadmin', function () {
    $request = new ActionRequest([
        'action' => '12|81',
        'action_type' => 1,
        'user' => 81,
    ]);

    expect(notificationRequestVisibility($request, 81))->toBeTrue()
        ->and(notificationRequestVisibility($request, 81, isSuperAdmin: true))->toBeFalse();
});

it('limits membership and organization request notifications to the proper officials', function () {
    $membership = new ActionRequest([
        'action' => '12|81',
        'action_type' => 1,
        'user' => 81,
    ]);
    $event = new ActionRequest([
        'action' => '12|81',
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'user' => 81,
    ]);

    expect(notificationRequestVisibility($membership, 90, officerOrganizationIds: [12]))->toBeTrue()
        ->and(notificationRequestVisibility($membership, 90, presidentOrganizationIds: [12]))->toBeFalse()
        ->and(notificationRequestVisibility($event, 90, officerOrganizationIds: [12]))->toBeFalse()
        ->and(notificationRequestVisibility($event, 90, presidentOrganizationIds: [12]))->toBeTrue();
});
