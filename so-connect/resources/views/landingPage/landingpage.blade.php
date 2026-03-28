<x-singlepage-layout>
    <x-slot name="title">SoConnect</x-slot>
    <div class="hero min-h-screen bg-base-200">
        <div class="hero-content text-center">
            <div class="max-w-md">
                <h1 class="text-5xl font-bold">Welcome to Student Connect!</h1>
                <p class="py-6">Your one-stop platform for discovering and joining student organizations on campus.
                    Connect with like-minded peers, explore new interests, and make the most of your college experience.
                </p>
                <a href="{{ route('register') }}" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </div>
    <div class="flex flex-col bg-base-100 rounded-box p-10">
        @foreach ($organizationTypes as $key => $type)
            {{-- Only show the category divider if it actually has organizations --}}
            @if (isset($organizationsByType[$key]) && count($organizationsByType[$key]) > 0)
                <div class="divider text-2xl font-bold my-8">{{ $type }}</div>

                {{-- The Grid Container: Responsive from 1 to 5 columns --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-6">

                    @foreach ($organizationsByType[$key] as $organization)
                        {{-- The Card: Added 'relative' and 'overflow-hidden' for the overlay --}}
                        <div
                            class="card w-full bg-base-100 shadow-md group transition-all duration-300 hover:shadow-xl hover:-translate-y-1 relative overflow-hidden">

                            {{-- Placeholder Logo (Using a free placeholder service) --}}
                            <figure>
                                <img src="https://placehold.co/400x250?text=Logo" alt="Organization Logo"
                                    class="w-full h-32 object-cover" />
                            </figure>

                            <div class="card-body p-4 text-center items-center">
                                <h2 class="card-title text-base">{{ $organization['name'] }}</h2>
                            </div>

                            {{-- Hover Overlay with Button --}}
                            <div
                                class="absolute inset-0 bg-base-100/80 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                <form action="#" method="POST"
                                    class="translate-y-4 group-hover:translate-y-0 transition-all duration-300">
                                    @csrf
                                    {{-- Hidden ID input (Ready for when you update the controller) --}}
                                    <input type="hidden" name="organization_id"
                                        value="{{ $organization['id'] ?? '' }}">
                                    <button type="submit" class="btn btn-primary">Join Now</button>
                                </form>
                            </div>
                        </div>
                    @endforeach

                </div>
            @endif
        @endforeach
    </div>
</x-singlepage-layout>
