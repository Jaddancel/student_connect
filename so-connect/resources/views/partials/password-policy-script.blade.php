{{--
    Shared Alpine factory backing every password field that enforces the
    StrongPassword policy (first-login wizard + Settings password form). Keeping
    the getters in one place stops the client hints from drifting apart or from
    App\Rules\StrongPassword. Spread `extra` to add form-specific state, e.g.
    x-data="passwordPolicyTools({ currentPassword: '', showCurrent: false })".
--}}
@once
    @push('scripts')
        <script>
            window.passwordPolicyTools = function (extra = {}) {
                return {
                    password: '',
                    confirmPassword: '',
                    showPassword: false,
                    showConfirm: false,
                    get minLength()      { return this.password.length >= 8; },
                    get hasUpper()       { return /[A-Z]/.test(this.password); },
                    get hasLower()       { return /[a-z]/.test(this.password); },
                    get hasNumber()      { return /[0-9]/.test(this.password); },
                    get hasSpecial()     { return /[^A-Za-z0-9]/.test(this.password); },
                    get allMet()         { return this.minLength && this.hasUpper && this.hasLower && this.hasNumber && this.hasSpecial; },
                    get passwordsMatch() { return this.confirmPassword !== '' && this.password === this.confirmPassword; },
                    ...extra,
                };
            };
        </script>
    @endpush
@endonce
