@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 flex min-h-screen items-center justify-center bg-white px-6 dark:bg-gray-900">
    <div class="w-full max-w-md text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-warning-50 text-warning-500 dark:bg-warning-500/10">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Organization suspended</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            Your organization's accreditation has lapsed, so access is paused. Once your
            organization submits and gets its accreditation approved, a system administrator can
            restore access. If you think this is a mistake, please contact your administrator.
        </p>

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                Sign out
            </button>
        </form>
    </div>
</div>
@endsection
