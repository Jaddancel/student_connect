<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Approval Breakdown</h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"
                x-text="'Status distribution for ' + $store.dashboardType.current.label + ' ' + $store.dashboardData.compareWindowText + '.'">
            </p>
        </div>
    </div>

    <div x-show="$store.dashboardData.loading" class="mt-6 animate-pulse space-y-4">
        <template x-for="index in 3" :key="'approval-skeleton-' + index">
            <div>
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></span>
                    <span class="h-3 w-14 rounded bg-gray-200 dark:bg-gray-700"></span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700"
                        x-bind:style="'width: ' + (40 + (index * 15)) + '%'"></div>
                </div>
            </div>
        </template>
    </div>

    <div x-show="!$store.dashboardData.loading" class="mt-6 space-y-4">
        <div>
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Approved</span>
                <span class="text-success-600 dark:text-success-500"
                    x-text="$store.dashboardData.approvedCount + ' (' + $store.dashboardData.approvalRate + '%)'"></span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-2 rounded-full bg-success-500 transition-all duration-300"
                    x-bind:style="'width: ' + $store.dashboardData.approvalRate + '%'">
                </div>
            </div>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Denied</span>
                <span class="text-error-600 dark:text-error-500"
                    x-text="$store.dashboardData.deniedCount + ' (' + $store.dashboardData.denialRate + '%)'"></span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-2 rounded-full bg-error-500 transition-all duration-300"
                    x-bind:style="'width: ' + $store.dashboardData.denialRate + '%'">
                </div>
            </div>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Pending</span>
                <span class="text-warning-600 dark:text-warning-500" x-text="$store.dashboardData.pendingCount"></span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-2 rounded-full bg-warning-500 transition-all duration-300"
                    x-bind:style="'width: ' + (100 - $store.dashboardData.completionRate) + '%'">
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-3 gap-3" x-show="!$store.dashboardData.loading">
        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90"
                x-text="$store.dashboardData.totalRequests"></p>
        </div>
        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Last 10</p>
            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90"
                x-text="$store.dashboardData.recentRequests.length"></p>
        </div>
        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Window</p>
            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90"
                x-text="$store.dashboardData.windowLabel"></p>
        </div>
    </div>
</div>
