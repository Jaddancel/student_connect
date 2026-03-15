<div class="flex flex-col align-middle space-y-5">
    <div class="stats shadow bg-base-100">
        <div class="stat">
            <div class="stat-figure text-primary"></div>
            <div class="stat-title">Organizations</div>
            <div class="stat-value text-primary">10</div>
        </div>
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
    <div class="flex flex-row align-middle flex-wrap gap-5">
        <div class="card p-5 shadow bg-base-100">
            <h3 class="card-title font-black text-xl">Manage Organizations</h3>
            <div class="card-body p-5">
                <table class="table table-base">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Member Count</th>
                    </tr>
                    </a>
                    <tr>
                        <td><a href="#" class="link">Ang Mga Juan Dela Cruz</a></td>
                        <td>Type</td>
                        <td>29</td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="card p-5 bg-base-100 shadow">
            <h3 class="card-title font-black text-xl px-2">Recent Event Requests</h3>
            <div class="card-body">
                <table class="table table-base">
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Organizer</th>
                        <th></th>
                    </tr>
                    <tr>
                        <td>Order 500 Cigarettes</td>
                        <td>9-11-2026</td>
                        <td>DOH</td>
                        <td>
                            <div class="flex flex-row">
                                <button type="button" class="btn btn-primary bg-success mr-1 p-2">Y</button>
                                <button type="button" class="btn btn-primary bg-error p-2">N</button>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="card p-5 bg-base-100 shadow">
            <h3 class="card-title font-black text-xl px-2">Make Announcement</h3>
            <div class="card-body">
                <input type="text" name="ann_title" id="ann_title" class="input" placeholder="Announcement Title">
                <textarea name="" id="" cols="30" placeholder="Enter announcement text here."
                    class="textarea"></textarea>
            </div>
        </div>
        {{-- TODO: Complete "student registration", "report", and "export PDF" widgets. --}}
    </div>
</div>