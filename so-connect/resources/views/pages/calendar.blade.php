@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Calendar" />
    <x-calendar-area :is-system-admin="$isSystemAdmin ?? false" :calendar-organizations="$calendarOrganizations ?? []" :organization-types="$organizationTypes ?? []" />
@endsection
