{{--
    Floating AI assistant trigger + panel, mounted once in the navbar.
    Read-only "how do I…" helper — see docs/assistant-contract.md.
--}}
<div x-data="assistantChat({
        endpoint: '{{ route('assistant.chat') }}',
        csrf: '{{ csrf_token() }}',
        currentPath: {{ Illuminate\Support\Js::from(request()->getPathInfo()) }},
    })" @keydown.escape.window="open = false">

    {{-- Trigger — identical size/hover treatment to the theme toggle and notification bell --}}
    <button
        class="relative flex items-center justify-center text-gray-500 transition-colors bg-white border border-gray-200 rounded-full hover:text-dark-900 h-11 w-11 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        @click="toggleOpen()" type="button" aria-label="AI assistant">
        {!! \App\Helpers\MenuHelper::getIconSvg('ai-assistant') !!}
    </button>

    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="fixed bottom-6 right-6 z-[99990] flex h-[32rem] w-[22rem] max-w-[calc(100vw-3rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
        style="display: none;">

        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            <h5 class="text-sm font-semibold text-gray-800 dark:text-white/90">Assistant</h5>
            <div class="flex items-center gap-3">
                <button @click="clear()" x-show="messages.length > 0" type="button"
                    class="text-xs font-medium text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Clear</button>
                <button @click="open = false" type="button" aria-label="Close" class="text-gray-500 dark:text-gray-400">
                    <svg class="fill-current" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                            fill=""></path>
                    </svg>
                </button>
            </div>
        </div>

        <div x-ref="log" class="flex-1 space-y-3 overflow-y-auto px-4 py-3 custom-scrollbar">
            <template x-if="messages.length === 0">
                <div class="mt-2 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                    <p>Ask me how to do something in Student Connect — e.g.:</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>"How do I publish a form?"</li>
                        <li>"Where do I approve a workplan?"</li>
                    </ul>
                </div>
            </template>

            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div class="max-w-[85%] space-y-2">
                        {{--
                            Assistant replies are Markdown (see the model's formatting
                            brief in AssistantController::buildSystemPrompt) and are the
                            only bubble rendered as HTML. bubbleHtml escapes before it
                            emits any tag and never produces an <a>, so nothing the model
                            writes can turn into live markup — resources/js/lib/markdown.js.

                            The `typeof` guard is what keeps a build of app.js older than
                            this template from printing the word "undefined" in every
                            reply: Alpine writes an expression that throws straight into
                            innerHTML as `undefined`, so a missing bubbleHtml has to fall
                            through to the plain-text bubble below instead.
                        --}}
                        <template x-if="message.role === 'assistant' && !message.failed && typeof bubbleHtml === 'function'">
                            <div class="assistant-markdown rounded-2xl rounded-bl-sm bg-gray-100 px-3.5 py-2 text-sm text-gray-800 dark:bg-white/5 dark:text-white/90"
                                x-html="bubbleHtml(message)"></div>
                        </template>

                        {{-- The user's own text, the degraded placeholder, and any reply
                             the renderer above couldn't be reached for, stay verbatim. --}}
                        <template x-if="message.role !== 'assistant' || message.failed || typeof bubbleHtml !== 'function'">
                            <div
                                :class="message.role === 'user'
                                    ? 'rounded-2xl rounded-br-sm bg-brand-500 px-3.5 py-2 text-sm text-white'
                                    : (message.failed
                                        ? 'rounded-2xl rounded-bl-sm bg-error-50 px-3.5 py-2 text-sm text-error-600 dark:bg-error-500/10 dark:text-error-400'
                                        : 'rounded-2xl rounded-bl-sm bg-gray-100 px-3.5 py-2 text-sm text-gray-800 dark:bg-white/5 dark:text-white/90')"
                                style="white-space: pre-wrap;"
                                x-text="message.content || ''"></div>
                        </template>
                        <div x-show="(message.links || []).length > 0" class="flex flex-wrap gap-1.5">
                            <template x-for="link in (message.links || [])" :key="link.path">
                                <a :href="link.path"
                                    class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-600 hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20"
                                    x-text="link.name"></a>
                            </template>
                        </div>
                        <div x-show="(message.suggestions || []).length > 0" class="flex flex-wrap gap-1.5">
                            <template x-for="suggestion in (message.suggestions || [])" :key="suggestion">
                                <button type="button" @click="askSuggestion(suggestion)"
                                    class="rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-left text-xs font-medium text-brand-600 hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20"
                                    x-text="suggestion"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="sending" class="flex justify-start">
                <div class="flex items-center gap-1 rounded-2xl rounded-bl-sm bg-gray-100 px-3.5 py-2.5 dark:bg-white/5">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                </div>
            </div>
        </div>

        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-100 p-3 dark:border-gray-800">
            <textarea
                x-model="draft"
                @keydown.enter.prevent="if (!$event.shiftKey) send()"
                rows="1"
                placeholder="Ask a question…"
                class="dark:bg-dark-900 max-h-24 flex-1 resize-none rounded-lg border border-gray-200 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-800 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"></textarea>
            <button type="submit" :disabled="sending || !draft.trim()" aria-label="Send"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 20L20 12L4 4V10L15 12L4 14V20Z" fill="currentColor" />
                </svg>
            </button>
        </form>
    </div>
</div>
