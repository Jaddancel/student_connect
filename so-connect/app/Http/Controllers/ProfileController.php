<?php

namespace App\Http\Controllers;

class ProfileController extends Controller
{
    public function profileForm()
    {
        return view('pages.profile.create');
    }
}
