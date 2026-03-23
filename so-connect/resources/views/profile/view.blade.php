<x-dashboard-layout>
    <x-slot name="title">Profile</x-slot>
    <div class="flex flex-col gap-y-5 bg-base-200">
        <div class="flex flex-row gap-x-5">
            <div class="avatar">
                <div class="w-24 rounded">
                    <img src="https://img.daisyui.com/images/profile/demo/superperson@192.webp" alt="" srcset="">
                </div>
            </div>

            <div class="bg-base-100 w-full p-5 card rounded">
                <h2 class="card-title">{{ $profile->first_name }} {{ $profile->last_name }}</h2>
                <p>Occupation: {{ ucwords($profile->occupation)}}</p>
                <p>User registered since {{ $profile->created_at->diffForHumans()}}</p>
            </div>
        </div>
    </div>
</x-dashboard-layout>