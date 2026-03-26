<x-dashboard-layout>
    @auth
        @php
            $typeCode = auth()->user()->user_type;
            // $isPresident = auth()
            //     ->user()
            //     ->member()
            //     ->whereHas('organizationrelation.organizationDetail', function ($query) {
            //         $query->whereColumn('organization_details.president', 'members.member_id');
            //     })
            //     ->exists();

            // $isOfficer = auth()
            //     ->user()
            //     ->member()
            //     ->get()
            //     ->contains(function ($membership) {
            //         $role = strtolower(trim((string) $membership->role));

            //         return $role !== '' && $role !== 'member';
            //     });
        @endphp

        @if ($typeCode == 1)
            <x-slot name="title">Super Admin Dashboard</x-slot>
            @include('dashboard.superadmin')
        @elseif ($isPresident)
            <x-slot name="title">President Dashboard</x-slot>
            @include('dashboard.president')
        @elseif ($isOfficer)
            <x-slot name="title">Officer Dashboard</x-slot>
            @include('dashboard.officer')
        @else
            <x-slot name="title">Member Dashboard</x-slot>
            @include('dashboard.member')
        @endif
    @endauth

</x-dashboard-layout>
