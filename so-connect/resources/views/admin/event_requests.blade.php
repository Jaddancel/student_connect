<x-dashboard-layout>
    <x-slot name="title">Event Requests</x-slot>

    <div class="container">
        <h2 class="text-3xl text-black font-bold pb-6">Event Requests</h2>

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
                        <th>Event</th>
                        <th>Organization</th>
                        <th>Requested By</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $request->event_name }}</div>
                                <div class="text-sm text-gray-600">{{ $request->event_desc_text ?: 'No description' }}
                                </div>
                            </td>
                            <td>{{ $request->organization->organizationDetail->organization_name ?? 'Unknown Organization' }}
                            </td>
                            <td>{{ $request->requester_name ?: 'Unknown User' }}</td>
                            <td>{{ $request->event_start_time }} - {{ $request->event_end_time }}</td>
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
                                            action="{{ route('admin.event_requests.approve', $request->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <form method="POST"
                                            action="{{ route('admin.event_requests.deny', $request->id) }}">
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
                            <td colspan="6" class="text-center text-gray-500">No event requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
