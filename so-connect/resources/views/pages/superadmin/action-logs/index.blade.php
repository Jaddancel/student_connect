@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Action Logs" />

    @include('partials.action-logs-viewer', [
        'routePrefix' => 'superadmin.action-logs',
        'searchUrl' => route('superadmin.profiles.search', ['admins' => 1]),
        'filterDescription' => 'Search administrator actions by user, category, or date range.',
        'tableTitle' => 'Administrator Actions',
        'userModalTitle' => 'Filter by administrator',
        'userSearchPlaceholder' => "Type an admin's name…",
        'userNoun' => 'administrators',
    ])
@endsection
