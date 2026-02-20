{{-- TODO: Make the registration controller work - Jad --}}

<x-dashboard-layout>
    <div class="container mx-auto lg:w-2/4 px-4 lg:px-0">
        <h1 class="text-xl text-black font-bold my-3">Registration</h1>
        <div id="form" class="flex flex-col gap-4 p-6">
            <form method="GET" action="membership_registration">
                <label for="org_type_selector" class="text-black pb-2 flex flex-col gap-4">Organization Type Label</label>
                <select name="org_type_selector" class="select select-primary select-lg select-bordered"
                    id="org_type_select" onchange="this.form.submit()">
                    @foreach ($organization_types as $organization_type)
                        <option value="{{ $organization_type->organization_type_code }}"
                            {{ request('org_type_selector') == $organization_type->organization_type_code ? 'selected' : '' }}>
                            {{ $organization_type->organization_type }}
                        </option>
                    @endforeach
                </select>
            </form>

            <form action="{{ route('membership.register') }}" method="post" class="flex flex-col gap-4">
                @csrf
                <label for="org_picker" class=" text-black pb-3 ">
                    Pick a Organization </label>
                <select class="select select-primary select-lg select-bordered" name="org_picker" id="org_picker">
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->organization_id }}">{{ $organization->organization_name }}</option>
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