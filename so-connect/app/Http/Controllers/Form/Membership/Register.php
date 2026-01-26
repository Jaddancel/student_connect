<?php

namespace App\Http\Controllers\Form\Membership;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Http\Request;

class Register extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $userId = $request->user()->id;
        $incoming = $request->validate([
            'org_picker' => 'required'
        ]);


        Membership::create([
            'user_id' => $userId,
            'organization_id' => $incoming['org_picker'],
        ]);

        return redirect()->route('/student_dashboard');

    }
}
