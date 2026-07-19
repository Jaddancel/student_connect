{{--
    Accreditation conditions editor (type 2). Pick the forms whose APPROVED
    submission (before the deadline) makes an org accredited. Stored as
    AppSetting['accreditation.conditions'].required_forms and read back by
    AccreditationService.
--}}
<form method="POST" action="{{ route('settings.accreditation-conditions') }}" class="mt-4 space-y-4">
    @csrf

    @if (empty($accreditationForms))
        <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
            No forms are available yet. Build the accreditation form first, then choose it here.
        </p>
    @else
        <p class="text-sm text-gray-600 dark:text-gray-400">
            An organization is accredited for the cycle once every selected form has an approved
            submission before the deadline.
        </p>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($accreditationForms as $form)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="required_forms[]" value="{{ $form['id'] }}"
                        @checked(in_array($form['id'], $accreditationRequiredForms, true))
                        class="h-4 w-4 rounded border-gray-300 text-brand-500" />
                    <span>{{ $form['name'] }}</span>
                </label>
            @endforeach
        </div>
        <button type="submit"
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">
            Save conditions
        </button>
    @endif
</form>
