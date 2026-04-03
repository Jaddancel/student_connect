<div
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-white/[0.05] dark:bg-white/[0.03]">
    <div class="mb-4 flex flex-col gap-3 px-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Requests</h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"
                x-text="'Last 10 ' + $store.dashboardType.current.label.toLowerCase() + ' submitted in the last 24 hours.'">
            </p>
        </div>
        <div
            class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <span x-text="$store.dashboardData.recentRequests.length"></span>
            <span>/ 10 shown</span>
        </div>
    </div>

    <div x-show="$store.dashboardData.error" class="px-6 pb-4 text-sm text-error-600 dark:text-error-500"
        x-text="$store.dashboardData.error"></div>

    <div class="max-w-full overflow-x-auto">
        <div class="h-[420px] overflow-y-auto">
            <table class="w-full min-w-[820px] table-fixed">
                <thead class="border-y border-gray-100 bg-gray-50 dark:border-white/[0.05] dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                            Request ID</th>
                        <th class="px-6 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                            Name / Event Title</th>
                        <th class="px-6 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                            Organization
                        </th>
                        <th class="px-6 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                            Request Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="$store.dashboardData.loading">
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <td colspan="4" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Loading
                                requests...</td>
                        </tr>
                    </template>

                    <template x-if="!$store.dashboardData.loading && !$store.dashboardData.recentRequests.length">
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <td colspan="4" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">No requests in
                                the last 24 hours for this type.</td>
                        </tr>
                    </template>

                    <template x-for="row in $store.dashboardData.recentRequests" :key="row.request_id">
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <td class="px-6 py-3.5 text-theme-sm font-medium text-gray-700 dark:text-gray-300"
                                x-text="'#' + row.request_id"></td>
                            <td class="px-6 py-3.5">
                                <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-300 truncate"
                                    x-text="row.name_or_title"></p>
                            </td>
                            <td class="px-6 py-3.5">
                                <p class="text-theme-sm text-gray-700 dark:text-gray-300 truncate"
                                    x-text="row.request_organization"></p>
                            </td>
                            <td class="px-6 py-3.5 text-theme-sm text-gray-700 dark:text-gray-300"
                                x-text="row.requested_at_label"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
