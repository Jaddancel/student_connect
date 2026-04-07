<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6"
    x-show="$store.dashboardType.current.code === 7">
    <div class="mb-5 flex items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Role By The Number</h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"
                x-text="'Policy and Security metrics for ' + $store.dashboardData.roleStats.organizationLabel"></p>
        </div>
        <span
            class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400"
            x-text="$store.dashboardData.roleStats.organizationNames.length + ' org(s)'"></span>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400"
                x-text="'Number of admins in ' + $store.dashboardData.roleStats.organizationLabel"></p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90"
                x-text="$store.dashboardData.roleStats.adminsCount"></p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Officers</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90"
                x-text="$store.dashboardData.roleStats.officersCount"></p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Memberships</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90"
                x-text="$store.dashboardData.roleStats.membershipsCount"></p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Pending Role Change Requests</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90"
                x-text="$store.dashboardData.roleStats.pendingRoleChangeRequestsCount"></p>
        </div>
    </div>
</div>
