@extends('mail.layout', ['accent' => '#dc2626', 'tint' => '#fef2f2'])

@section('title', 'Sign-up not approved')
@section('badge', 'Not approved')
@section('heading', $accountDeleted ? 'Your sign-up request was closed' : 'Your sign-up needs changes')

@section('body')
    <p>Hello{{ $firstName !== '' ? ' '.$firstName : '' }},</p>
    <p>An administrator reviewed your StudentConnect sign-up request and did not approve it.</p>

    <div class="highlight-box">
        <p><strong>Reason</strong></p>
        <p>{{ $reason !== '' ? $reason : 'No reason was given.' }}</p>
    </div>

    @if ($accountDeleted)
        <p>
            This was your final attempt, so your temporary guest account has been removed along with
            the details you submitted. You are welcome to start a fresh sign-up if you believe the
            information can be corrected.
        </p>
        <div class="btn-wrap">
            <a href="{{ $retryUrl }}" class="btn">Start a new sign-up</a>
        </div>
    @else
        <p>
            You can correct the details and submit again — sign in and your previous answers will be
            waiting for you, already filled in.
        </p>
        <div class="btn-wrap">
            <a href="{{ $retryUrl }}" class="btn">Review and resubmit</a>
        </div>
        <p class="note" style="text-align:center;">
            {{ $attemptsLeft }} {{ $attemptsLeft === 1 ? 'attempt' : 'attempts' }} remaining before the
            guest account is closed.
        </p>
    @endif
@endsection
