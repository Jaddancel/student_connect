<div class="flex flex-col item-start gap-y-5">
    <div class="stats w-full rounded-box bg-base-100">
        <div class="stat">
            <div class="stat-title">Total Uptime</div>
            <div class="stat-value text-primary">1h 3m</div>
        </div>
        <div class="stat">
            <div class="stat-title">Total Registrations</div>
            <div class="stat-value text-primary">9001</div>
        </div>
        <div class="stat">
            <div class="stat-title">Total Users</div>
            <div class="stat-value text-primary">5063</div>
        </div>
    </div>
    <div class="flex flex-row gap-5">
        <div class="card bg-base-100 p-5">
            <h3 class="card-title font-bold">Performance Chart</h3>
            <div class="card-body">
                {{-- Insert A Chart Here --}}
            </div>
        </div>

        <div class="card bg-base-100 p-5">
            <h3 class="card-title font-bold">Performance Chart</h3>
            <div class="card-body">
                {{-- Insert A Chart Here --}}
            </div>
        </div>

        <div class="card bg-base-100 p-5">
            <h3 class="card-title font-bold">Performance Chart</h3>
            <div class="card-body">
                {{-- Insert A Chart Here --}}
            </div>
        </div>

        <div class="card bg-base-100 p-5">
            <h3 class="card-title font-bold">Performance Chart</h3>
            <div class="card-body">
                {{-- Insert A Chart Here --}}
            </div>
        </div>
        <div class="card bg-base-100 p-5">
            <h3 class="card-title font-bold">Server Status</h3>
            <div class="card-body">
                <table class="table table-base">
                    {{-- <tr>
                        <th>Service</th>
                    </tr> --}}
                    <tr>
                        <td>
                            <div class="status status-success mr-5" aria-label="success"></div>Relay
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="status status-success mr-5" aria-label="success"></div>Auditor
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="status status-success mr-5" aria-label="success"></div>Database
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="status status-success mr-5" aria-label="success"></div>Apache
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>