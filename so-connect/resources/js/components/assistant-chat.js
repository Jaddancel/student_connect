import { escapeHtml, renderMarkdown } from "../lib/markdown";

/**
 * ASSISTANT_CHAT widget: a floating panel that POSTs the running conversation
 * to POST /assistant/chat and renders the reply plus any deep-link chips it
 * returns. Message shape: { role: 'user'|'assistant', content: string,
 * links?: [{name, path}], failed?: bool }. `failed` marks a client-side
 * placeholder (network error / degraded response) — never sent back to the
 * server as conversation history.
 *
 * Assistant replies are Markdown and are rendered through `bubbleHtml()` (via
 * `renderBubble()`) at display time — `content` stays the raw reply, so what is persisted and what
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
        ticketEndpoint: config.ticketEndpoint,
        csrf: config.csrf,
        currentPath: config.currentPath || "",

        open: false,
        sending: false,
        draft: "",
        messages: [],
        escalating: false,
        creatingTicket: false,
        ticketSubject: "",
        ticketDescription: "",
        ticketError: "",
        ticketCreatedUrl: "",

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

        openEscalation() {
            const lastQuestion = [...this.messages]
                .reverse()
                .find((message) => message.role === "user" && !message.failed)?.content;
            this.ticketSubject = (lastQuestion || "Assistant support request").slice(0, 120);
            this.ticketDescription = lastQuestion || "I still need help with this conversation.";
            this.ticketError = "";
            this.ticketCreatedUrl = "";
            this.escalating = true;
        },

        async createTicket() {
            if (this.creatingTicket || !this.ticketSubject.trim() || !this.ticketDescription.trim()) return;
            this.creatingTicket = true;
            this.ticketError = "";
            try {
                const response = await fetch(this.ticketEndpoint, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": this.csrf,
                    },
                    body: JSON.stringify({
                        subject: this.ticketSubject.trim(),
                        description: this.ticketDescription.trim(),
                        current_path: this.currentPath,
                        messages: this.messages.filter((message) => !message.failed).slice(-20)
                            .map((message) => ({ role: message.role, content: message.content })),
                    }),
                });
                const data = await response.json();
                if (!response.ok || !data.ok || typeof data.url !== "string") throw new Error("ticket failed");
                this.ticketCreatedUrl = data.url;
                this.escalating = false;
            } catch (error) {
                this.ticketError = "The ticket could not be created. Please try again.";
            }
            this.creatingTicket = false;
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
         * Writes one assistant bubble's HTML into `el` (called from `x-effect`,
         * so it re-runs when the message changes). Replaces `x-html`, which
         * Alpine's CSP build doesn't support; the markup is still exactly what
         * `bubbleHtml()` produces.
         */
        renderBubble(el, message) {
            el.innerHTML = this.bubbleHtml(message);
        },

        /**
         * HTML for one assistant bubble. Always returns a non-empty string:
         * the result is assigned straight into `innerHTML`, and both a throw
         * and an `undefined` result would land there as the literal word
         * "undefined" — which is what a reply the
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
