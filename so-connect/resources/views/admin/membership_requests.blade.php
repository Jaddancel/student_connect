<x-dashboard-layout>
    <x-slot name="title">Membership Requests</x-slot>

    <div class="container">
        <h2 class="text-3xl text-black font-bold pb-6">Membership Requests</h2>

        @if (session('success'))
            <div class="alert alert-success mb-4">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error mb-4">
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="overflow-x-auto card bg-base-200 p-4">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Organization</th>
                        <th>Requested At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr>
                            <td>{{ trim(($request->user->profile->last_name ?? '') . ', ' . ($request->user->profile->first_name ?? '') . ' ' . ($request->user->profile->middle_name ?? '')) }}
                            </td>
                            <td>{{ $request->organization->organizationDetail->organization_name ?? 'Unknown Organization' }}
                            </td>
                            <td>{{ $request->requested_at }}</td>
                            <td>
                                @if ($request->status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @elseif ($request->status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @elseif ($request->status === 'rejected')
                                    <span class="badge badge-error">Rejected</span>
                                @endif
                            </td>
                            <td>
                                @if ($request->status === 'pending')
                                    <div class="flex gap-2">
                                        <form method="POST"
                                            action="{{ route('admin.membership_requests.approve', $request->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <form method="POST"
                                            action="{{ route('admin.membership_requests.deny', $request->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-error">Reject</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-gray-500">No actions available</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-gray-500">No membership requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>