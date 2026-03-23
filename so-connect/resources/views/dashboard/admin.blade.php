<div class="flex flex-col item-start space-y-5 p-8">
    <div class="card shadow bg-base-100 flex flex-col items-start">
        <div class="stats w-full">
            <div class="stat">
                <div class="stat-title">Pending Events</div>
                <div class="stat-value text-primary">12</div>
            </div>
            <div class="stat">
                <div class="stat-title">Announcements</div>
                <div class="stat-value text-primary">8</div>
            </div>
            <div class="stat">
                <div class="stat-title">Registrations</div>
                <div class="stat-value text-primary">29</div>
            </div>
        </div>
        <button class="btn btn-xs pt-4 p-3 ghost mb-3 ml-4">Export as PDF</button>
    </div>


    <div class="flex flex-row align-middle flex-wrap gap-5">
        <div class="card p-5 bg-base-100 shadow grow-2">
            <h3 class="card-title font-bold text-lg px-2">Make Announcement</h3>
            <div class="card-body">
                <input type="text" name="ann_title" id="ann_title" class="input w-full"
                    placeholder="Announcement Title">
                <textarea name="" id="" cols="30" placeholder="Enter announcement text here."
                    class="textarea w-full"></textarea>
                <button class="btn btn-primary hover">Post</button>
            </div>
        </div>
        <div class="card p-5 bg-base-100 shadow grow-2">
            <h3 class="card-title font-bold text-lg px-2">Pending Membership Approvals</h3>
            <div class="card-body">
                <ul class="list rounded-box">
                    <li class="list-row">
                        <div><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-10">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </div>
                        <div>
                            <div>Juan Dela Cruz</div>
                            <div class="text-xs uppercase font-semibold opacity-60">Fifteen Minutes Ago</div>
                        </div>
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
                    </li>
                </ul>
            </div>
        </div>
        <div class="card p-5 bg-base-100 shadow grow-2">
            <h3 class="card-title font-bold px-2">Reports</h3>
            <div class="card-body">
                <div class="flex flex-col gap-y-5">
                    <div class="text-xs uppercase opacity-60 font-semibold">Updated: Sept. 20, 2025</div>
                    <button class="btn btn-primary">Read Latest Report</button>
                </div>
            </div>
        </div>
        <div class="card p-5 shadow bg-base-100">
            <h3 class="card-title font-bold text-lg">Manage Organizations</h3>
            <div class="card-body p-5">
                <table class="table table-base">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Member Count</th>
                    </tr>
                    </a>
                    <tr>
                        <td>Ang Mga Juan Dela Cruz</td>
                        <td>Type</td>
                        <td>29</td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="card p-5 bg-base-100 shadow">
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
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="3" stroke="currentColor" class="size-[1.2em]">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </button>
                                <button class="btn btn-square btn-ghost">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="3" stroke="currentColor" class="size-[1.2em]">
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
        <div class="card p-5 bg-base-100">
            <h3 class="card-title">Calendar</h3>
            <div class="card-body">
                <div class="list">

                </div>
            </div>
        </div>
    </div>
</div>