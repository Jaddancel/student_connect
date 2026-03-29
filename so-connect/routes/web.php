<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
<<<<<<< HEAD
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
=======
<<<<<<< HEAD
=======
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\Form\MembershipRegistration;
use App\Http\Controllers\LandingPage;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestController;
>>>>>>> main
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPage::class, 'view']);
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4

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
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======

Route::get('/officer/membership_requests', [RequestController::class, 'getMembershipRequests'])
    ->middleware('auth')
    ->name('officer.membership_requests');

Route::post('/officer/membership_requests/{membershipRequest}/approve', [ApprovalController::class, 'approveMembershipRequest'])
    ->middleware('auth')
    ->name('officer.membership_requests.approve');

Route::post('/officer/membership_requests/{membershipRequest}/deny', [ApprovalController::class, 'denyMembershipRequest'])
    ->middleware('auth')
    ->name('officer.membership_requests.deny');

Route::get('/admin/forms_management', function () {
    return view('admin.forms_management');
})->middleware('auth')->name('admin.forms_management');

Route::get('/admin/template_management', function () {
    return view('admin.template_management');
})->middleware('auth')->name('admin.template_management');

Route::get('/admin/members/list/{organization_id}', [MemberController::class, 'index'])
    ->middleware('auth')->name('admin.members.list');

Route::get('/admin/event_requests', [RequestController::class, 'getEventRequests'])
    ->middleware('auth')
    ->name('admin.event_requests');

Route::post('/admin/event_requests/{eventRequest}/approve', [ApprovalController::class, 'approveEventRequest'])
    ->middleware('auth')
    ->name('admin.event_requests.approve');

Route::post('/admin/event_requests/{eventRequest}/deny', [ApprovalController::class, 'denyEventRequest'])
    ->middleware('auth')
    ->name('admin.event_requests.deny');

Route::get('/forms/membership_registration', [MembershipRegistration::class, 'view'])
    ->middleware('auth')
    ->name('forms.membership_registration');

Route::get('/forms/event_registration', [EventController::class, 'eventRegistrationForm'])
    ->middleware('auth');

Route::post('/event/create', [EventController::class, 'createEventRequest'])
    ->middleware('auth'
    )->name('events.create');

Route::post('/member/register/request', [MemberController::class, 'post'])
    ->middleware('auth')
    ->name('member.register_request');

// profile/view redirects to profile.view with the authenticated user's ID, while profile/view/{user_id} allows viewing any user's profile by their ID
Route::get('/profile/view', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route('profile.view', ['user_id' => auth()->id()]);
})->name('profile.view.redirect');

Route::get('/profile/view/{user_id}', [ProfileController::class, 'view'])
    ->middleware('auth')
    ->name('profile.view');
>>>>>>> main
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
