<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function register(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:members',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create a new member record in the database
        $member = Member::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
        ]);

        // Redirect to a success page or return a response
        return redirect()->route('dashboard')->with('success', 'Membership registration successful!');
    }
}
