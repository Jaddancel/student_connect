<div x-data="basicTablesOne">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-full overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[1102px]">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                User
                            </p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                Project Name
                            </p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                Team
                            </p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                Status
                            </p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                Budget
                            </p>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="order in orders" :key="order.id">
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-5 py-4 sm:px-6" colspan="1">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 overflow-hidden rounded-full">
                                        <img :src="order.user.image" :alt="order.user.name">
                                    </div>
                                    <div>
                                        <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90" x-text="order.user.name"></span>
                                        <span class="block text-gray-500 text-theme-xs dark:text-gray-400" x-text="order.user.role"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400" x-text="order.projectName"></p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex -space-x-2">
                                    <template x-for="(teamImage, index) in order.team.images" :key="index">
                                        <div class="w-6 h-6 overflow-hidden border-2 border-white rounded-full dark:border-gray-900">
                                            <img :src="teamImage" alt="team member">
                                        </div>
                                    </template>
                                </div>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium" :class="getStatusClass(order.status)" x-text="order.status"></p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400" x-text="order.budget"></p>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>