<x-dashboard-layout>
    <x-slot name="title">Membership Requests</x-slot>

    <div class="container">
        <h2 class="text-3xl text-black font-bold pb-6">Membership Requests</h2>

        <div class="overflow-x-auto card bg-base-200 p-4">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Requested At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr>
                            <td>{{ $request->request_id }}</td>
                            <td>{{ $request->action }}</td>
                            <td>
                                {{ $request->request_made_at ? \Illuminate\Support\Carbon::parse($request->request_made_at)->format('M j, Y g:i A') : '-' }}
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