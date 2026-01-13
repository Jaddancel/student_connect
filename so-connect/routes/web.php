<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function(){
    return view('home');
});

Route::get('/student_dashboard', function () {
    return view('student/dashboard');
});

Route::get('/login', function(){
    return view('Auth/login');
});

Route::get('/register', function(){
    return view('Auth/register');
});

Route::post('/auth/register' , [UserController::class, 'register']);

?>