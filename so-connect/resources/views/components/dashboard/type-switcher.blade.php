@php
    use Illuminate\Support\HtmlString;

    $KeyIcon = new HtmlString('
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
  <path fill-rule="evenodd" d="M8 7a5 5 0 1 1 3.61 4.804l-1.903 1.903A1 1 0 0 1 9 14H8v1a1 1 0 0 1-1 1H6v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2a1 1 0 0 1 .293-.707L8.196 8.39A5.002 5.002 0 0 1 8 7Zm5-3a.75.75 0 0 0 0 1.5A1.5 1.5 0 0 1 14.5 7 .75.75 0 0 0 16 7a3 3 0 0 0-3-3Z" clip-rule="evenodd" />
</svg>
    ');
    $CalendarIcon = new HtmlString('
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
  <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
</svg>
    ');

    $MemberIcon = new HtmlString('
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
        <path d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM1.49 15.326a.78.78 0 0 1-.358-.442 3 3 0 0 1 4.308-3.516 6.484 6.484 0 0 0-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 0 1-2.07-.655ZM16.44 15.98a4.97 4.97 0 0 0 2.07-.654.78.78 0 0 0 .357-.442 3 3 0 0 0-4.308-3.517 6.484 6.484 0 0 1 1.907 3.96 2.32 2.32 0 0 1-.026.654ZM18 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM5.304 16.19a.844.844 0 0 1-.277-.71 5 5 0 0 1 9.947 0 .843.843 0 0 1-.277.71A6.975 6.975 0 0 1 10 18a6.974 6.974 0 0 1-4.696-1.81Z" />
    </svg>
    ');
@endphp

<div class="flex flex-wrap items-center gap-3 md:gap-5">
    <x-ui.button x-on:click="$store.dashboardType.toggleType('membership')" size="md" variant="outline"
        :endIcon="$MemberIcon"
        x-bind:class="$store.dashboardType.isActive('membership') ?
            'ring-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 dark:ring-brand-500' :
            ''">
        Memberships
    </x-ui.button>
    <x-ui.button x-on:click="$store.dashboardType.toggleType('events')" size="md" variant="outline" :endIcon="$CalendarIcon"
        x-bind:class="$store.dashboardType.isActive('events') ?
            'ring-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 dark:ring-brand-500' :
            ''">
        Events
    </x-ui.button>
    <x-ui.button x-on:click="$store.dashboardType.toggleType('roles')" size="md" variant="outline" :endIcon="$KeyIcon"
        x-bind:class="$store.dashboardType.isActive('roles') ?
            'ring-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 dark:ring-brand-500' :
            ''">
        Roles and Security
    </x-ui.button>
</div>
