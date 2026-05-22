@extends('layouts.fullscreen-layout')

@section('content')
    <div class="container mx-auto lg:w-2/4 p-4 px-6 rounded-box bg-base-100">
        <h1 class="text-xl text-black font-bold my-3">Create Event</h1>

        @if (session('success'))
            <div class="alert alert-success mb-4" role="alert">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error mb-4" role="alert">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form action="{{ route('events.create') }}" method="post" class="form flex flex-col gap-4 p-6">
            @csrf
            <label for="organization_id" class=" text-black pb-3 ">
                Pick an Organization </label>
            <select class="select select-primary select-lg select-bordered" name="organization_id" id="organization_id">
                @if ($organizationsOfUser->isEmpty())
                    <option value="" disabled>No organizations available</option>
                @endif
                @foreach ($organizationsOfUser as $organization)
                    <option value="{{ $organization->organization_id }}">
                        {{ $organization->organizationDetail?->organization_name ?? 'N/A' }}
                    </option>
                @endforeach
            </select>
            <label for="event_name" class="text-black pb-2">Event Name</label>
            <input type="text" name="event_name" id="event_name" class="input input-bordered input-primary w-full"
                required>

            <label for="event_start_time" class="text-black pb-2">Event Start Time</label>
            <input type="datetime-local" name="event_start_time" id="event_start_time"
                class="input input-bordered input-primary w-full" required>
            <label for="event_end_time" class="text-black pb-2">Event End Time</label>
            <input type="datetime-local" name="event_end_time" id="event_end_time"
                class="input input-bordered input-primary w-full" required>
            <label for="event_desc_text" class="text-black pb-2">Event Description</label>
            <textarea name="event_desc_text" id="event_desc_text" class="textarea textarea
-bordered textarea-primary w-full"
                rows="4" required></textarea>
            <div class="hidden sm:flex justify-end">
                <button type="submit" class="btn btn-primary text-white">Submit</button>
            </div>
            <button type="submit" class="block sm:hidden btn btn-primary text-white">Submit</button>
        </form>
    </div>
@endsection
