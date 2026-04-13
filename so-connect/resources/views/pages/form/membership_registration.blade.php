@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Membership Registration" />

    <div class="w-full flex justify-center">

        <form action="/register/member" method="post" x-data="membershipRegistrationForm(@js($organizationsByType ?? []))"
            class="space-y-5 w-full md:w-3/4 mx-auto rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            @csrf

            <div>
                <label for="typeSelect" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization
                    Type</label>
                <div class="relative z-20 bg-transparent">
                    <select x-model="selectedType" x-on:change="updateOrgList" name="typeSelector" id="typeSelect"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <option value="0" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Select an
                            Organization Type</option>
                        <option value="1" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Socio-Civic
                        </option>
                        <option value="2" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Religious</option>
                        <option value="3" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Fraternities
                        </option>
                        <option value="4" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Special Interest
                        </option>
                        <option value="5" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Student Government
                        </option>
                        <option value="6" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                            University-Sanctioned</option>
                    </select>
                    <span
                        class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
            </div>

            <div>
                <label for="orgSelector"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                <div class="relative z-20 bg-transparent">
                    <select x-model="selectedOrganization" name="orgSelector" id="orgSelector"
                        :disabled="selectedType === '0' || availableOrganizations.length === 0"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 dark:disabled:bg-gray-800 dark:disabled:text-gray-400">
                        <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Select an
                            Organization</option>
                        <template x-for="organization in availableOrganizations" :key="organization.organization_id">
                            <option :value="organization.organization_id" x-text="organization.organization_name"
                                class="text-gray-700 dark:bg-gray-900 dark:text-gray-400"></option>
                        </template>
                    </select>
                    <span
                        class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
            </div>

            <button type="submit"
                class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:bg-brand-500 dark:hover:bg-brand-600">
                Submit
            </button>
        </form>
    </div>
@endsection
