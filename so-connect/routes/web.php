<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\FormView\Membership_Registration;
use App\Http\Controllers\Form\Membership\Register as MembershipRegister;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function(){
    return view('home');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::view('/register' , 'auth.register')
->middleware('guest')
->name('register');

Route::post('register', [Register::class])
->middleware('guest');

Route::get('forms/membership_registration', [Membership_Registration::class, 'view']);

Route::post('forms/membership/register', MembershipRegister::class)
    ->middleware('auth')
    ->name('membership.register');

Route::post('/logout' , Logout::class)
->middleware('auth')
->name('logout');

Route::view('/login' , 'auth.login')
->middleware('guest')
->name('login');

Route::post('login', Login::class)
    ->middleware('guest');