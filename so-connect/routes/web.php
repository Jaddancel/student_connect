<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\FormView\Membership_Registration;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function () {
    return view('home');
});

Route::get('/dashboard', [\App\Http\Controllers\EventController::class, 'dashboard'])
    ->middleware('auth');

Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');

Route::post('register', [Register::class])
    ->middleware('guest');

Route::get('forms/membership_registration', [Membership_Registration::class, 'view']);

Route::post('forms/membership/register', [MemberController::class, 'register'])
    ->middleware('auth')
    ->name('membership.register');

Route::get('document_request', function () {
    return view('student/document_request');
});

Route::get('forms/document_upload', [\App\Http\Controllers\DocumentController::class, 'uploadForm'])
    ->middleware('auth');

Route::get('my_documents', function () {
    return view('student/my_document');
})->middleware('auth')->name('my_documents');

Route::get('my_organizations', [\App\Http\Controllers\OrganizationController::class, 'user_organizations'])
    ->middleware('auth')
    ->name('my_organizations');

Route::get('my_events', function () {
    return view('student/my_events');
})->middleware('auth')->name('my_events');

Route::get('my_calendar', [\App\Http\Controllers\EventController::class, 'calendar'])
    ->middleware('auth')
    ->name('my_calendar');

Route::post('/logout', Logout::class)
    ->middleware('auth')
    ->name('logout');

Route::view('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::post('login', Login::class)
    ->middleware('guest');

Route::get('/admin/membership_requests', [ApprovalController::class, 'membershipRequests'])
    ->middleware('auth')
    ->name('admin.membership_requests');

Route::get('/admin/forms_management', function () {
    return view('admin.forms_management');
})->middleware('auth')->name('admin.forms_management');

Route::get('/admin/template_management', function () {
    return view('admin.template_management');
})->middleware('auth')->name('admin.template_management');

Route::get('/admin/event_requests', function () {
    return view('admin.event_requests');
})->middleware('auth')->name('admin.event_requests');
