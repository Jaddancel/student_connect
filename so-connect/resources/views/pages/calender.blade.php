@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Calender" />
    <x-calender-area :can-request-event="$canRequestEvent ?? null"
        :event-request-organizations="$eventRequestOrganizations ?? null" />
@endsection