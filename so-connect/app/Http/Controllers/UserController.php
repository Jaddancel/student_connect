<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    //
    public function register(Request $request){
        $incomingFields = $request->validate([
            'name' => ['required', 'min:3', 'max:23', Rule::unique('users', 'name')],
            'email' => ['required', 'min:3', Rule::unique('users', 'email')],
            'password' => 'required'
        ]);


        $incomingFields['password'] = bcrypt($incomingFields['password']);
        $loggedUser = User::create($incomingFields);

        auth()->guard()->login($loggedUser);
        return redirect('/student_dashboard');

    return "Hello from UserController register method";

    }

    public function logout(Request $request){
        auth()->guard()->logout();
        return redirect('/home');
    }


}
