<?php

namespace App\Http\Controllers;

class UserController extends Controller
{
    public function loginPage()
    {
        return redirect()->route('home');
    }
}
