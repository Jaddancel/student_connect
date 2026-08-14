@extends('mail.layout', ['accent' => '#465fff', 'tint' => '#eef2ff'])

@section('title', 'Confirm your superadmin account')
@section('badge', 'System setup')
@section('heading', 'Confirm your superadmin account')

@section('body')
    <p>Hello,</p>
    <p>
        A superadmin account for <strong>StudentConnect</strong> was just created with this address
        during first-time system setup. Confirm the address to activate the account — until then it
        cannot sign in.
    </p>

    <div class="highlight-box">
        <p><strong>{{ $recipientEmail }}</strong></p>
        <p class="note">This account has full control over the system.</p>
    </div>

    <div class="btn-wrap">
        <a href="{{ $confirmationUrl }}" class="btn">Confirm and activate</a>
    </div>

    <p class="note" style="text-align:center;">
        This link expires in {{ $expiresInHours }} hours.
    </p>

    <hr>
    <p class="note">
        If you were not expecting this, someone else may be installing StudentConnect on your
        behalf. Do not share this link — anyone who opens it gains full administrative access.
    </p>
@endsection
