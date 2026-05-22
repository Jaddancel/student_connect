@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Calendar" />
    <x-calendar-area :can-request-event="$canRequestEvent ?? null" :event-request-organizations="$eventRequestOrganizations ?? null" :locked-org-ids="$lockedOrgIds ?? []" :president-name="$presidentName ?? null" :president-contact="$presidentContact ?? null" />
@endsection
