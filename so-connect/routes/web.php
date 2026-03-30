<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPage::class, 'view']);

Route::get('/home', function () {
    return view('home');
});

Route::post('/logout', Logout::class)
    ->middleware('auth')
    ->name('logout');

Route::view('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::post('login', Login::class)
    ->middleware('guest');
