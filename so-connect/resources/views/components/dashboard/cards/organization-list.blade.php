@props([
    'organizations' => collect(),
])

<div {{ $attributes->class(['card bg-base-100 p-5 shadow-lg w-full lg:w-1/3']) }}>
    <h3 class="card-title">Organizations</h3>
    <ul class="list bg-base-100 rounded-box shadow-md">
        @forelse ($organizations as $org)
            <li class="list-row">
                <div><img class="size-10 rounded-box" src="" />Logo</div>
                <div>
                    <div>{{ $org->organization_name }}</div>
                    <div class="text-xs uppercase font-semibold opacity-60">{{ $org->role_name }}</div>
                </div>
                <span class="badge {{ $org->status === 'Approved' ? 'badge-success' : 'badge-warning' }}">
                    {{ $org->status }}
                </span>
            </li>
        @empty
            <li class="p-4 text-sm opacity-70">No organization memberships yet.</li>
        @endforelse


    </ul>
</div>
