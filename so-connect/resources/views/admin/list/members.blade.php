<x-dashboard-layout>
    <x-slot name="title">Organization List</x-slot>
    <div class="overflow-x-auto rounded-box border border-base-content/5 bg-base-100">
        <table class="table">
            <!-- head -->
            <thead>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Date Registered</th>
                    <th>E-mail</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $member)
                    <tr>
                        <th>{{ $loop->iteration }}</th>
                        <td>{{ $member->member_user->profile->first_name }}
                            {{ $member->member_user->profile->last_name }}
                        </td>
                        <td>{{ ucwords($member->role ?: 'Undefined') }}</td>
                        <td>{{ $member->member_since->format('M d, Y') }}</td>
                        <td>{{ $member->member_user->user_email }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-dashboard-layout>
