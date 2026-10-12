<div
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 pb-5 pt-5 sm:px-6 sm:pb-6 sm:pt-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Request Volume</h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"
                x-text="'Request traffic for ' + $store.dashboardType.current.label + ' ' + $store.dashboardData.compareWindowText + '.'">
            </p>
        </div>
        <span
            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
            x-text="$store.dashboardData.chartWindowLabel"></span>
    </div>

    <div class="mt-6">
        <div x-show="$store.dashboardData.loading" class="animate-pulse">
            <div class="grid h-44 grid-cols-12 items-end gap-2">
                <template x-for="index in 12" :key="'skeleton-bar-' + index">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-full rounded-t-md bg-gray-200 dark:bg-gray-700"
                            x-bind:style="'height: ' + (20 + (index % 6) * 18) + 'px'"></div>
                        <span class="h-2 w-6 rounded bg-gray-200 dark:bg-gray-700"></span>
                    </div>
                </template>
            </div>
        </div>

        <div x-show="!$store.dashboardData.loading">
            <div class="flex h-44 items-end gap-1">
                <template x-for="bucket in $store.dashboardData.volumeSeries" :key="bucket.key">
                    <div class="group flex h-full min-w-0 flex-1 flex-col items-center justify-end"
                        x-bind:title="bucket.label + ': ' + bucket.count + ' request(s)'">
                        <div class="w-full rounded-t-sm transition-all duration-300"
                            x-bind:class="bucket.count > 0
                                ? 'bg-brand-500/80 group-hover:bg-brand-500'
                                : 'bg-gray-200 group-hover:bg-gray-300 dark:bg-gray-700'"
                            x-bind:style="'height: ' + (bucket.count > 0
                                ? Math.max((bucket.count / $store.dashboardData.maxVolumeCount) * 144, 4)
                                : 2) + 'px'"></div>
                    </div>
                </template>
            </div>
            <div class="mt-2 flex gap-1">
                <template x-for="(bucket, index) in $store.dashboardData.volumeSeries" :key="'label-' + bucket.key">
                    <div class="relative h-4 min-w-0 flex-1">
                        <span x-show="bucket.showLabel"
                            class="absolute top-0 whitespace-nowrap text-[10px] text-gray-500 dark:text-gray-400"
                            x-bind:class="index === $store.dashboardData.volumeSeries.length - 1
                                ? 'right-0'
                                : (index === 0 ? 'left-0' : 'left-1/2 -translate-x-1/2')"
                            x-text="bucket.label"></span>
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-brand-500"></span>
                <span class="text-gray-600 dark:text-gray-300" x-text="$store.dashboardData.volumeLegendLabel"></span>
            </div>
            <span class="text-gray-500 dark:text-gray-400"
                x-text="'Peak: ' + $store.dashboardData.maxVolumeCount + $store.dashboardData.peakVolumeSuffix"></span>
        </div>
    </div>
</div>
