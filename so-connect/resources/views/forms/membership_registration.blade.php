{{-- TODO: Make the registration controller work - Jad --}}

<x-dashboard-layout>
    <div class="container mx-auto lg:w-2/4 px-4 lg:px-0">
        <h1 class="text-xl text-black font-bold my-3">Membership Registration</h1>

        @if (session('success'))
            <div class="alert alert-success mb-4" role="alert">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error mb-4" role="alert">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div id="form" class="flex flex-col gap-4 p-6">
            <form method="GET" action="{{ route('forms.membership_registration') }}">
                <label for="org_type_selector" class="text-black pb-2 flex flex-col gap-4">Organization Type
                    Label</label>
                <select name="org_type_selector" class="select select-primary select-lg select-bordered"
                    id="org_type_select" onchange="this.form.submit()">
                    <option value="" {{ request('org_type_selector') === null || request('org_type_selector') === '' ? 'selected' : '' }}>
                        All Organization Types
                    </option>
                    @foreach ($organization_types as $type_code => $organization_type)
                        <option value="{{ $type_code }}" {{ (string) request('org_type_selector') === (string) $type_code ? 'selected' : '' }}>
                            {{ $organization_type }}
                        </option>
                    @endforeach
                </select>
            </form>

            <form action="{{ route('member.register_request') }}" method="post" class="flex flex-col gap-4">
                @csrf
                <label for="organization_id" class=" text-black pb-3 ">
                    Pick a Organization </label>
                <select class="select select-primary select-lg select-bordered" name="organization_id"
                    id="organization_id">
                    @if ($organizations->isEmpty())
                        <option value="" disabled>No organizations available</option>
                    @endif
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->organization_id }}">
                            {{ $organization->organizationDetail?->organization_name ?? 'N/A' }}
                        </option>
                    @endforeach
                </select>
                <br>
                <div class="hidden sm:flex justify-end">
                    <button type="submit" class="btn btn-primary text-white">Submit</button>
                </div>
                <button type="submit" class="block sm:hidden btn btn-primary text-white">Submit</button>
            </form>
        </div>


    </div>
</x-dashboard-layout>