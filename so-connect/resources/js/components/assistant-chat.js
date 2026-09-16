import { escapeHtml, renderMarkdown } from "../lib/markdown";

/**
 * ASSISTANT_CHAT widget: a floating panel that POSTs the running conversation
 * to POST /assistant/chat and renders the reply plus any deep-link chips it
 * returns. Message shape: { role: 'user'|'assistant', content: string,
 * links?: [{name, path}], failed?: bool }. `failed` marks a client-side
 * placeholder (network error / degraded response) — never sent back to the
 * server as conversation history.
 *
 * Assistant replies are Markdown and are rendered through `bubbleHtml()` at
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
        currentPath: config.currentPath || "",

        open: false,
        sending: false,
        draft: "",
        messages: [],

        init() {
            const saved = sessionStorage.getItem(this.storageKey());
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
                this.ensureWelcome();
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        ensureWelcome() {
            if (
                this.messages.some(
                    (message) => !message.failed && !message.initialGreeting,
                )
            ) {
                return;
            }

            this.messages = [];
            this.messages.push({
                role: "assistant",
                content: "Hi! How can I help?",
                initialGreeting: true,
            });
            this.persist();
            this.send(true);
        },

        askSuggestion(suggestion) {
            this.draft = suggestion;
            this.send();
        },

        clear() {
            this.messages = [];
            sessionStorage.removeItem(this.storageKey());
        },

        storageKey() {
            return `assistantChat.messages.${encodeURIComponent(this.currentPath || "/")}`;
        },

        persist() {
            sessionStorage.setItem(
                this.storageKey(),
                JSON.stringify(this.messages),
            );
        },

        scrollToBottom() {
            const log = this.$refs.log;
            if (log) log.scrollTop = log.scrollHeight;
        },

        /**
         * HTML for one assistant bubble. Always returns a non-empty string:
         * Alpine assigns whatever an `x-html` expression evaluates to straight
         * into `innerHTML`, and both a throw and an `undefined` result land
         * there as the literal word "undefined" — which is what a reply the
         * renderer choked on, or one the sidecar returned empty, used to look
         * like in the panel. Anything the renderer can't turn into markup
         * degrades to the escaped text instead.
         */
        bubbleHtml(message) {
            const content =
                typeof message?.content === "string" ? message.content : "";

            let html = "";
            try {
                html = renderMarkdown(content) || "";
            } catch (e) {
                html = "";
            }
            if (html !== "") return html;

            const fallback =
                content.trim() !== ""
                    ? content
                    : (message?.links || []).length > 0
                      ? "Here’s the page for that:"
                      : "Sorry, I couldn't put an answer together for that — try rephrasing it?";

            return `<p>${escapeHtml(fallback).replace(/\n/g, "<br>")}</p>`;
        },

        async send(opening = false) {
            const content = this.draft.trim();
            if ((!content && !opening) || this.sending) return;

            if (!opening) {
                this.messages.push({ role: "user", content });
                this.draft = "";
            }
            this.sending = true;
            this.persist();
            this.$nextTick(() => this.scrollToBottom());

            try {
                const response = await fetch(this.endpoint, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": this.csrf,
                    },
                    body: JSON.stringify({
                        messages: this.messages
                            .filter((m) => !m.failed)
                            .slice(-20)
                            .map((m) => ({ role: m.role, content: m.content })),
                        current_path: this.currentPath,
                        opening,
                    }),
                });
                const data = await response.json();
                // `reply` is coerced here rather than trusted: a bubble whose
                // content isn't a string has nothing to render, so a malformed
                // response is treated as a failed turn instead of being pushed
                // into the history and replayed to the model on the next one.
                const reply = typeof data.reply === "string" ? data.reply : "";
                const links = Array.isArray(data.links) ? data.links : [];
                const suggestions = Array.isArray(data.suggestions)
                    ? data.suggestions.filter(
                          (suggestion) => typeof suggestion === "string",
                      )
                    : [];

                if (data.ok && (reply !== "" || links.length > 0)) {
                    this.messages.push({
                        role: "assistant",
                        content: reply,
                        links,
                        suggestions,
                    });
                } else if (data.ok && suggestions.length > 0) {
                    this.messages.push({
                        role: "assistant",
                        content: "Here are some ways I can help:",
                        suggestions,
                    });
                } else {
                    this.pushUnavailable(opening);
                }
            } catch (e) {
                this.pushUnavailable(opening);
            }

            this.sending = false;
            this.persist();
            this.$nextTick(() => this.scrollToBottom());
        },

        pushUnavailable(opening = false) {
            this.messages.push({
                role: "assistant",
                content:
                    "Sorry, I'm unavailable right now — please try again shortly.",
                failed: true,
                opening,
            });
        },
    };
}
