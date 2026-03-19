<x-dashboard-layout>
    <x-slot name="title">Calendar</x-slot>
    <div id='calendar' class="w-full h-full p-5"></div>

    <dialog id="event_modal" class="modal">
        <div class="modal-box">
            <h3 id="modal_title" class="font-bold text-lg"></h3>
            <p id="modal_body" class="py-4"></p>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Close</button>
                </form>
            </div>
        </div>
    </dialog>
</x-dashboard-layout>