@php
    $memberEvents = $memberDashboardEvents ?? collect();
    $memberPendingRequests = $memberPendingMembershipRequests ?? collect();
@endphp

<x-dashboard.cards.event-calendar :events="$memberEvents" id-prefix="member-event" />

<div class="flex flex-row gap-5">
    <x-dashboard.cards.membership-requests :requests="$memberPendingRequests" />
    <x-dashboard.cards.document-requests />
</div>
