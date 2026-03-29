<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'occupation' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:50',
        ]);

        Profile::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'occupation' => $request->input('occupation'),
            'middle_name' => $request->input('middle_name'),
        ]);

        User::where('user_id', auth()->id())->update(['profile_id' => Profile::latest()->first()->profile_id]);

        return redirect('/dashboard');
    }

    public function form()
    {
        return view('profile.create');
    }

    public function view($user_id)
    {   // get profile of user with id $user_id
        $profile = User::where('user_id', $user_id)->first()->profile;

        if (! $profile) {
            return redirect('/dashboard')->with('error', 'Profile not found.');
        }

        return view('profile.view', ['profile' => $profile]);
    }
}
