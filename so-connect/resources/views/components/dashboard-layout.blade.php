<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ isset($title) ? $title . ' ' : 'SOConnect' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/dashboard.js'])
</head>

<body class="min-h-screen flex flex-col bg-base-200 font-sans">

    @auth
        @php
            $typeCode = auth()->user()->user_type;
            $isPresident = auth()
                ->user()
                ->member()
                ->whereHas('organizationrelation.organizationDetail', function ($query) {
                    $query->whereColumn('organization_details.president', 'members.member_id');
                })
                ->exists();

            $isOfficer = auth()
                ->user()
                ->member()
                ->get()
                ->contains(function ($membership) {
                    $role = strtolower(trim((string) $membership->role));

                    return $role !== '' && $role !== 'member';
                });
        @endphp

        <div class="drawer lg:drawer-open">
            <input id="my-drawer-4" type="checkbox" class="drawer-toggle" />
            <div class="drawer-content">
                <nav class="navbar">
                    <label for="my-drawer-4" aria-label="open sidebar" class="btn btn-square btn-ghost lg:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>

                    </label>
                    <div class="navbar-start">
                        <p class="btn btn-ghost text-xl"><a href="http://" target="_blank"
                                rel="noopener noreferrer">{{ isset($title) ? $title . ' ' : 'SOConnect' }}</a>
                        </p>
                    </div>
                    <div class="navbar-end gap-1">
                        <div class="dropdown dropdown-end">
                            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar avatar-placeholder">
                                <div class="bg-neutral text-neutral-content w-8 rounded-full">
                                    <span
                                        class="text-xs">{{ strtoupper(auth()->user()->profile->first_name[0] ?? '?') }}</span>
                                </div>
                            </div>
                            <ul tabindex="-1"
                                class="menu menu-sm dropdown-content bg-base-100 rounded-box z-1 mt-3 w-52 p-2 shadow">
                                <li>
                                    <a class="justify-between"
                                        href="{{ route('profile.view', ['user_id' => auth()->id()]) }}">
                                        Profile
                                        @if (is_null($typeCode))
                                            <span class="badge bg-gray-300 font-semibold text-black">Unknown</span>
                                        @elseif ($typeCode == 3)
                                            <span class="badge bg-blue-300 font-semibold text-black">User</span>
                                        @elseif ($typeCode == 2)
                                            <span class="badge bg-green-300 font-semibold text-black">Admin</span>
                                        @elseif ($typeCode == 1)
                                            <span class="badge bg-yellow-300 font-semibold text-black">Super Admin</span
                                                @endif
                                    </a>
                                </li>
                                <li><a>Settings</a></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="w-full text-left">Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                </nav>
                <main class="flex-1 container m-auto px-4 py-8 space-y-5">
                    {{ $slot }}
                </main>
            </div>
            <div class="drawer-side is-browser-close:overflow-visible">
                <label for="my-drawer-4" aria-label="close sidebar" class="drawer-overlay"></label>
                <div class="flex min-h-full flex-col items-start bg-base-100 ">
                    <ul class="menu w-full grow">
                        @if (is_null($typeCode))
                            <p>User has no role</p>
                        @else
                            @if ($typeCode == 1)
                                @include('components.sidebar.president')
                            @elseif ($isPresident)
                                @include('components.sidebar.president')
                            @elseif ($isOfficer)
                                @include('components.sidebar.officer')
                            @else
                                @include('components.sidebar.member')
                            @endif
                        @endif
                    </ul>
                </div>
            </div>
        @else
            You're not supposed to be here.
        @endauth
        @stack('scripts')
</body>

</html>
