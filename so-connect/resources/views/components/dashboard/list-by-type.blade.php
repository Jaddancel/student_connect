<div
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-white/[0.05] dark:bg-white/[0.03]">
    <div class="mb-4 flex flex-col gap-3 px-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Requests</h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"
                x-text="'Last 10 ' + $store.dashboardType.current.label.toLowerCase() + ' submitted ' + $store.dashboardData.listWindowText + '.'">
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
            <table class="w-full min-w-[940px] table-fixed">
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
                        <th class="px-6 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                            Status</th>
                        <th class="px-6 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                            Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="$store.dashboardData.loading">
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Loading
                                requests...</td>
                        </tr>
                    </template>

                    <template x-if="!$store.dashboardData.loading && !$store.dashboardData.recentRequests.length">
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
                                x-text="'No requests ' + $store.dashboardData.listWindowText + ' for this type.'"></td>
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
                            <td class="px-6 py-3.5">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    x-bind:class="{
                                        'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400': row
                                            .approval_status === 'pending',
                                        'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400': row
                                            .approval_status === 'approved',
                                        'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400': row
                                            .approval_status === 'rejected',
                                    }"
                                    x-text="row.approval_status_label"></span>
                            </td>
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-success-600 text-white transition hover:bg-success-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        aria-label="Approve request" title="Approve"
                                        x-bind:disabled="$store.dashboardData.isDecisionLoading(row.request_id) || row
                                            .approval_status === 'approved'"
                                        x-on:click="$store.dashboardData.decideRequest(row.request_id, 'approve')">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                            class="h-4 w-4">
                                            <path fill-rule="evenodd"
                                                d="M16.704 5.29a1 1 0 0 1 0 1.415l-7.32 7.32a1 1 0 0 1-1.415 0l-3.673-3.674a1 1 0 1 1 1.414-1.414l2.966 2.966 6.613-6.612a1 1 0 0 1 1.415 0Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <button type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-error-600 text-white transition hover:bg-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        aria-label="Reject request" title="Reject"
                                        x-bind:disabled="$store.dashboardData.isDecisionLoading(row.request_id) || row
                                            .approval_status === 'rejected' || row.approval_status === 'approved'"
                                        x-on:click="$store.dashboardData.decideRequest(row.request_id, 'reject')">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                            class="h-4 w-4">
                                            <path fill-rule="evenodd"
                                                d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
