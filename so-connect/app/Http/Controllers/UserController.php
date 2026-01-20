<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{

    public function logout(Request $request){
        auth()->guard()->logout();
        return redirect('/home');
    }


}
