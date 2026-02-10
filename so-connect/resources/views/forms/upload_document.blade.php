<x-dashboard-layout>
    <x-slot name="title">Upload Document</x-slot>
    <div class="container">
        <h2 class="text-3xl text-black font-bold pb-6">Upload Document</h2>

        <form class="form bg-gray-100 flex flex-col gap-4 p-4 rounded-lg" enctype="multipart/form-data">
            <label class="text-black">Select File</label>
            <input class="p-2 border rounded text-black" type="file">

            <label class="text-black">Organization</label>
            <select class="p-2 border rounded text-black">
                <option>Student Government Association</option>
                <option>Environmental Club</option>
            </select>

            <label class="text-black">Document Type</label>
            <select class="p-2 border rounded text-black">
                <option>Meeting Minutes</option>
                <option>Budget Report</option>
            </select>

            <fieldset>
                <legend class="text-black fieldset-legend">Description</legend>
                <textarea class="w-full p-2 border rounded text-black" rows="4"
                    placeholder="Enter a brief description of the document..."></textarea>
            </fieldset>

            <button class="p-2 btn btn-primary text-white rounded" type="submit">Upload</button>
        </form>
    </div>
</x-dashboard-layout>