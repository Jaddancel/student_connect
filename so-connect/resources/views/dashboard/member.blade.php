@php
    $memberEvents = $memberDashboardEvents ?? collect();
    $organizations = $userOrganizations ?? collect();
@endphp

<x-dashboard.cards.event-calendar :events="$memberEvents" id-prefix="member-dashboard-event" />

<x-dashboard.cards.organization-list :organizations="$organizations" />
