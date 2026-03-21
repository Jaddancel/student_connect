<x-dashboard-layout>
    <x-slot name="title">My Organizations</x-slot>

    <div class="overflow-x-auto rounded-box border border-base-content/5 bg-base-100 p-4 ">
        <h2 class="text-2xl font-bold my-3">My Organizations</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Role</th>
                    <th>Member Since</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $org)
                    <tr>
                        <td>{{ $org->organization_name }}</td>
                        <td>{{ $org->role_name }}</td>
                        <td>{{ $org->member_since ? \Carbon\Carbon::parse($org->member_since)->format('M d, Y') : '—' }}
                        </td>
                        <td>
                            @if ($org->status === 'Approved')
                                <span class="badge badge-success">Approved</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-gray-500">You are not part of any organizations yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-dashboard-layout>