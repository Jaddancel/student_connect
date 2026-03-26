<x-ui.modal x-data="{ open: false }"
    @open-demographic-map-modal.window="open = true; $nextTick(() => setTimeout(() => window.dispatchEvent(new Event('demographic-map-modal-opened')), 120))"
    :isOpen="false" class="max-w-[96vw] rounded-2xl sm:max-w-[92vw] lg:max-w-[90vw]">
    <div
        class="relative h-[90vh] w-full overflow-hidden rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900 sm:h-[92vh] sm:p-4">
        <div class="pr-12 sm:pr-16">
            <h4 class="text-theme-xl font-semibold text-gray-800 dark:text-white/90">Member Demographic Map</h4>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                Expanded geographic view of member distribution.
            </p>
        </div>

        <div
            class="mt-3 h-[calc(90vh-90px)] overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900 sm:mt-4 sm:h-[calc(92vh-100px)]">
            <div id="mapOneModal" class="map-btn h-full w-full"></div>
        </div>
    </div>
</x-ui.modal>
