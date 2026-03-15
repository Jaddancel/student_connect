<x-dashboard-layout>
    @auth
        @php
            $typeCode = auth()->user()
                ->user_type_code;
        @endphp
        @if ($typeCode == 3)
            <x-slot name="title">Member Dashboard</x-slot>
            @include('dashboard.member')
        @else
            <x-slot name="title">Admin Dashboard</x-slot>
            @include('dashboard.admin')
        @endif
    @endauth

</x-dashboard-layout>