@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <x-dashboard.type-switcher />

                <div class="w-full xl:w-auto">
                    <label for="dashboard-window-filter" class="sr-only">Filter dashboard timeframe</label>
                    <select id="dashboard-window-filter"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 outline-none transition focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 xl:min-w-[180px]"
                        x-model="$store.dashboardWindow.key"
                        x-on:change="$store.dashboardWindow.setWindow($event.target.value)">
                        <option value="day">Last 24 hours</option>
                        <option value="month">1 month</option>
                        <option value="all">Overall</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-span-12" x-show="$store.dashboardType.current.code === 7">
            <x-dashboard.role-by-the-number />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-7" x-show="$store.dashboardType.current.code !== 7">
            <x-dashboard.metrics-by-type />
            <x-dashboard.list-by-type :canDecide="$canDecide ?? true" />
        </div>
        <div class="col-span-12 space-y-6 xl:col-span-5" x-show="$store.dashboardType.current.code !== 7">
            <x-dashboard.compare-by-type />
            <x-dashboard.a-d-chart-by-type />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-7" x-show="$store.dashboardType.current.code === 7">
            <x-dashboard.list-by-type :canDecide="$canDecide ?? true" />
        </div>
        <div class="col-span-12 space-y-6 xl:col-span-5" x-show="$store.dashboardType.current.code === 7">
            <x-dashboard.a-d-chart-by-type />
        </div>
    </div>
@endsection
