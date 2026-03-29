<?php

namespace App\Http\Controllers;

use App\Models\Member;
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

        return redirect()->route('my_organizations')->with('success', 'Membership request submitted successfully.');
    }

    public function index($organization_id)
    {
        $members = Member::where('organization', $organization_id)->with(['member_user.profile'])->get();

        return view('admin.list.members', ['members' => $members]);
    }
}
