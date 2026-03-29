@props([
    'pendingMembershipRequests' => 42,
    'pendingEvents' => 12,
    'announcements' => 8,
    'members' => 39,
])

<div {{ $attributes->class(['card shadow bg-base-100 flex flex-col items-start']) }}>
    <div class="stats w-full">
        <div class="stat">
            <div class="stat-title">Pending Membership Requests</div>
            <div class="stat-value text-primary">{{ $pendingMembershipRequests }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">Pending Events</div>
            <div class="stat-value text-primary">{{ $pendingEvents }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">Announcements</div>
            <div class="stat-value text-primary">{{ $announcements }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">Members</div>
            <div class="stat-value text-primary">{{ $members }}</div>
        </div>
    </div>

    <button class="btn btn-xs pt-4 p-3 ghost mb-3 ml-4">Export as PDF</button>
</div>
