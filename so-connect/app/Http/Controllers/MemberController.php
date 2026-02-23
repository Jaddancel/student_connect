<?php

namespace App\Http\Controllers;

use App\Services\RequestService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,organization_id'],
        ]);

        $user_id = auth()->id();
        $organization_id = $validated['organization_id'];

        // create a request for approval
        $action = "$user_id|$organization_id";
        $requestService = new RequestService;
        $requestService->createRequest($action, 1);

        return response()->json(['message' => 'Membership registration request submitted successfully']);
    }
}
