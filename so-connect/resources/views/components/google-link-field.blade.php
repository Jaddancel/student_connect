{{--
    "Sign in with Google" pre-fill for the admin/officer account-creation forms.

    Opens the stateless Google OAuth popup (GoogleLinkController), receives the
    applicant's Google profile via postMessage, and fills the form's name/email
    inputs plus a hidden `google_id`. It NEVER logs the applicant in — the
    signed-in SuperAdmin/Admin keeps their session. Left unconfigured, the popup
    reports a graceful error and the form still works as a manual entry.
--}}
<div class="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/[0.08]">
    <input type="hidden" id="google_id" name="google_id" value="{{ old('google_id') }}" />

    <p class="flex items-start gap-2 text-xs text-gray-600 dark:text-gray-300">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <span>The applicant admin must be physically present, or have authorized a representative, to sign in to his/her account.</span>
    </p>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <button type="button" data-google-link
            class="inline-flex items-center justify-center gap-2.5 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-white/90 dark:hover:bg-white/10">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18.7511 10.1944C18.7511 9.47495 18.6915 8.94995 18.5626 8.40552H10.1797V11.6527H15.1003C15.0011 12.4597 14.4654 13.675 13.2749 14.4916L13.2582 14.6003L15.9087 16.6126L16.0924 16.6305C17.7788 15.1041 18.7511 12.8583 18.7511 10.1944Z" fill="#4285F4" />
                <path d="M10.1788 18.75C12.5895 18.75 14.6133 17.9722 16.0915 16.6305L13.274 14.4916C12.5201 15.0068 11.5081 15.3666 10.1788 15.3666C7.81773 15.3666 5.81379 13.8402 5.09944 11.7305L4.99473 11.7392L2.23868 13.8295L2.20264 13.9277C3.67087 16.786 6.68674 18.75 10.1788 18.75Z" fill="#34A853" />
                <path d="M5.10014 11.7305C4.91165 11.186 4.80257 10.6027 4.80257 9.99992C4.80257 9.3971 4.91165 8.81379 5.09022 8.26935L5.08523 8.1534L2.29464 6.02954L2.20333 6.0721C1.5982 7.25823 1.25098 8.5902 1.25098 9.99992C1.25098 11.4096 1.5982 12.7415 2.20333 13.9277L5.10014 11.7305Z" fill="#FBBC05" />
                <path d="M10.1789 4.63331C11.8554 4.63331 12.9864 5.34303 13.6312 5.93612L16.1511 3.525C14.6035 2.11528 12.5895 1.25 10.1789 1.25C6.68676 1.25 3.67088 3.21387 2.20264 6.07218L5.08953 8.26943C5.81381 6.15972 7.81776 4.63331 10.1789 4.63331Z" fill="#EB4335" />
            </svg>
            Sign in with Google
        </button>
        <span data-google-status class="text-xs font-medium text-gray-500 dark:text-gray-400" aria-live="polite"></span>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                var redirectUrl = @json(route('admin.accounts.google.redirect'));
                var configured = @json(\App\Http\Controllers\Auth\GoogleLinkController::isConfigured());
                var origin = window.location.origin;
                var popup = null;

                function setField(id, value) {
                    var el = document.getElementById(id);
                    if (el && value) { el.value = value; }
                }

                function setStatus(msg, isError) {
                    document.querySelectorAll('[data-google-status]').forEach(function (el) {
                        el.textContent = msg;
                        el.classList.toggle('text-error-500', !!isError);
                        el.classList.toggle('text-success-600', !isError && !!msg);
                    });
                }

                document.querySelectorAll('[data-google-link]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!configured) {
                            setStatus('Google sign-in is not configured yet. See GOOGLE-AUTH-SETUP.md.', true);
                            return;
                        }
                        setStatus('Opening Google…', false);
                        var w = 500, h = 600;
                        var left = window.screenX + (window.outerWidth - w) / 2;
                        var top = window.screenY + (window.outerHeight - h) / 2;
                        popup = window.open(redirectUrl, 'google-link',
                            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top);
                    });
                });

                window.addEventListener('message', function (event) {
                    if (event.origin !== origin) { return; }
                    var data = event.data || {};
                    if (data.source !== 'google-link') { return; }

                    if (data.error) {
                        setStatus(data.error, true);
                        return;
                    }

                    setField('first_name', data.first_name);
                    setField('last_name', data.last_name);
                    setField('email', data.email);
                    setField('google_id', data.google_id);
                    // Only present when the applicant has these set AND consented
                    // to share them on the Google OAuth screen — see GoogleLinkController.
                    setField('sex', data.sex);
                    setField('birthday', data.birthday);
                    setField('present_address', data.present_address);

                    var name = [data.first_name, data.last_name].filter(Boolean).join(' ');
                    setStatus('Linked to Google' + (data.email ? ' (' + data.email + ')' : name ? ' (' + name + ')' : '') + '.', false);
                });
            })();
        </script>
    @endpush
@endonce
