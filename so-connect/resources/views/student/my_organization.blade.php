<x-dashboard-layout>
    <x-slot name="title">My Organizations</x-slot>

    <div class="overflow-x-auto rounded-box border border-base-content/5 bg-base-100 p-4 ">
        <h2 class="text-2xl font-bold my-3">My Organizations</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Role</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Student Government Association</td>
                    <td>President</td>
                    <td class="status-active">Active</td>
                </tr>
                <tr>
                    <td>Environmental Club</td>
                    <td>Member</td>
                    <td class="status-pending">Pending</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-dashboard-layout>