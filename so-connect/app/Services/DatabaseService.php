<?php

namespace App\Services;

use App\Models\Member;

class DatabaseService
{
    public static function processMembershipRequest($action_string)
    {
        $data = explode('|', $action_string);
        $user_id = $data[0];
        $organization_id = $data[1];
        Member::create([
            'user' => $user_id,
            'organization' => $organization_id,
        ]);
    }
}
