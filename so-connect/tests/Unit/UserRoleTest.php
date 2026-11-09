<?php

use App\Models\User;

it('defines the account role hierarchy explicitly', function () {
    $superAdmin = new User(['user_type' => User::TYPE_SUPERADMIN]);
    $admin = new User(['user_type' => User::TYPE_ADMIN]);
    $organizationUser = new User(['user_type' => User::TYPE_OFFICER]);
    $guest = new User(['user_type' => User::TYPE_GUEST]);

    expect($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($superAdmin->isAdmin())->toBeFalse()
        ->and($superAdmin->isAdministrative())->toBeTrue()
        ->and($admin->isSuperAdmin())->toBeFalse()
        ->and($admin->isAdmin())->toBeTrue()
        ->and($admin->isAdministrative())->toBeTrue()
        ->and($organizationUser->isOrganizationUser())->toBeTrue()
        ->and($organizationUser->isAdministrative())->toBeFalse()
        ->and($guest->isGuest())->toBeTrue()
        ->and($guest->isAdministrative())->toBeFalse();
});
