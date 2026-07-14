{{-- Generic top-right toast: $store.toast.open('Saved!') or .open(msg, 'error').
     Also auto-opens when a controller flashed session('toast') before a
     redirect, so full-page navigations can confirm a save too. --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('toast', {
            show: false,
            message: '',
            variant: 'success',
            _timer: null,
            open(message, variant = 'success') {
                this.message = message;
                this.variant = variant === 'error' ? 'error' : 'success';
                this.show = true;
                clearTimeout(this._timer);
                this._timer = setTimeout(() => { this.show = false; }, 4000);
            },
            dismiss() {
                this.show = false;
                clearTimeout(this._timer);
            },
        });
    });
</script>

<div x-data x-cloak x-show="$store.toast.show"
     @if (session('toast'))
         x-init="$store.toast.open(@js(session('toast')))"
     @elseif (session('toast_error'))
         x-init="$store.toast.open(@js(session('toast_error')), 'error')"
     @endif
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-2"
     class="fixed top-6 right-6 z-[100000] w-80 rounded-xl border bg-white shadow-xl dark:bg-gray-900"
     :class="$store.toast.variant === 'error'
         ? 'border-error-200 dark:border-error-500/30'
         : 'border-success-200 dark:border-success-500/30'"
     role="status">
    <div class="flex items-start gap-3 p-4">
        <span class="mt-0.5 flex-shrink-0"
              :class="$store.toast.variant === 'error' ? 'text-error-500' : 'text-success-500'">
            <svg x-show="$store.toast.variant !== 'error'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
            </svg>
            <svg x-show="$store.toast.variant === 'error'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
        </span>
        <p class="flex-1 min-w-0 text-sm font-semibold text-gray-900 dark:text-white"
           x-text="$store.toast.message"></p>
        <button @click="$store.toast.dismiss()"
                class="flex-shrink-0 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
            </svg>
        </button>
    </div>
</div>
