<?php

namespace App\Http\Controllers;

use App\Services\ActionService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    protected function createMembershipRequest(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'organization_id' => 'required|integer',
        ]);

        $validatedData['user_id'] = auth()->user()->getKey();
        (new ActionService)->passAction($validatedData, 0);
    }

    public function post(Request $request)
    {
        $this->createMembershipRequest($request);

        return redirect()->route('forms.membership_registration')->with('success', 'Membership request submitted successfully.');
    }
}
