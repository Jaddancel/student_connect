@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="New ID Template" />

    @include('pages.superadmin.id-templates._form')
@endsection
