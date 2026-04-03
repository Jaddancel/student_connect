<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-center w-12 h-12 bg-gray-100 rounded-xl dark:bg-gray-800">
            <svg class="fill-gray-800 dark:fill-white/90" width="24" height="24" viewBox="0 0 24 24" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M7.25 4C5.45507 4 4 5.45508 4 7.25V16.75C4 18.5449 5.45508 20 7.25 20H16.75C18.5449 20 20 18.5449 20 16.75V7.25C20 5.45507 18.5449 4 16.75 4H7.25ZM8.5 9.25C8.5 8.83579 8.83579 8.5 9.25 8.5H14.75C15.1642 8.5 15.5 8.83579 15.5 9.25C15.5 9.66421 15.1642 10 14.75 10H9.25C8.83579 10 8.5 9.66421 8.5 9.25ZM8.5 12.75C8.5 12.3358 8.83579 12 9.25 12H14.75C15.1642 12 15.5 12.3358 15.5 12.75C15.5 13.1642 15.1642 13.5 14.75 13.5H9.25C8.83579 13.5 8.5 13.1642 8.5 12.75ZM9.25 15.5C8.83579 15.5 8.5 15.8358 8.5 16.25C8.5 16.6642 8.83579 17 9.25 17H12C12.4142 17 12.75 16.6642 12.75 16.25C12.75 15.8358 12.4142 15.5 12 15.5H9.25Z"
                    fill="" />
            </svg>
        </div>

        <div class="flex items-end justify-between mt-5">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400"
                    x-text="$store.dashboardType.current.label + ' in Last 24h'"></span>
                <h4 class="mt-2 font-bold text-gray-800 text-title-sm dark:text-white/90"
                    x-text="$store.dashboardData.totalRequests"></h4>
            </div>

            <span
                class="flex items-center gap-1 rounded-full bg-brand-50 py-0.5 pl-2 pr-2.5 text-sm font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                <span x-text="$store.dashboardData.recentRequests.length"></span>
                <span>shown</span>
            </span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-center w-12 h-12 bg-gray-100 rounded-xl dark:bg-gray-800">
            <svg class="fill-gray-800 dark:fill-white/90" width="24" height="24" viewBox="0 0 24 24"
                fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M12 3C7.02943 3 3 7.02943 3 12C3 16.9706 7.02943 21 12 21C16.9706 21 21 16.9706 21 12C21 7.02943 16.9706 3 12 3ZM12.75 7.5C12.75 7.08579 12.4142 6.75 12 6.75C11.5858 6.75 11.25 7.08579 11.25 7.5V12.3107C11.25 12.5096 11.3299 12.7002 11.4718 12.8411L14.1218 15.4706C14.4158 15.7623 14.8907 15.7604 15.1825 15.4665C15.4742 15.1725 15.4723 14.6975 15.1784 14.4058L12.75 11.9958V7.5Z"
                    fill="" />
            </svg>
        </div>

        <div class="flex items-end justify-between mt-5">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Request Completion</span>
                <h4 class="mt-2 font-bold text-gray-800 text-title-sm dark:text-white/90"
                    x-text="$store.dashboardData.completionRate + '%'">
                </h4>
            </div>

            <span
                class="flex items-center gap-1 rounded-full bg-success-50 py-0.5 pl-2 pr-2.5 text-sm font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                <span x-text="$store.dashboardData.approvedCount + $store.dashboardData.deniedCount"></span>
                <span>resolved</span>
            </span>
        </div>
    </div>
</div>
