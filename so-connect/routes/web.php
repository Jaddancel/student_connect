<?php

use App\Http\Controllers\Admin\TemplateManagerController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\EventController;
use App\Http\Controllers\LandingPage;
use App\Http\Controllers\MembershipRegistrationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PolicySecurityRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestDecisionController;
use App\Http\Controllers\SidebarMenuController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\UserController;
use App\Http\Resources\ActionRequestResource;
use App\Http\Resources\ApprovalResource;
use App\Http\Resources\UserResource;
use App\Models\Approval;
use App\Models\Organization;
use App\Models\Request;
use App\Models\User;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPage::class, 'view'])->name('home');
Route::get('/organizations/{organizationId}/{slug?}', [LandingPage::class, 'organizationFeed'])
    ->whereNumber('organizationId')
    ->name('organization-feed');

// dashboard pages
Route::get('/sample_dashboard', function () {
    return view('pages.dashboard.ecommerce', ['title' => 'E-commerce Dashboard']);
})->name('sample-dashboard');

Route::get('/dashboard/president', function () {
    return view('pages.dashboard.administrator', ['title' => 'President Dashboard']);
})->middleware(['auth', 'dashboard.access:president'])->name('president-dashboard');

Route::get('/dashboard/admin', function () {
    return view('pages.dashboard.administrator', [
        'title' => 'Admin Dashboard',
        'canDecide' => false,
    ]);
})->middleware(['auth', 'admin'])->name('admin-dashboard');

Route::get('/dashboard/officer', function () {
    return view('pages.dashboard.officer', ['title' => 'Officer Dashboard']);
})->middleware(['auth', 'dashboard.access:officer'])->name('officer-dashboard');

Route::get('/dashboard/member', [Dashboard::class, 'memberDashboard'])->middleware('auth')->name('member-dashboard');

Route::get('/dashboard', [Dashboard::class, 'viewDashboard'])->middleware('auth')->name('dashboard');

// Auth routes.

Route::post('/login', Login::class)->middleware('guest');
Route::post('/signup', Register::class)->middleware('guest');
Route::post('/logout', Logout::class)->middleware('auth');

// Auth pages.

Route::get('/login', [UserController::class, 'loginPage'])->name('login');

// calender pages
Route::get('/calendar', function () {
    $user = request()->user();
    $isOfficerOrPresident = Gate::forUser($user)->allows('access-dashboard', 'officer')
        || Gate::forUser($user)->allows('access-dashboard', 'president');
    $canRequestEvent = $isOfficerOrPresident;

    $eventRequestOrganizations = collect();

    if ($canRequestEvent) {
        $eventRequestOrganizations = Organization::query()
            ->join('members as m', 'm.organization', '=', 'organizations.organization_id')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
            ->where('m.user', (int) $user->getKey())
            ->orderBy('od.name')
            ->get([
                'organizations.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as name"),
            ])
            ->unique('organization_id')
            ->values();
    }

    return view('pages.calender', [
        'title' => 'Calendar',
        'canRequestEvent' => $canRequestEvent,
        'eventRequestOrganizations' => $eventRequestOrganizations,
    ]);
})->middleware('auth')->name('calendar');

Route::get('/upcoming-events', [SidebarMenuController::class, 'upcomingEvents'])
    ->middleware('auth')
    ->name('upcoming-events');

Route::get('/register', [MembershipRegistrationController::class, 'create'])
    ->middleware('auth')
    ->name('register');

Route::post('/register', [MembershipRegistrationController::class, 'store'])
    ->middleware('auth')
    ->name('register.store');

Route::get('/download-files', [SidebarMenuController::class, 'downloadFiles'])
    ->middleware('auth')
    ->name('download-files');

Route::get('/download-files/{documentId}/download', [SidebarMenuController::class, 'downloadDocument'])
    ->whereNumber('documentId')
    ->middleware('auth')
    ->name('download-files.download');

Route::get('/approval-requests', [SidebarMenuController::class, 'approvalRequests'])
    ->middleware('auth')
    ->name('approval-requests');

Route::get('/request-forms', [SidebarMenuController::class, 'requestForms'])
    ->middleware('auth')
    ->name('request-forms');

Route::post('/request-forms', [SidebarMenuController::class, 'storeRoleChangeRequest'])
    ->middleware('auth')
    ->name('request-forms.store');

Route::get('/recent-event-requests', [SidebarMenuController::class, 'recentEventRequests'])
    ->middleware('auth')
    ->name('recent-event-requests');

Route::get('/manage-organization', [OrganizationController::class, 'manage'])
    ->middleware('auth')
    ->name('manage-organization');

Route::get('/forms/student-leader-directory', function () {
    return view('pages.form.student-leader-directory', ['title' => 'Directory of Student Leader']);
})->middleware(['auth', 'role.president'])->name('student-leader-directory');

Route::get('/forms/joint-statement', function () {
    return view('pages.form.joint-statement', ['title' => 'Joint Statement of Involvement/Commitment']);
})->middleware(['auth', 'role.president'])->name('joint-statement');


Route::middleware(['auth', 'admin.or.superadmin'])->group(function () {
    Route::get('/admin/templates', [TemplateManagerController::class, 'index'])
        ->name('admin.templates.index');
    Route::get('/admin/templates/{form}/upload', [TemplateManagerController::class, 'showUpload'])
        ->name('admin.templates.upload');
    Route::post('/admin/templates/{form}/upload', [TemplateManagerController::class, 'storeUpload'])
        ->name('admin.templates.store');
    Route::get('/admin/templates/{template}/verify', [TemplateManagerController::class, 'showVerify'])
        ->name('admin.templates.verify');
    Route::post('/admin/templates/{template}/confirm', [TemplateManagerController::class, 'confirm'])
        ->name('admin.templates.confirm');
    Route::delete('/admin/templates/{template}', [TemplateManagerController::class, 'destroy'])
        ->name('admin.templates.destroy');
});

Route::get('/superadmin/profile-requests', [SuperAdminController::class, 'profileRequests'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests');

Route::get('/superadmin/profiles/search', [SuperAdminController::class, 'searchProfiles'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.search');

Route::post('/superadmin/profile-requests/auto-accept-suggested', [SuperAdminController::class, 'autoAcceptSuggestedRequests'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests.auto-accept-suggested');

Route::get('/superadmin/request-types', [SuperAdminController::class, 'requestTypes'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.request-types');

Route::post('/superadmin/request-types', [SuperAdminController::class, 'storeRequestType'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.request-types.store');

Route::get('/superadmin/dashboard-builder', [SuperAdminController::class, 'dashboardBuilder'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard-builder');

Route::post('/superadmin/dashboard-builder', [SuperAdminController::class, 'storeDashboardWidget'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard-builder.store');

Route::patch('/superadmin/dashboard-builder/{widgetId}', [SuperAdminController::class, 'updateDashboardWidget'])
    ->whereNumber('widgetId')
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard-builder.update');

Route::post('/superadmin/profile-requests/{requestId}/decision', [SuperAdminController::class, 'decideProfileRequest'])
    ->whereNumber('requestId')
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests.decision');

Route::get('/superadmin/data-sync', [SuperAdminController::class, 'dataSyncPage'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync');

Route::get('/superadmin/data-sync/export', [SuperAdminController::class, 'exportData'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync.export');

Route::post('/superadmin/data-sync/import-file', [SuperAdminController::class, 'importFromFile'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync.import-file');

Route::post('/superadmin/data-sync/import-api', [SuperAdminController::class, 'importFromApi'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync.import-api');

// profile pages
Route::get('/profile', function () {
    return view('pages.profile', ['title' => 'Profile']);
})->name('profile');

Route::get('/profile/create', [ProfileController::class, 'profileForm'])->middleware('auth')->name('profile.create');
Route::post('/profile/create', [ProfileController::class, 'store'])->middleware('auth')->name('profile.store');

// form pages
Route::get('/form-elements', function () {
    return view('pages.form.form-elements', ['title' => 'Form Elements']);
})->name('form-elements');

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
})->middleware('guest')->name('signup');

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

Route::get('/api/policy-security/requests', [PolicySecurityRequestController::class, 'index'])
    ->middleware('auth');

Route::get('/api/policy-security/approvals', [PolicySecurityRequestController::class, 'approvals'])
    ->middleware('auth');

Route::get('/api/policy-security/stats', [PolicySecurityRequestController::class, 'stats'])
    ->middleware('auth');

$applyAuthorizedOrganizationScope = static function ($query, array $authorizedOrganizationIds) {
    return $query->where(function ($organizationScopeQuery) use ($authorizedOrganizationIds) {
        foreach ($authorizedOrganizationIds as $organizationId) {
            $organizationScopeQuery->orWhere('action', 'like', $organizationId.'|%');
        }
    });
};

Route::get('/api/requests/{actionType}', function (int $actionType) use ($applyAuthorizedOrganizationScope) {
    if ($actionType === 7) {
        return response()->json([
            'message' => 'Use the Policy and Security endpoint for this action type.',
        ], 403);
    }

    $user = request()->user();

    if (! $user) {
        abort(401);
    }

    $hours = max((int) request()->query('hours', 0), 0);
    $limit = min(max((int) request()->query('limit', 0), 0), 200);

    $isAdmin = (int) $user->user_type === 2;

    if ($isAdmin) {
        $query = Request::query()->where('action_type', $actionType)->orderByDesc('requested_at');

        if ($hours > 0) {
            $query->where('requested_at', '>=', now()->subHours($hours));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return ActionRequestResource::collection($query->get());
    }

    $authorizedOrganizationIds = in_array($actionType, [2, 3, 4, 8], true)
        ? OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey())
        : OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

    if (empty($authorizedOrganizationIds)) {
        return ActionRequestResource::collection(collect());
    }

    $query = $applyAuthorizedOrganizationScope(
        Request::query()->where('action_type', $actionType),
        $authorizedOrganizationIds
    )->orderByDesc('requested_at');

    if ($hours > 0) {
        $query->where('requested_at', '>=', now()->subHours($hours));
    }

    if ($limit > 0) {
        $query->limit($limit);
    }

    return ActionRequestResource::collection($query->get());
})->middleware('auth');

Route::post('/api/requests/{requestId}/decision', [RequestDecisionController::class, 'store'])
    ->whereNumber('requestId')
    ->middleware('auth');

Route::post('/api/events/requests', [EventController::class, 'storeEventRequest'])
    ->middleware('auth')
    ->name('api.events.requests.store');

Route::get('/api/organizations', function () {
    $ids = collect(explode(',', (string) request()->query('ids', '')))
        ->map(fn ($id) => (int) trim($id))
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values();

    if ($ids->isEmpty()) {
        return response()->json(['data' => []]);
    }

    $rows = DB::table('organizations as o')
        ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
        ->whereIn('o.organization_id', $ids->all())
        ->select('o.organization_id', 'od.name')
        ->get();

    $nameMap = $ids->mapWithKeys(function ($id) {
        return [(string) $id => 'Unknown Organization'];
    })->all();

    foreach ($rows as $row) {
        $id = (string) $row->organization_id;
        $nameMap[$id] = $row->name ?: 'Unknown Organization';
    }

    return response()->json(['data' => $nameMap]);
})->middleware('auth');

Route::get('/api/approvals/{actionType}', function (int $actionType) use ($applyAuthorizedOrganizationScope) {
    if ($actionType === 7) {
        return response()->json([
            'message' => 'Use the Policy and Security endpoint for this action type.',
        ], 403);
    }

    $user = request()->user();

    if (! $user) {
        abort(401);
    }

    $isAdmin = (int) $user->user_type === 2;

    if ($isAdmin) {
        $allRequestIds = Request::query()->where('action_type', $actionType)->select('request_id');

        return ApprovalResource::collection(
            Approval::query()
                ->whereIn('request', $allRequestIds)
                ->orderByDesc('approved_at')
                ->get()
        );
    }

    $authorizedOrganizationIds = in_array($actionType, [2, 3, 4, 8], true)
        ? OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey())
        : OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

    if (empty($authorizedOrganizationIds)) {
        return ApprovalResource::collection(collect());
    }

    $scopedRequestIds = $applyAuthorizedOrganizationScope(
        Request::query()->where('action_type', $actionType),
        $authorizedOrganizationIds
    )->select('request_id');

    return ApprovalResource::collection(
        Approval::query()
            ->whereIn('request', $scopedRequestIds)
            ->orderByDesc('approved_at')
            ->get()
    );
})->middleware('auth');

Route::get('/api/approvals/{status}/{actionType}', function (string $status, int $actionType) use ($applyAuthorizedOrganizationScope) {
    if ($actionType === 7) {
        return response()->json([
            'message' => 'Use the Policy and Security endpoint for this action type.',
        ], 403);
    }

    $user = request()->user();

    if (! $user) {
        abort(401);
    }

    $isAdmin = (int) $user->user_type === 2;

    if ($isAdmin) {
        $scopedRequestIds = Request::query()->where('action_type', $actionType)->select('request_id');
    } else {
        $authorizedOrganizationIds = in_array($actionType, [2, 3, 4, 8], true)
            ? OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey())
            : OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

        if (empty($authorizedOrganizationIds)) {
            return ApprovalResource::collection(collect());
        }

        $scopedRequestIds = $applyAuthorizedOrganizationScope(
            Request::query()->where('action_type', $actionType),
            $authorizedOrganizationIds
        )->select('request_id');
    }

    if ($status == 'approved') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', false)
                ->whereIn('request', $scopedRequestIds)
                ->get()
        );
    } elseif ($status == 'denied') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', true)
                ->whereIn('request', $scopedRequestIds)
                ->get()
        );
    } elseif ($status == 'pending') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', null)
                ->whereIn('request', $scopedRequestIds)
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

Route::get('/api/events/calendar', [EventController::class, 'calendarEvents'])
    ->middleware('auth')
    ->name('api.events.calendar');

Route::get('/api/superadmin/data/export', [SuperAdminController::class, 'apiExport']);

Route::post('/api/superadmin/data/import', [SuperAdminController::class, 'apiImport'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
