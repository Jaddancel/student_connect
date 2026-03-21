<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\Form\MembershipRegistration;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function () {
    return view('home');
});

Route::get('/dashboard', [EventController::class, 'dashboard'])
    ->middleware('auth');

Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');

Route::post('register', Register::class)
    ->middleware('guest');

Route::post('/profile/create', [ProfileController::class, 'create'])
    ->middleware('auth')
    ->name('profile.make_profile');

Route::view('/profile/create', 'profile.create')
    ->middleware('auth')
    ->name('profile.create');

Route::get('document_request', function () {
    return view('student/document_request');
});

Route::get('forms/document_upload', [DocumentController::class, 'uploadForm'])
    ->middleware('auth');

Route::get('my_documents', function () {
    return view('student/my_document');
})->middleware('auth')->name('my_documents');

Route::get('my_organizations', [OrganizationController::class, 'getUserOrganizations'])
    ->middleware('auth')
    ->name('my_organizations');

Route::get('my_events', function () {
    return view('student/my_events');
})->middleware('auth')->name('my_events');

Route::get('my_calendar', [EventController::class, 'calendar'])
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

Route::get('/admin/membership_requests', [RequestController::class, 'getMembershipRequests'])
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

Route::get('/forms/membership_registration', [MembershipRegistration::class, 'view'])
    ->middleware('auth')
    ->name('forms.membership_registration');

Route::post('/member/register/request', [MemberController::class, 'post'])
    ->middleware('auth')
    ->name('member.register_request');
