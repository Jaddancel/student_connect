@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Audit Logs" />

    @include('partials.action-logs-viewer', [
        'routePrefix' => 'admin.audit-logs',
        'searchUrl' => route('admin.audit-logs.officers'),
        'filterDescription' => 'Search officer actions by user, category, organization, or date range.',
        'tableTitle' => 'Officer Actions',
        'userModalTitle' => 'Filter by officer',
        'userSearchPlaceholder' => "Type an officer's name or email…",
        'userNoun' => 'officers',
    ])
@endsection
