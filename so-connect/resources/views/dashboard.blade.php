<x-dashboard-layout>
    @auth
        @php
            $typeCode = auth()->user()
                ->user_type;
        @endphp
        @if ($typeCode == 3)
            <x-slot name="title">Member Dashboard</x-slot>
            @include('dashboard.member')
        @elseif ($typeCode == 2)
            <x-slot name="title">Admin Dashboard</x-slot>
            @include('dashboard.admin')
        @elseif ($typeCode == 1)
            <x-slot name="title">Super Admin Dashboard</x-slot>
            @include('dashboard.superadmin')
        @endif
    @endauth

    </x-dashboard-layout>