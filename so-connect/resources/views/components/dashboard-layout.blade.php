<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{isset($title) ? $title . " - SOConnect" : 'SOConnect'}}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex flex-col bg-[#fafaf6] font-sans">

    @auth
        @php
            $typeCode = auth()->user()
                ->user_type_code;
        @endphp

        <div class="drawer lg:drawer-open">
            <input id="my-drawer-4" type="checkbox" class="drawer-toggle" />
            <div class="drawer-content">
                <nav class="navbar bg-base-100">
                    <label for="my-drawer-4" aria-label="open sidebar" class="btn btn-square btn-ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linejoin="round"
                            stroke-linecap="round" stroke-width="2" fill="none" stroke="currentColor"
                            class="my-1.5 inline-block size-4">
                            <path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z">
                            </path>
                            <path d="M9 4v16"></path>
                            <path d="M14 10l2 2l-2 2"></path>
                        </svg>
                    </label>
                    <div class="navbar-start">
                        <p class="btn btn-ghost text-xl"><a href="http://" target="_blank"
                                rel="noopener noreferrer">{{isset($title) ? $title . " - SOConnect" : 'SOConnect'}}</a></p>
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
                                    <a class="justify-between">
                                        Profile
                                        @if (is_null($typeCode))
                                            <span class="badge bg-gray-300 font-semibold text-black">Unknown</span>
                                        @elseif ($typeCode == 3)
                                            <span class="badge bg-blue-300 font-semibold text-black">User</span>
                                        @else
                                            <span class="badge bg-green-300 font-semibold text-black">Admin</span>
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
                <main class="flex-1 container mx-auto px-4 py-8 space-y-5">
                    {{ $slot }}
                </main>
            </div>
            <div class="drawer-side is-browser-close:overflow-visible">
                <label for="my-drawer-4" aria-label="close sidebar" class="drawer-overlay"></label>
                <div class="flex min-h-full flex-col items-start bg-base-200 is-drawer-close:w-14 is-drawer-open:w-64">
                    <ul class="menu w-full grow">
                        @if (is_null($typeCode))
                            <p>User has no role</p>
                        @else
                            @if ($typeCode == 3)
                                @include('components.sidebar.officer')
                            @elseif ($typeCode < 3)
                                @include('components.sidebar.admin')
                            @else
                                <p>Unknown role: {{ $typeCode }}</p>
                            @endif
                        @endif
                    </ul>
                </div>
            </div>
            <script type="module" src="https://unpkg.com/cally"></script>
    @else
            You're not supposed to be here.
        @endauth


</body>

</html>