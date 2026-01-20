<x-dashboard-layout>
    <div class="container mx-auto lg:w-2/4 px-4 lg:px-0">
        <h1 class="text-xl font-bold my-3">Registration</h1>
        <form action="{{ route('membership.register') }}" method="post" class="flex flex-col  gap-4 p-6">
            @csrf
            <label for="org_picker" class="pb-3">
                Pick a Organization </label> <br>
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

</x-dashboard-layout>