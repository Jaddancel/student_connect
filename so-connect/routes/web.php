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

Route::get('/student_dashboard', function () {
    return view('student/dashboard');
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

Route::get('document_request', function(){
    return view('student/document_request');
});

Route::get('forms/document_upload', function(){
    return view('forms/upload_document');
});

Route::get('my_documents', function(){
    return view('student/my_document');
})->middleware('auth')->name('my_documents');

Route::get('my_organization', function(){
    return view('student/my_organization');
})->middleware('auth')->name('my_organization');

Route::get('my_events', function(){
    return view('student/my_events');
})->middleware('auth')->name('my_events');

Route::get('my_calendar', function(){
    return view('student/calendar');
})->middleware('auth')->name('my_calendar');

Route::post('/logout' , Logout::class)
->middleware('auth')
->name('logout');

Route::view('/login' , 'auth.login')
->middleware('guest')
->name('login');

Route::post('login', Login::class)
    ->middleware('guest');