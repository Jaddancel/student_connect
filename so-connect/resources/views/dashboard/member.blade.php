@php
    $memberEvents = $memberDashboardEvents ?? collect();
@endphp

<x-dashboard.cards.event-calendar :events="$memberEvents" id-prefix="member-dashboard-event" />

<section class="space-y-5">
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <p class="text-sm opacity-70">Member dashboard content is intentionally minimal.</p>
        </div>
    </div>
</section>
