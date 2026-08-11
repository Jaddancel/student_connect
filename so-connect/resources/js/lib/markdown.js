/**
 * Dependency-free Markdown renderer for assistant chat replies.
 *
 * The model behind the assistant writes Markdown whether or not it is asked to
 * — bold UI labels, numbered steps, backticked field keys — and the chat bubble
 * used to print the reply verbatim, so answers read as literal `**Form
 * Builder**` and `1.` noise. This turns the subset the model actually reaches
 * for into HTML, keeping the bundle free of the `marked` + `dompurify` pair
 * this codebase has so far done without (same reasoning as
 * lib/perspective-warp.js).
 *
 * Safety: the input is escaped before any tag is introduced, and the only tags
 * emitted are the fixed set below — so a reply is inert markup no matter what
 * the model, or anything that talked its way into the model, writes. Notably
 * absent is `<a>`: per docs/assistant-contract.md the assistant must never
 * link, deep links arrive separately as chips already validated against the
 * user's own page index, so `[label](url)` renders as its label with the
 * destination dropped. That also leaves no href for a `javascript:` payload to
 * ride in on.
 *
 * Supported: ATX headings, nested ordered/unordered lists, fenced and inline
 * code, blockquotes, thematic breaks, bold, italic, strikethrough. Anything
 * else degrades to the plain text it was written as.
 */

const HTML_ESCAPES = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

// Block openers. Leading indent is capped at 3 spaces the way CommonMark does
// it, so a deeper indent stays list-item content instead of silently becoming
// a new block.
const FENCE = /^ {0,3}(`{3,}|~{3,})\s*\S*\s*$/;
const HEADING = /^ {0,3}(#{1,6})\s+(.+?)\s*#*\s*$/;
const RULE = /^ {0,3}([-*_])[ \t]*(?:\1[ \t]*){2,}$/;
const QUOTE = /^ {0,3}>[ \t]?(.*)$/;
const BULLET = /^([ \t]*)[-*+][ \t]+(.*)$/;
const ORDERED = /^([ \t]*)(\d{1,9})[.)][ \t]+(.*)$/;

// Sentinel for parked inline-code spans. Stripped from the input first so it
// can never arrive from outside.
const CODE_MARK = '\u0000';

/**
 * Render a Markdown string as a trusted HTML fragment.
 *
 * @param {string} source raw reply text from the assistant
 * @returns {string} HTML safe to hand to `x-html`
 */
export function renderMarkdown(source) {
    if (typeof source !== 'string') return '';

    const text = source.split(CODE_MARK).join('').replace(/\r\n?/g, '\n');
    if (text.trim() === '') return '';

    return renderBlocks(text.split('\n'));
}

/**
 * Exported so a caller that has to fall back to plain text — see
 * `bubbleHtml()` in components/assistant-chat.js — escapes it the same way
 * this module does rather than reaching for its own.
 *
 * @param {string} text
 * @returns {string}
 */
export function escapeHtml(text) {
    return text.replace(/[&<>"']/g, (char) => HTML_ESCAPES[char]);
}

/**
 * Tabs count as four columns when measuring list indentation, so a tab-indented
 * sub-list nests the same way a space-indented one does.
 *
 * @param {string} line
 * @returns {string}
 */
function expandTabs(line) {
    return line.replace(/^[ \t]+/, (indent) => indent.replace(/\t/g, '    '));
}

/**
 * @param {string} line
 * @returns {boolean} true when the line opens a block that outranks a paragraph
 */
function isBlockStart(line) {
    return FENCE.test(line) || HEADING.test(line) || RULE.test(line) || QUOTE.test(line);
}

/**
 * @param {string} line
 * @returns {boolean}
 */
function isListStart(line) {
    const expanded = expandTabs(line);

    return !RULE.test(expanded) && (BULLET.test(expanded) || ORDERED.test(expanded));
}

/**
 * @param {string[]} lines
 * @returns {string}
 */
function renderBlocks(lines) {
    const out = [];
    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        if (line.trim() === '') {
            i++;
            continue;
        }

        const fence = line.match(FENCE);
        if (fence) {
            const closer = new RegExp(`^ {0,3}${fence[1][0] === '`' ? '`' : '~'}{${fence[1].length},}\\s*$`);
            const body = [];
            i++;
            while (i < lines.length && !closer.test(lines[i])) {
                body.push(lines[i]);
                i++;
            }
            // An unterminated fence runs to the end of the reply — which is what
            // a mid-stream truncation looks like, so render what arrived.
            if (i < lines.length) i++;
            out.push(`<pre><code>${escapeHtml(body.join('\n'))}</code></pre>`);
            continue;
        }

        const heading = line.match(HEADING);
        if (heading) {
            const level = heading[1].length;
            out.push(`<h${level}>${renderInline(heading[2])}</h${level}>`);
            i++;
            continue;
        }

        // Checked before the list openers: `- - -` satisfies both.
        if (RULE.test(line)) {
            out.push('<hr>');
            i++;
            continue;
        }

        if (QUOTE.test(line)) {
            const body = [];
            while (i < lines.length) {
                const quoted = lines[i].match(QUOTE);
                if (quoted) {
                    body.push(quoted[1]);
                    i++;
                    continue;
                }
                if (lines[i].trim() === '' || isBlockStart(lines[i]) || isListStart(lines[i])) break;
                body.push(lines[i]);
                i++;
            }
            out.push(`<blockquote>${renderBlocks(body)}</blockquote>`);
            continue;
        }

        if (isListStart(line)) {
            const [items, next] = collectList(lines, i);
            out.push(renderList(items));
            i = next;
            continue;
        }

        const paragraph = [];
        while (
            i < lines.length &&
            lines[i].trim() !== '' &&
            !isBlockStart(lines[i]) &&
            !isListStart(lines[i])
        ) {
            paragraph.push(lines[i].trim());
            i++;
        }
        if (paragraph.length === 0) {
            i++; // Unreachable in practice; keeps a malformed line from stalling the loop.
            continue;
        }
        out.push(`<p>${renderInline(paragraph.join('\n'))}</p>`);
    }

    return out.join('');
}

/**
 * Gather one run of list items starting at `start`, flattened with their indent
 * so {@see renderList} can rebuild the nesting.
 *
 * @param {string[]} lines
 * @param {number} start
 * @returns {[Array<{indent: number, ordered: boolean, start: number, text: string}>, number]} items and the index after them
 */
function collectList(lines, start) {
    const items = [];
    let i = start;

    while (i < lines.length) {
        const line = expandTabs(lines[i]);

        if (!RULE.test(line)) {
            const bullet = line.match(BULLET);
            const ordered = line.match(ORDERED);
            if (bullet || ordered) {
                items.push({
                    indent: (bullet ? bullet[1] : ordered[1]).length,
                    ordered: Boolean(ordered),
                    start: ordered ? Number(ordered[2]) : 1,
                    text: bullet ? bullet[2] : ordered[3],
                });
                i++;
                continue;
            }
        }

        if (line.trim() === '') {
            // A blank line ends the list only if an item doesn't resume after
            // it — models space out steps and still mean one list.
            let j = i + 1;
            while (j < lines.length && lines[j].trim() === '') j++;
            if (j < lines.length && isListStart(lines[j])) {
                i = j;
                continue;
            }
            break;
        }

        if (isBlockStart(line) || items.length === 0) break;

        // A wrapped or lazily-indented continuation of the item above it.
        items[items.length - 1].text += ` ${line.trim()}`;
        i++;
    }

    return [items, i];
}

/**
 * @param {Array<{indent: number, ordered: boolean, start: number, text: string}>} items
 * @returns {string}
 */
function renderList(items) {
    if (items.length === 0) return '';

    // Anchored on the first item rather than the shallowest, so a reply that
    // opens over-indented still consumes an item per pass and terminates.
    const base = items[0].indent;
    let html = '';
    let i = 0;

    while (i < items.length) {
        const ordered = items[i].ordered;
        const tag = ordered ? 'ol' : 'ul';
        // Honour a list that picks up its numbering mid-reply (`3.` first).
        const startAt = ordered && items[i].start > 1 ? ` start="${Number(items[i].start)}"` : '';
        let entries = '';

        while (i < items.length && items[i].ordered === ordered && items[i].indent <= base) {
            let j = i + 1;
            while (j < items.length && items[j].indent > base) j++;
            const nested = items.slice(i + 1, j);
            entries += `<li>${renderInline(items[i].text)}${renderList(nested)}</li>`;
            i = j;
        }

        html += `<${tag}${startAt}>${entries}</${tag}>`;
    }

    return html;
}

/**
 * Escape a run of text and apply the inline rules to it.
 *
 * @param {string} text
 * @returns {string}
 */
function renderInline(text) {
    // Park code spans before anything else so `**` inside them stays literal.
    const spans = [];
    let out = text.replace(/`([^`\n]+)`/g, (match, code) => {
        spans.push(code);

        return `${CODE_MARK}${spans.length - 1}${CODE_MARK}`;
    });

    out = escapeHtml(out);

    // Images and links: keep the label, drop the destination (see the module
    // docblock — the assistant links through validated chips, never inline).
    // The destination allows one level of nested parens so a `foo(1)` tail is
    // swallowed whole rather than leaving a stray `)` behind; the alternation's
    // two branches can't match the same character, so it can't backtrack badly.
    out = out.replace(/!\[([^\]\n]*)\]\((?:[^()\n]|\([^()\n]*\))*\)/g, '$1');
    out = out.replace(/\[([^\]\n]+)\]\((?:[^()\n]|\([^()\n]*\))*\)/g, '$1');

    out = out.replace(/\*\*(?=\S)([\s\S]*?\S)\*\*/g, '<strong>$1</strong>');
    // `__` and `_` are word-boundary gated: this dashboard's vocabulary is full
    // of bare underscores (`user_type`, `form_id`) that must not italicise.
    out = out.replace(/(^|[\s(\[])__(?=\S)([\s\S]*?\S)__(?!\w)/g, '$1<strong>$2</strong>');
    out = out.replace(/\*(?=\S)([^*\n]*?\S)\*/g, '<em>$1</em>');
    out = out.replace(/(^|[\s(\[])_(?=\S)([^_\n]*?\S)_(?!\w)/g, '$1<em>$2</em>');
    out = out.replace(/~~(?=\S)([\s\S]*?\S)~~/g, '<del>$1</del>');

    // A single newline inside a paragraph is a break here rather than a space:
    // it preserves the shape the old pre-wrap bubble gave short chat replies.
    out = out.replace(/\n/g, '<br>');

    return out.replace(
        new RegExp(`${CODE_MARK}(\\d+)${CODE_MARK}`, 'g'),
        (match, index) => `<code>${escapeHtml(spans[Number(index)])}</code>`,
    );
}
