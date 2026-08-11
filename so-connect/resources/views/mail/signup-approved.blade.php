@extends('mail.layout')

@section('title', 'Application approved')
@section('badge', '✓ Approved')
@section('heading', 'Your application has been approved!')

@section('body')
    <p>Hello{{ $firstName !== '' ? ' '.$firstName : '' }},</p>
    <p>
        An administrator has approved your sign-up request. Your account is now a full officer
        account — sign in with the email and password you registered with.
    </p>

    @if ($organizationName !== '' || $position !== '')
        <div class="highlight-box">
            @if ($organizationName !== '')
                <p><strong>{{ $organizationName }}</strong></p>
            @endif
            @if ($position !== '')
                <p class="note">Position: {{ $position }}</p>
            @endif
        </div>
    @endif

    <div class="btn-wrap">
        <a href="{{ $dashboardUrl }}" class="btn">Go to my dashboard</a>
    </div>
@endsection
