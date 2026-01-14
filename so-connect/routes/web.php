<?php

use App\Http\Controllers\Auth\Login;
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

Route::get('/register', function(){
    return view('Auth/register');
});

Route::post('/auth/register' , [UserController::class, 'register']);

Route::post('/auth/logout' , [UserController::class, 'logout']); 

Route::view('/login' , 'auth.login')
->middleware('guest')
->name('login');

Route::post('login', Login::class)
->middleware('guest')

?>