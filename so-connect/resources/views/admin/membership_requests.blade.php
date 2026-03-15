<x-dashboard-layout>
    <x-slot name="title">Membership Requests</x-slot>

    <div class="container">
        <h2 class="text-3xl text-black font-bold pb-6">Membership Requests</h2>

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
                            <td>{{ $request->user->profile->last_name .", ". $request->user->profile->first_name . " " . $request->user->profile->middle_name ?? ""}}</td>
                            <td>{{ $request->organization->organization_name}}</td>
                            <td>{{ $request->request_time }}</td>
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
                                    <button class="btn btn-sm btn-success"
                                        onclick="approveRequest({{ $request->id }})">Approve</button>
                                    <button class="btn btn-sm btn-error"
                                        onclick="rejectRequest({{ $request->id }})">Reject</button>
                                @else
                                    <span class="text-gray-500">No actions available</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-gray-500">No membership requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>