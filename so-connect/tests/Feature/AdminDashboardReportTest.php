<?php

use App\Models\LoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets admins and superadmins view the dashboard report', function () {
    $admin = recordsUser(2);
    $superAdmin = recordsUser(1);

    $this->actingAs($admin)
        ->get(route('dashboard-reports.index'))
        ->assertOk()
        ->assertSee('Executive Dashboard Report');

    $this->actingAs($superAdmin)
        ->get(route('dashboard-reports.index'))
        ->assertOk()
        ->assertSee('Executive Dashboard Report');
});

it('exports the dashboard report in csv, excel, and pdf and records audit entries', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->get(route('dashboard-reports.export.csv'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $this->actingAs($admin)
        ->get(route('dashboard-reports.export.excel'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($admin)
        ->get(route('dashboard-reports.export.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(LoginLog::whereIn('interaction', [
        'DASHBOARD_REPORT_VIEW',
        'DASHBOARD_REPORT_EXPORT_CSV',
        'DASHBOARD_REPORT_EXPORT_EXCEL',
        'DASHBOARD_REPORT_EXPORT_PDF',
    ])->count())->toBeGreaterThanOrEqual(4);
});

it('blocks non-admins from the dashboard report', function () {
    $member = recordsUser(3);

    $this->actingAs($member)
        ->get(route('dashboard-reports.index'))
        ->assertForbidden();
});
