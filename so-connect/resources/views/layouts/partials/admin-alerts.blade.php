{{-- Proactive admin danger notifications (top-right stack), shown on every
     page load while a piece of setup the whole system depends on is missing.
     Admins and super admins only; each card is dismissible for the page but
     reappears on every load until the underlying setup is done. --}}
@auth
    @php
        $adminAlertIsAdmin = in_array((int) (auth()->user()->user_type ?? 0), [1, 2], true);

        // No active or upcoming semester — the new school year has not been
        // defined yet. Submissions, event plans and scoring made in this gap
        // are not attributed to any scoring period.
        $adminAlertNoSchoolYear = $adminAlertIsAdmin && ! \App\Models\Semester::hasActiveOrUpcoming();

        // No usable New Workplan form — the function is disabled, so officers
        // cannot submit a workplan at all and no workplan request can reach
        // the admin queue.
        $adminAlertWorkplanForm = $adminAlertIsAdmin
            ? \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_WORKPLAN)
            : null;
        $adminAlertNoWorkplanForm = $adminAlertIsAdmin
            && ($adminAlertWorkplanForm === null || ! $adminAlertWorkplanForm->is_published);
    @endphp

    @if ($adminAlertNoSchoolYear || $adminAlertNoWorkplanForm)
        <div class="fixed top-6 right-6 z-[100000] flex w-80 flex-col gap-3">
            @if ($adminAlertNoSchoolYear)
                <x-admin-alert-card
                    title="No active school year"
                    body="No active or upcoming semester covers today. Records made now aren't attributed to a
                          scoring period. Define the new school year to resume."
                    :href="route('admin.semesters.index')"
                    action="Define school year" />
            @endif

            @if ($adminAlertNoWorkplanForm)
                <x-admin-alert-card
                    title="No workplan form"
                    :body="$adminAlertWorkplanForm === null
                        ? 'The New Workplan function has no form yet, so organizations cannot submit a workplan
                           and no workplan request can reach your queue. Create the form to enable it.'
                        : 'The New Workplan form exists but is unpublished, so organizations cannot submit a
                           workplan and no workplan request can reach your queue. Publish it to enable the function.'"
                    :href="$adminAlertWorkplanForm === null
                        ? route('admin.form-builder.create', ['function' => \App\Forms\SystemFunction::NEW_WORKPLAN])
                        : route('admin.form-builder.edit', $adminAlertWorkplanForm)"
                    :action="$adminAlertWorkplanForm === null ? 'Create workplan form' : 'Publish workplan form'" />
            @endif
        </div>
    @endif
@endauth
