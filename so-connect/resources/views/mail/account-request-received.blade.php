@extends('mail.layout')

@section('title', 'Confirm your email')
@section('badge', 'Request received')
@section('heading', 'Confirm your email address')

@section('body')
    <p>Hello{{ $firstName !== '' ? ' '.$firstName : '' }},</p>
    <p>
        We received your request for a <strong>StudentConnect</strong> account. Confirm this email
        address to activate your guest access — you'll be able to sign in and follow your request
        while an administrator reviews it.
    </p>

    <div class="btn-wrap">
        <a href="{{ $confirmUrl }}" class="btn">Confirm my email</a>
    </div>

    <p class="note" style="text-align:center;">This link expires in {{ $expiresInDays }} days.</p>

    <hr>

    <p><strong>What happens next</strong></p>
    <ol>
        <li>Confirm this email address to unlock your guest access.</li>
        <li>An administrator reviews your sign-up request.</li>
        <li>Once it is approved, your account becomes a full officer account.</li>
    </ol>

    <p class="note">If you did not request a StudentConnect account, you can safely ignore this email.</p>
@endsection
