import { renderMarkdown } from '../lib/markdown';

/**
 * ASSISTANT_CHAT widget: a floating panel that POSTs the running conversation
 * to POST /assistant/chat and renders the reply plus any deep-link chips it
 * returns. Message shape: { role: 'user'|'assistant', content: string,
 * links?: [{name, path}], failed?: bool }. `failed` marks a client-side
 * placeholder (network error / degraded response) — never sent back to the
 * server as conversation history.
 *
 * Assistant replies are Markdown and are rendered through lib/markdown.js at
 * display time — `content` stays the raw reply, so what is persisted and what
 * is replayed to the model is exactly what it sent. Only assistant bubbles go
 * through that path; the user's own text is printed verbatim.
 *
 * Non-streaming: the whole reply lands at once behind a typing indicator (no
 * SSE helper exists in the codebase yet). History persists to sessionStorage
 * for the tab's lifetime.
 */
export function assistantChat(config) {
    return {
        endpoint: config.endpoint,
        csrf: config.csrf,
        currentPath: config.currentPath || '',

        renderMarkdown,

        open: false,
        sending: false,
        draft: '',
        messages: [],

        init() {
            const saved = sessionStorage.getItem('assistantChat.messages');
            if (!saved) return;
            try {
                this.messages = JSON.parse(saved);
            } catch (e) {
                this.messages = [];
            }
        },

        toggleOpen() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        clear() {
            this.messages = [];
            sessionStorage.removeItem('assistantChat.messages');
        },

        persist() {
            sessionStorage.setItem('assistantChat.messages', JSON.stringify(this.messages));
        },

        scrollToBottom() {
            const log = this.$refs.log;
            if (log) log.scrollTop = log.scrollHeight;
        },

        async send() {
            const content = this.draft.trim();
            if (!content || this.sending) return;

            this.messages.push({ role: 'user', content });
            this.draft = '';
            this.sending = true;
            this.persist();
            this.$nextTick(() => this.scrollToBottom());

            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({
                        messages: this.messages
                            .filter((m) => !m.failed)
                            .slice(-20)
                            .map((m) => ({ role: m.role, content: m.content })),
                        current_path: this.currentPath,
                    }),
                });
                const data = await response.json();

                if (data.ok) {
                    this.messages.push({ role: 'assistant', content: data.reply, links: data.links || [] });
                } else {
                    this.pushUnavailable();
                }
            } catch (e) {
                this.pushUnavailable();
            }

            this.sending = false;
            this.persist();
            this.$nextTick(() => this.scrollToBottom());
        },

        pushUnavailable() {
            this.messages.push({
                role: 'assistant',
                content: "Sorry, I'm unavailable right now — please try again shortly.",
                failed: true,
            });
        },
    };
}
