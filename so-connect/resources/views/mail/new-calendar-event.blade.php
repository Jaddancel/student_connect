@extends('mail.layout', ['accent' => '#465fff', 'tint' => '#eef2ff'])

@section('title', 'New Calendar Event')
@section('badge', 'New Event')
@section('heading', $eventName)

@section('body')
    <p>Hello {{ $recipientName }},</p>
    <p>A new event has been added to the {{ $organizationName }} calendar.</p>

    <div class="highlight-box">
        <p><strong>When:</strong> {{ $startTime }} – {{ $endTime }}</p>
        @if ($location !== '')
            <p style="margin-top: 8px;"><strong>Where:</strong> {{ $location }}</p>
        @endif
    </div>

    @if ($description !== '')
        <p><strong>Event details</strong></p>
        <p>{{ $description }}</p>
    @endif

    <div class="btn-wrap">
        <a class="btn" href="{{ $calendarUrl }}">Add to Google Calendar</a>
    </div>
@endsection
