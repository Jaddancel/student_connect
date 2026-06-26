<?php

use App\Http\Controllers\AccomplishmentReportController;
use App\Http\Controllers\ActivityRequestController;
use App\Http\Controllers\Admin\AccomplishmentReportRequestController;
use App\Http\Controllers\Admin\AdminAccountCreationController;
use App\Http\Controllers\Admin\AdminOfficerCreationController;
use App\Http\Controllers\Admin\AdminWorkplanController;
use App\Http\Controllers\Admin\ActivityRequestController as AdminActivityRequestController;
use App\Http\Controllers\Admin\FinancialReportRequestController;
use App\Http\Controllers\Admin\JointStatementRequestController;
use App\Http\Controllers\Admin\OrganizationScoringController;
use App\Http\Controllers\Admin\ProjectRequestController;
use App\Http\Controllers\Admin\RecognitionRequestController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\FormCreationWizardController;
use App\Http\Controllers\Admin\TemplateManagerController;
use App\Http\Controllers\Admin\WorkplanRequestController;
use App\Http\Controllers\OcrScanController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\Auth\SystemSetupController;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\DashboardSearchController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventPlanController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\JointStatementController;
use App\Http\Controllers\LandingPage;
use App\Http\Controllers\MembershipRegistrationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationAccreditationWizardController;
use App\Http\Controllers\OrganizationRecognitionController;
use App\Http\Controllers\PolicySecurityRequestController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectRequestController as UserProjectRequestController;
use App\Http\Controllers\PromotionRequestsPageController;
use App\Http\Controllers\RequestDecisionController;
use App\Http\Controllers\SidebarMenuController;
use App\Http\Controllers\StudentLeaderDirectoryController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkplanController;
use App\Http\Resources\ActionRequestResource;
use App\Http\Resources\ApprovalResource;
use App\Http\Resources\UserResource;
use App\Models\Approval;
use App\Models\Organization;
use App\Models\Request;
use App\Models\Semester;
use App\Models\User;
use App\Models\Workplan;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// First-run system setup (creates the initial superadmin; locked once one exists)
Route::get('/setup', [SystemSetupController::class, 'create'])->name('setup.create');
Route::post('/setup', [SystemSetupController::class, 'store'])->name('setup.store');

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

Route::get('/dashboard/officer', [Dashboard::class, 'officerDashboard'])
    ->middleware(['auth', 'dashboard.access:officer'])->name('officer-dashboard');

Route::get('/dashboard', [Dashboard::class, 'viewDashboard'])->middleware('auth')->name('dashboard');

// Auth routes.

Route::post('/login', Login::class)->middleware('guest');
Route::post('/signup', Register::class)->middleware(['guest', 'form.template:student-leader-directory']);
Route::post('/logout', Logout::class)->middleware('auth');

// Invitation activation (Flows 1, 2, 3)
Route::get('/invitation/verify', [InvitationController::class, 'verify'])
    ->middleware('guest')
    ->name('invitation.verify');

// First-login password wizard (Flow 3 — admin accounts)
Route::get('/change-password', [PasswordChangeController::class, 'show'])
    ->middleware('auth')
    ->name('password.change');

Route::post('/change-password', [PasswordChangeController::class, 'update'])
    ->middleware('auth')
    ->name('password.change.update');

Route::post('/events', [EventController::class, 'store'])
    ->middleware('auth')
    ->name('events.create');

// Auth pages.

Route::get('/login', fn () => redirect(route('home').'#auth'))->name('login');

// calender pages
Route::get('/calendar', function () {
    $user = request()->user();
    $isOfficerOrPresident = Gate::forUser($user)->allows('access-dashboard', 'officer')
        || Gate::forUser($user)->allows('access-dashboard', 'president');
    $canRequestEvent = $isOfficerOrPresident;
    $profileRow = DB::table('users as u')
        ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
        ->where('u.user_id', (int) $user->getKey())
        ->select(['p.first_name', 'p.middle_name', 'p.last_name', 'p.contact_number'])
        ->first();

    $presidentName = $profileRow ? trim(implode(' ', array_filter([
        $profileRow->first_name,
        $profileRow->middle_name,
        $profileRow->last_name,
    ]))) : '';

    $presidentContact = $profileRow?->contact_number ?? '';

    $eventRequestOrganizations = collect();
    $lockedOrgIds = [];
    $allSemesters = collect();
    $workplanStatuses = [];

    if ($canRequestEvent) {
        $eventRequestOrganizations = Organization::query()
            ->join('organization_officers as oo', 'oo.organization', '=', 'organizations.organization_id')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
            ->where('oo.user', (int) $user->getKey())
            ->orderBy('od.name')
            ->get([
                'organizations.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as name"),
            ])
            ->unique('organization_id')
            ->values();

        $activeSemester = Semester::currentlyActive();
        if ($activeSemester && $eventRequestOrganizations->isNotEmpty()) {
            $lockedOrgIds = Workplan::query()
                ->where('semester_id', $activeSemester->semester_id)
                ->whereIn('organization_id', $eventRequestOrganizations->pluck('organization_id'))
                ->whereIn('status', ['finalized', 'archived'])
                ->pluck('organization_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        // Semester data for calendar auto-toggle logic
        $allSemesters = Semester::query()->orderBy('starts_at')->get(['semester_id', 'starts_at', 'vacation_days']);

        if ($eventRequestOrganizations->isNotEmpty() && $allSemesters->isNotEmpty()) {
            $orgIds = $eventRequestOrganizations->pluck('organization_id')->all();
            Workplan::query()
                ->whereIn('organization_id', $orgIds)
                ->whereIn('semester_id', $allSemesters->pluck('semester_id'))
                ->get(['organization_id', 'semester_id', 'status'])
                ->each(function ($wp) use (&$workplanStatuses) {
                    $workplanStatuses[(int) $wp->organization_id][(int) $wp->semester_id] = $wp->status;
                });
        }
    }

    return view('pages.calendar', [
        'title' => 'Calendar',
        'canRequestEvent' => $canRequestEvent,
        'eventRequestOrganizations' => $eventRequestOrganizations,
        'lockedOrgIds' => $lockedOrgIds,
        'presidentName' => $presidentName,
        'presidentContact' => $presidentContact,
        'allSemesters' => $allSemesters,
        'workplanStatuses' => $workplanStatuses,
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
    ->middleware(['auth', 'admin'])
    ->name('request-forms');

Route::post('/request-forms', [SidebarMenuController::class, 'storeRoleChangeRequest'])
    ->middleware(['auth', 'admin'])
    ->name('request-forms.store');

Route::get('/manage-organization', [OrganizationController::class, 'manage'])
    ->middleware('auth')
    ->name('manage-organization');

// Promotion requests management (President + Admin)
Route::get('/promotion-requests', [PromotionRequestsPageController::class, 'index'])
    ->middleware(['auth', 'admin'])->name('promotion-requests');
Route::get('/promotion-requests/{requestId}', [PromotionRequestsPageController::class, 'show'])
    ->whereNumber('requestId')->middleware(['auth', 'admin'])->name('promotion-requests.show');
Route::get('/promotion-requests/submissions/{submissionId}/confirm', [PromotionRequestsPageController::class, 'confirmation'])
    ->whereNumber('submissionId')->middleware(['auth', 'admin'])->name('promotion-requests.confirmation');
Route::post('/promotion-requests/submissions/{submissionId}/confirm', [PromotionRequestsPageController::class, 'confirm'])
    ->whereNumber('submissionId')->middleware(['auth', 'admin'])->name('promotion-requests.confirm');

Route::middleware(['auth', 'officer.or.admin'])->group(function () {
    Route::get('/event-plans', [EventPlanController::class, 'index'])
        ->name('event-plans');
    Route::post('/event-plans/{id}/create-event', [EventPlanController::class, 'storeEvent'])
        ->whereNumber('id')
        ->name('event-plans.create-event');
    Route::patch('/event-plans/{id}/junk', [EventPlanController::class, 'junk'])
        ->whereNumber('id')
        ->name('event-plans.junk');
    Route::patch('/event-plans/{id}/revise', [EventPlanController::class, 'revise'])
        ->whereNumber('id')
        ->name('event-plans.revise');

    // Organization accreditation wizard (replaces single-page org recognition GET entry)
    Route::get('/forms/organization-recognition', [OrganizationAccreditationWizardController::class, 'showStep1'])
        ->middleware('form.template:organization-recognition')->name('organization-recognition');
    Route::post('/forms/organization-recognition', [OrganizationRecognitionController::class, 'store'])
        ->middleware('form.template:organization-recognition')->name('organization-recognition.store');
    Route::post('/forms/accreditation/step1', [OrganizationAccreditationWizardController::class, 'saveStep1'])
        ->middleware('form.template:organization-recognition')->name('accreditation.wizard.step1.save');
    Route::get('/forms/accreditation/step2', [OrganizationAccreditationWizardController::class, 'showStep2'])
        ->middleware('form.template:organization-recognition')->name('accreditation.wizard.step2');
    Route::post('/forms/accreditation/step2', [OrganizationAccreditationWizardController::class, 'saveStep2'])
        ->middleware('form.template:organization-recognition')->name('accreditation.wizard.step2.save');
    Route::get('/forms/accreditation/step3', [OrganizationAccreditationWizardController::class, 'showStep3'])
        ->middleware('form.template:organization-recognition')->name('accreditation.wizard.step3');
    Route::post('/forms/accreditation/store', [OrganizationAccreditationWizardController::class, 'store'])
        ->middleware('form.template:organization-recognition')->name('accreditation.wizard.store');

    Route::get('/forms/accomplishment-report', [AccomplishmentReportController::class, 'index'])
        ->middleware('form.template:accomplishment-report')->name('accomplishment-report');
    Route::post('/forms/accomplishment-report', [AccomplishmentReportController::class, 'store'])
        ->middleware('form.template:accomplishment-report')->name('accomplishment-report.store');

    Route::get('/forms/activity-request', [ActivityRequestController::class, 'index'])
        ->middleware('form.template:activity-request')->name('activity-request');
    Route::get('/forms/activity-request/waiver-document', [ActivityRequestController::class, 'waiverDocument'])
        ->name('activity-request.waiver');
    Route::post('/forms/activity-request', [ActivityRequestController::class, 'store'])
        ->middleware('form.template:activity-request')->name('activity-request.store');

    Route::get('/forms/project-request', [UserProjectRequestController::class, 'index'])
        ->middleware('form.template:project-request')->name('project-request');
    Route::post('/forms/project-request', [UserProjectRequestController::class, 'store'])
        ->middleware('form.template:project-request')->name('project-request.store');

    Route::get('/forms/workplan/{workplan_id}', [WorkplanController::class, 'review'])
        ->whereNumber('workplan_id')
        ->middleware('form.template:workplan')->name('workplan.review');
    Route::post('/forms/workplan/{workplan_id}/generate', [WorkplanController::class, 'generatePdf'])
        ->whereNumber('workplan_id')
        ->middleware('form.template:workplan')->name('workplan.generate');
    Route::get('/forms/workplan/{workplan_id}/download', [WorkplanController::class, 'downloadPdf'])
        ->whereNumber('workplan_id')
        ->middleware('form.template:workplan')->name('workplan.download');

    Route::patch('/workplans/{workplan_id}/finalize', [EventPlanController::class, 'finalize'])
        ->whereNumber('workplan_id')
        ->name('workplans.finalize');

    Route::get('/forms/financial-report', [FinancialReportController::class, 'index'])
        ->middleware('form.template:financial-report')->name('financial-report');
    Route::post('/forms/financial-report', [FinancialReportController::class, 'store'])
        ->middleware('form.template:financial-report')->name('financial-report.store');
});

Route::get('/forms/student-leader-directory', [StudentLeaderDirectoryController::class, 'index'])
    ->middleware('form.template:student-leader-directory')->name('student-leader-directory');
Route::post('/forms/student-leader-directory', [StudentLeaderDirectoryController::class, 'store'])
    ->middleware('form.template:student-leader-directory')->name('student-leader-directory.store');

Route::get('/forms/joint-statement', [JointStatementController::class, 'index'])
    ->middleware(['auth', 'role.officer', 'form.template:joint-statement'])->name('joint-statement');
Route::post('/forms/joint-statement', [JointStatementController::class, 'store'])
    ->middleware(['auth', 'role.officer', 'form.template:joint-statement'])->name('joint-statement.store');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::patch('/posts/{postId}', [PostController::class, 'update'])->whereNumber('postId')->name('posts.update');
    Route::delete('/posts/{postId}', [PostController::class, 'destroy'])->whereNumber('postId')->name('posts.destroy');

    Route::get('/admin/semesters', [SemesterController::class, 'index'])->name('admin.semesters.index');
    Route::post('/admin/semesters', [SemesterController::class, 'store'])->name('admin.semesters.store');
    Route::get('/admin/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('admin.semesters.edit');
    Route::patch('/admin/semesters/{semester}', [SemesterController::class, 'update'])->name('admin.semesters.update');

    Route::get('/admin/activity-requests', [AdminActivityRequestController::class, 'index'])
        ->name('admin.activity-requests.index');
    Route::get('/admin/activity-requests/{requestId}', [AdminActivityRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.activity-requests.show');
    Route::post('/admin/activity-requests/{requestId}/decide', [AdminActivityRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.activity-requests.decide');

    Route::get('/admin/project-requests', [ProjectRequestController::class, 'index'])
        ->name('admin.project-requests.index');
    Route::get('/admin/project-requests/{requestId}', [ProjectRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.project-requests.show');
    Route::post('/admin/project-requests/{requestId}/decide', [ProjectRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.project-requests.decide');

    Route::get('/admin/joint-statement-requests', [JointStatementRequestController::class, 'index'])
        ->name('admin.joint-statement-requests.index');
    Route::get('/admin/joint-statement-requests/{requestId}', [JointStatementRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.joint-statement-requests.show');
    Route::post('/admin/joint-statement-requests/{requestId}/decide', [JointStatementRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.joint-statement-requests.decide');
    Route::post('/admin/joint-statement-requests/{requestId}/generate', [JointStatementRequestController::class, 'generate'])
        ->whereNumber('requestId')
        ->name('admin.joint-statement-requests.generate');

    Route::get('/admin/workplans', [AdminWorkplanController::class, 'index'])
        ->name('admin.workplans.index');

    Route::get('/admin/accomplishment-report-requests', [AccomplishmentReportRequestController::class, 'index'])
        ->name('admin.accomplishment-report-requests.index');
    Route::get('/admin/accomplishment-report-requests/{requestId}', [AccomplishmentReportRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.accomplishment-report-requests.show');
    Route::post('/admin/accomplishment-report-requests/{requestId}/decide', [AccomplishmentReportRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.accomplishment-report-requests.decide');

    Route::get('/admin/financial-report-requests', [FinancialReportRequestController::class, 'index'])
        ->name('admin.financial-report-requests.index');
    Route::get('/admin/financial-report-requests/{requestId}', [FinancialReportRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.financial-report-requests.show');
    Route::post('/admin/financial-report-requests/{requestId}/decide', [FinancialReportRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.financial-report-requests.decide');

    Route::get('/admin/recognition-requests', [RecognitionRequestController::class, 'index'])
        ->name('admin.recognition-requests.index');
    Route::get('/admin/recognition-requests/{requestId}', [RecognitionRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.recognition-requests.show');
    Route::post('/admin/recognition-requests/{requestId}/decide', [RecognitionRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.recognition-requests.decide');

    Route::get('/admin/workplan-requests', [WorkplanRequestController::class, 'index'])
        ->name('admin.workplan-requests.index');
    Route::get('/admin/workplan-requests/{requestId}', [WorkplanRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.workplan-requests.show');
    Route::post('/admin/workplan-requests/{requestId}/decide', [WorkplanRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.workplan-requests.decide');

    Route::get('/admin/scoring', [OrganizationScoringController::class, 'index'])
        ->name('admin.scoring.index');
    Route::get('/admin/scoring/rankings', [OrganizationScoringController::class, 'rankings'])
        ->name('admin.scoring.rankings');
    Route::get('/admin/scoring/create', [OrganizationScoringController::class, 'create'])
        ->name('admin.scoring.create');
    Route::post('/admin/scoring', [OrganizationScoringController::class, 'store'])
        ->name('admin.scoring.store');
    Route::get('/admin/scoring/{id}/edit', [OrganizationScoringController::class, 'edit'])
        ->whereNumber('id')
        ->name('admin.scoring.edit');
    Route::put('/admin/scoring/{id}', [OrganizationScoringController::class, 'update'])
        ->whereNumber('id')
        ->name('admin.scoring.update');

    Route::get('/admin/export', [ExportController::class, 'adminIndex'])
        ->name('admin.export');
    Route::get('/admin/export/org-data/json', [ExportController::class, 'adminExportOrgDataJson'])
        ->name('admin.export.org-data.json');
    Route::get('/admin/export/org-data/print', [ExportController::class, 'adminExportOrgDataPrint'])
        ->name('admin.export.org-data.print');
    Route::get('/admin/export/request-records/json', [ExportController::class, 'adminExportRequestRecordsJson'])
        ->name('admin.export.request-records.json');
    Route::get('/admin/export/request-records/print', [ExportController::class, 'adminExportRequestRecordsPrint'])
        ->name('admin.export.request-records.print');
    Route::get('/admin/export/org-data/xlsx', [ExportController::class, 'adminExportOrgDataXlsx'])
        ->name('admin.export.org-data.xlsx');
    Route::get('/admin/export/request-records/xlsx', [ExportController::class, 'adminExportRequestRecordsXlsx'])
        ->name('admin.export.request-records.xlsx');

    Route::get('/admin/officers/create', [AdminOfficerCreationController::class, 'create'])
        ->name('admin.officers.create');
    Route::post('/admin/officers/create', [AdminOfficerCreationController::class, 'store'])
        ->name('admin.officers.store');
});

Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware('auth')
    ->name('documents.index');

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
    Route::get('/admin/templates/field-reference', [TemplateManagerController::class, 'fieldReference'])
        ->name('admin.templates.field-reference');

    // Form Creation Wizard
    Route::prefix('admin/form-wizard')->name('admin.form-wizard.')->group(function () {
        Route::get('/', [FormCreationWizardController::class, 'showStart'])->name('start');
        Route::post('/upload', [FormCreationWizardController::class, 'storeUpload'])->name('upload');
        Route::get('/meta', [FormCreationWizardController::class, 'showMeta'])->name('meta');
        Route::post('/meta', [FormCreationWizardController::class, 'saveMeta'])->name('meta.save');
        Route::get('/ai-review', [FormCreationWizardController::class, 'showAiReview'])->name('ai-review');
        Route::get('/ai-status', [FormCreationWizardController::class, 'aiStatus'])->name('ai-status');
        Route::post('/ai-review', [FormCreationWizardController::class, 'confirmAiReview'])->name('ai-confirm');
        Route::get('/revise', [FormCreationWizardController::class, 'showRevise'])->name('revise');
        Route::post('/revise', [FormCreationWizardController::class, 'saveRevise'])->name('revise.save');
        Route::get('/final', [FormCreationWizardController::class, 'showFinal'])->name('final');
        Route::post('/confirm', [FormCreationWizardController::class, 'confirm'])->name('confirm');
        Route::delete('/discard', [FormCreationWizardController::class, 'discard'])->name('discard');
    });
});

// OCR scan (auth OR guest-allowed — enforced inside the controller).
Route::post('/api/forms/{form}/scan', [OcrScanController::class, 'store'])
    ->name('api.forms.scan');
Route::get('/api/form-scans/{scan}/result', [OcrScanController::class, 'result'])
    ->name('api.form-scans.result');

Route::get('/superadmin/dashboard', [SuperAdminController::class, 'monitoringDashboard'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard');

Route::get('/superadmin/profile-requests', [SuperAdminController::class, 'profileRequests'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests');

Route::get('/superadmin/profiles/search', [SuperAdminController::class, 'searchProfiles'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.search');

Route::post('/superadmin/profile-requests/auto-accept-suggested', [SuperAdminController::class, 'autoAcceptSuggestedRequests'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests.auto-accept-suggested');

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

Route::post('/superadmin/officer-account-requests/{requestId}/decision', [SuperAdminController::class, 'decideOfficerAccountRequest'])
    ->whereNumber('requestId')
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.officer-account-requests.decision');

Route::get('/superadmin/backups', [DatabaseBackupController::class, 'index'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.backups');

Route::post('/superadmin/backups/run', [DatabaseBackupController::class, 'run'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.backups.run');

Route::get('/superadmin/backups/{file}/download', [DatabaseBackupController::class, 'download'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.backups.download');

Route::delete('/superadmin/backups/{file}', [DatabaseBackupController::class, 'destroy'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.backups.destroy');

Route::post('/superadmin/backups/restore', [DatabaseBackupController::class, 'restore'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.backups.restore');

Route::get('/superadmin/export', [ExportController::class, 'index'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export');

Route::get('/superadmin/export/org-data/json', [ExportController::class, 'exportOrgDataJson'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.org-data.json');

Route::get('/superadmin/export/org-data/print', [ExportController::class, 'exportOrgDataPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.org-data.print');

Route::get('/superadmin/export/request-records/json', [ExportController::class, 'exportRequestRecordsJson'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.request-records.json');

Route::get('/superadmin/export/request-records/print', [ExportController::class, 'exportRequestRecordsPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.request-records.print');

Route::get('/superadmin/export/login-logs/json', [ExportController::class, 'exportLoginLogsJson'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.login-logs.json');

Route::get('/superadmin/export/login-logs/print', [ExportController::class, 'exportLoginLogsPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.login-logs.print');

Route::get('/superadmin/export/org-data/xlsx', [ExportController::class, 'exportOrgDataXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.org-data.xlsx');

Route::get('/superadmin/export/request-records/xlsx', [ExportController::class, 'exportRequestRecordsXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.request-records.xlsx');

Route::get('/superadmin/export/login-logs/xlsx', [ExportController::class, 'exportLoginLogsXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.login-logs.xlsx');

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

Route::get('/superadmin/accounts/create', [AdminAccountCreationController::class, 'create'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.accounts.create');

Route::post('/superadmin/accounts/create', [AdminAccountCreationController::class, 'store'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.accounts.store');

Route::get('/superadmin/scoring/audit', [OrganizationScoringController::class, 'audit'])
    ->middleware(['auth', 'superadmin'])
    ->name('admin.scoring.audit');

Route::get('/superadmin/scoring/audit/print', [OrganizationScoringController::class, 'auditPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('admin.scoring.audit.print');

Route::get('/superadmin/scoring/audit/xlsx', [OrganizationScoringController::class, 'auditXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('admin.scoring.audit.xlsx');

Route::get('/superadmin/profiles', [SuperAdminController::class, 'profiles'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles');

Route::get('/superadmin/profiles/{id}/edit', [SuperAdminController::class, 'editProfile'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.edit');

Route::patch('/superadmin/profiles/{id}', [SuperAdminController::class, 'updateProfile'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.update');

// profile pages
Route::get('/profile', function () {
    $user = auth()->user();
    if ($user && ! $user->profile()->exists()) {
        return redirect()->route('profile.create');
    }

    return view('pages.profile', ['title' => 'Profile']);
})->middleware('auth')->name('profile');

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
Route::get('/signin', fn () => redirect()->route('home'))->name('signin');

Route::get('/signup', [StudentLeaderDirectoryController::class, 'index'])
    ->middleware(['guest', 'form.template:student-leader-directory'])->name('signup');

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

Route::post('/api/requests/{requestId}/approve-with-signatures', [RequestDecisionController::class, 'approveWithSignatures'])
    ->whereNumber('requestId')
    ->middleware('auth')
    ->name('requests.approve-with-signatures');

Route::post('/api/events/requests', [EventController::class, 'storeEventPlanRequest'])
    ->middleware('auth')
    ->name('api.events.requests.store');

Route::post('/api/events/direct-request', [EventController::class, 'storeDirectEventRequest'])
    ->middleware('auth')
    ->name('api.events.direct-request.store');

Route::get('/api/organizations/{organizationId}/officers', [EventController::class, 'organizationOfficers'])
    ->whereNumber('organizationId')
    ->middleware('auth')
    ->name('api.organizations.officers');

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

Route::get('/api/dashboard-search', [DashboardSearchController::class, 'index'])
    ->middleware('auth')
    ->name('api.dashboard-search');

Route::get('/api/superadmin/data/export', [SuperAdminController::class, 'apiExport']);

Route::post('/api/superadmin/data/import', [SuperAdminController::class, 'apiImport'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
