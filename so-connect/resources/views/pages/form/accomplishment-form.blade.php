@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Accomplishment Form" />

    <div class="w-full flex flex-col justify-center">

        @if (session('status'))
            <div class="w-full md:w-3/4 mx-auto mb-4">
                <x-ui.alert variant="success" title="Request Submitted" :message="session('status')" :showLink="false" />
            </div>
        @endif

        @if ($errors->any())
            <div class="w-full md:w-3/4 mx-auto mb-4">
                <x-ui.alert variant="error" title="Submission Failed" :message="$errors->first()" :showLink="false" />
            </div>
        @endif

        <form action="/document/submit" method="post" x-data="membershipRegistrationForm(@js($organizationsByType ?? []))"
            class="space-y-5 w-full md:w-3/4 mx-auto rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            @csrf

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Input
                </label>
                <input type="text"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

        </form>
    </div>
@endsection
