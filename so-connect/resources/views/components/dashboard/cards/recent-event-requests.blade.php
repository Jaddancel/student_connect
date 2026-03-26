<div {{ $attributes->class(['card p-5 bg-base-100 shadow']) }}>
    <h3 class="card-title font-bold text-lg px-2">Recent Event Requests</h3>
    <div class="card-body hidden md:block">
        <table class="table table-base">
            <tr>
                <th>Event</th>
                <th>Date</th>
                <th>Organizer</th>
                <th></th>
            </tr>
            <tr>
                <td>Webinar</td>
                <td>9-11-2026</td>
                <td>DOH</td>
                <td>
                    <div class="flex flex-row">
                        <button class="btn btn-square btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3"
                                stroke="currentColor" class="size-[1.2em]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </button>
                        <button class="btn btn-square btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3"
                                stroke="currentColor" class="size-[1.2em]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <div class="card-body md:hidden">
        SMALL
    </div>
</div>
