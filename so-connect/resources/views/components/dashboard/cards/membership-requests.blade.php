@props([
    'requests' => collect(),
])

<div {{ $attributes->class(['card bg-base-100 p-5']) }}>
    <h3 class="card-title">Membership Requests</h3>
    <div class="card-body">
        <ul class="space-y-3">
            @forelse ($requests as $request)
                <li class="rounded-box border border-base-300 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold leading-tight">{{ $request['requester_name'] }}</p>
                            <p class="mt-1 text-xs uppercase tracking-wide opacity-70">
                                {{ $request['organization_name'] }}</p>
                        </div>
                        <span class="badge badge-warning badge-sm">Pending</span>
                    </div>
                    <p class="mt-2 text-sm opacity-80">
                        Requested {{ optional($request['requested_at'])->diffForHumans() ?? 'recently' }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <form method="POST"
                            action="{{ route('officer.membership_requests.approve', $request['id']) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('officer.membership_requests.deny', $request['id']) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-error">Reject</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="rounded-box border border-base-300 bg-base-200/50 p-4 text-sm opacity-80">
                    No pending membership requests.
                </li>
            @endforelse
        </ul>
    </div>
</div>
