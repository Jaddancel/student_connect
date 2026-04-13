<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Resources\ActionRequestResource;
use App\Http\Resources\ApprovalResource;
use App\Http\Resources\UserResource;
use App\Models\Approval;
use App\Models\Request;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// dashboard pages
Route::get('/sample_dashboard', function () {
    return view('pages.dashboard.ecommerce', ['title' => 'E-commerce Dashboard']);
})->name('sample-dashboard');

Route::get('/dashboard/president', function () {
    return view('pages.dashboard.administrator', ['title' => 'President Dashboard']);
})->middleware('auth')->name('president-dashboard');

Route::get('/dashboard/admin', function () {
    return view('pages.dashboard.administrator', ['title' => 'Administrator Dashboard']);
})->middleware('auth')->name('admin-dashboard');

Route::get('/dashboard/officer', function () {
    return view('pages.dashboard.officer', ['title' => 'Officer Dashboard']);
})->middleware('auth')->name('officer-dashboard');

Route::get('/dashboard/member', function () {
    return view('pages.dashboard.member', ['title' => 'Member Dashboard']);
})->middleware('auth')->name('member-dashboard');

Route::get('/dashboard', [Dashboard::class, 'viewDashboard'])->middleware('auth')->name('dashboard');

// Auth routes.

Route::post('/login', Login::class)->middleware('guest');
Route::post('/logout', Logout::class)->middleware('auth');

// Auth pages.

Route::get('/login', [UserController::class, 'loginPage'])->name('login');

// calender pages
Route::get('/calendar', function () {
    return view('pages.calender', ['title' => 'Calendar']);
})->name('calendar');

// profile pages
Route::get('/profile', function () {
    return view('pages.profile', ['title' => 'Profile']);
})->name('profile');

Route::get('/profile/create', [ProfileController::class, 'profileForm']);

// form pages
Route::get('/form-elements', function () {
    return view('pages.form.form-elements', ['title' => 'Form Elements']);
})->name('form-elements');

Route::get('/form/membership_registration', function (MemberController $controller) {
    return $controller->registration_form(auth()->id());
})->middleware('auth')->name('membership-registration');

Route::post('/register/member', [MemberController::class, 'post'])->middleware('auth');

// tables pages
Route::get('/basic-tables', function () {
    return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
})->name('basic-tables');

// pages

Route::get('/blank', function () {
    return view('pages.blank', ['title' => 'Blank']);
})->name('blank');

// error pages
Route::get('/error-404', function () {
    return view('pages.errors.error-404', ['title' => 'Error 404']);
})->name('error-404');

// chart pages
Route::get('/line-chart', function () {
    return view('pages.chart.line-chart', ['title' => 'Line Chart']);
})->name('line-chart');

Route::get('/bar-chart', function () {
    return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
})->name('bar-chart');

// authentication pages
Route::get('/signin', function () {
    return view('pages.auth.signin', ['title' => 'Sign In']);
})->name('signin');

Route::get('/signup', function () {
    return view('pages.auth.signup', ['title' => 'Sign Up']);
})->name('signup');

// ui elements pages
Route::get('/alerts', function () {
    return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
})->name('alerts');

Route::get('/avatars', function () {
    return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
})->name('avatars');

Route::get('/badge', function () {
    return view('pages.ui-elements.badges', ['title' => 'Badges']);
})->name('badges');

Route::get('/buttons', function () {
    return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
})->name('buttons');

Route::get('/image', function () {
    return view('pages.ui-elements.images', ['title' => 'Images']);
})->name('images');

Route::get('/videos', function () {
    return view('pages.ui-elements.videos', ['title' => 'Videos']);
})->name('videos');

// API routes

Route::get('/api/requests/{actionType}', function (int $actionType) {
    return ActionRequestResource::collection(Request::where('action_type', $actionType)->get());
})->middleware('auth');

Route::get('/api/approvals/{actionType}', function (int $actionType) {
    return ApprovalResource::collection(
        Approval::query()
            ->whereIn('request', Request::query()->select('request_id')->where('action_type', $actionType))
            ->get()
    );
})->middleware('auth');

Route::get('/api/approvals/{status}/{actionType}', function (string $status, int $actionType) {
    if ($status == 'approved') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', false)->whereIn('request', Request::query()->select('request_id')->where('action_type', $actionType))
                ->get()
        );
    } elseif ($status == 'denied') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', true)->whereIn('request', Request::query()->select('request_id')->where('action_type', $actionType))
                ->get()
        );
    } elseif ($status == 'pending') {
        // add things for pending... - Jad
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', null)->whereIn('request', Request::query()->select('request_id')->where('action_type', $actionType))
                ->get()
        );
    }
})->middleware('auth');

Route::get('/api/user/{id}', function (string $id) {
    return User::findOrFail($id)->toResource();
})->middleware('auth');

Route::get('/api/users', function () {
    return UserResource::collection(User::all());
});
