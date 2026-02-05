<x-dashboard-layout>
    <x-slot name="title">Dashboard</x-slot>
    <div class="card bg-base-100 w-full shadow-sm">
        <div class="card-body">
            <h2 class="card-title pb-2">Upcoming Events</h2>
            <div class="flex flex-col lg:flex-row gap-4">
                <calendar-date min="2025-12-01" max="2026-01-31" locale="en-GB"
                    class="cally w-full lg:w-1/4 bg-base-100 border border-base-300 shadow-lg rounded-box">
                    <svg aria-label="Previous" class="fill-current size-4" slot="previous"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M15.75 19.5 8.25 12l7.5-7.5"></path>
                    </svg>
                    <svg aria-label="Next" class="fill-current size-4" slot="next" xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24">
                        <path fill="currentColor" d="m8.25 4.5 7.5 7.5-7.5 7.5"></path>
                    </svg>
                    <calendar-month></calendar-month>
                </calendar-date>
                <div class="divider lg:divider-horizontal"></div>
                <div class="flex-1">
                    <h2 class="card-title pb-2">No upcoming events</h2>
                    <p>You have no upcoming events scheduled.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="card bg-base-100 w-full md:w-96 shadow-sm">
        <div class="card-body">
            <h2 class="card-title"><a href="http://" target="_blank" rel="noopener noreferrer"
                    class="btn btn-ghost text-lg"><svg xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>Document Requests (0)</a></h2>
            <p>You have not made any document requests.</p>
        </div>
        <div class="card-actions justify-end">
            <button class="btn btn-primary px-3 py-1 m-5 text-white">
                Request A Document
            </button>
        </div>
    </div>
</x-dashboard-layout>