@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <x-dashboard.type-switcher />
        </div>
        <div x-show="$store.dashboardType.layout1" class="col-span-12 space-y-6 xl:col-span-7">
            <x-dashboard.metrics-by-type />
            <x-dashboard.list-by-type />
        </div>
        <div x-show="$store.dashboardType.layout1" class="col-span-12 space-y-6 xl:col-span-5">
            <x-dashboard.compare-by-type />
            <x-dashboard.a-d-chart-by-type />
        </div>
        <div x-show="$store.dashboardType.layout2" class="col-span-12">
            <x-dashboard.role-by-the-number />
        </div>
        <div x-show="$store.dashboardType.layout2" class="col-span-12 space-y-6 xl:col-span-7">
            <x-dashboard.list-by-type />
        </div>
        <div x-show="$store.dashboardType.layout2" class="col-span-12 space-y-6 xl:col-span-5">
            <x-dashboard.a-d-chart-by-type />
        </div>
    </div>
@endsection