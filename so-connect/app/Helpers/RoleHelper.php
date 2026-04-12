<?php

namespace App\Helpers;

use App\Models\User;

class RoleHelper
{
    public static function isUserAdmin($userId)
    {
        // presidents and officers that (should) get the user type 2 automatically
        if (User::find($userId)->user_type == 2) {
            return true;
        }

        return false;
    }
}
