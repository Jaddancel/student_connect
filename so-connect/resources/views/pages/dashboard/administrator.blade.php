@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <x-horizontal-tabs.admin />
        </div>
        <div class="col-span-12 space-y-6 xl:col-span-7">
            <x-counter.member />
            <x-chart.oad-chart />
        </div>
        <div class="col-span-12 xl:col-span-5">
            <x-chart.monthly-approvals />
        </div>
        <div class="col-span-12 xl:col-span-7">
            <x-list.recent-member-requests />
        </div>
    </div>
@endsection
