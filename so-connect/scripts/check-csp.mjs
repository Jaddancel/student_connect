#!/usr/bin/env node
/*
 * Static check that the Blade views (and front-end JS) work under the app's
 * nonce-based Content-Security-Policy, which has no 'unsafe-inline' and no
 * 'unsafe-eval' (see app/Http/Middleware/SecurityHeaders.php). Flags:
 *
 *  - Alpine expressions the CSP build (@alpinejs/csp) cannot run. It
 *    interprets expressions with its own parser instead of `new Function`, so
 *    anything that parser rejects — or refuses at runtime (globals, `this`,
 *    writes to DOM objects, x-html, unregistered x-data) — silently breaks the
 *    component. Expressions go through the real parser, then the AST is
 *    screened for the runtime-only failures.
 *  - on*="" event handler attributes.
 *  - inline <script>/<style> elements without @nonce.
 *  - on*="" inside HTML built by JavaScript (view <script> blocks and
 *    resources/js), which the CSP blocks once inserted.
 *
 * style="" attributes are allowed (the policy sets style-src-attr
 * 'unsafe-inline'), so they are not reported.
 *
 * Usage: npm run check:csp [-- --verbose] [-- path/filter]
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { Tokenizer, Parser } from '@alpinejs/csp/src/parser.js';

const ROOT = new URL('..', import.meta.url).pathname;
const VIEWS = join(ROOT, 'resources/views');
const verbose = process.argv.includes('--verbose');

// Identifiers that resolve to browser globals: the CSP build only resolves
// names from Alpine's data/magic scope, so these throw "Undefined variable".
const GLOBALS = new Set([
    'this', 'window', 'document', 'globalThis', 'self', 'Math', 'JSON', 'Object', 'Array',
    'Number', 'String', 'Boolean', 'Date', 'RegExp', 'Symbol', 'Map', 'Set', 'Promise',
    'console', 'parseInt', 'parseFloat', 'isNaN', 'isFinite', 'setTimeout', 'setInterval',
    'clearTimeout', 'clearInterval', 'requestAnimationFrame', 'fetch', 'location', 'history',
    'navigator', 'alert', 'confirm', 'prompt', 'encodeURIComponent', 'decodeURIComponent',
    'Intl', 'localStorage', 'sessionStorage', 'URL', 'URLSearchParams', 'FormData', 'Blob',
    'structuredClone', 'axios', 'Alpine', 'event',
]);
// Magics whose properties are DOM objects; writing through them is refused.
const DOM_ROOTS = new Set(['$el', '$refs', '$root', '$event']);
const BLOCKED_METHODS = new Set([
    'insertAdjacentHTML', 'insertAdjacentElement', 'insertAdjacentText', 'setAttribute',
    'setAttributeNS', 'setAttributeNode', 'setAttributeNodeNS', 'createElement',
    'createElementNS', 'createDocumentFragment', 'createContextualFragment', 'appendChild',
    'insertBefore', 'replaceChild',
]);
// Alpine attributes whose value is not an expression.
const NON_EXPRESSION = /^x-(ref|cloak|ignore|teleport|collapse|transition|id$)/;

function walk(dir, ext = '.php') {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? walk(path, ext) : path.endsWith(ext) ? [path] : [];
    });
}

// Blank out a region while keeping offsets/newlines so line numbers stay right.
const blank = (s) => s.replace(/[^\n]/g, ' ');

function stripNonMarkup(src) {
    return src
        .replace(/\{\{--[\s\S]*?--\}\}/g, blank)
        .replace(/<!--[\s\S]*?-->/g, blank)
        .replace(/@php\b(?!\s*\()[\s\S]*?@endphp/g, blank)
        .replace(/<\?php[\s\S]*?\?>/g, blank)
        .replace(/(<script\b[^>]*>)([\s\S]*?)(<\/script>)/gi, (m, a, b, c) => a + blank(b) + c)
        .replace(/(<style\b[^>]*>)([\s\S]*?)(<\/style>)/gi, (m, a, b, c) => a + blank(b) + c);
}

// Index just past the bracket matching src[i] (an opening paren/brace).
function skipBalanced(src, i, open, close) {
    let depth = 0;
    let quote = null;
    for (; i < src.length; i++) {
        const ch = src[i];
        if (quote) {
            if (ch === '\\') i++;
            else if (ch === quote) quote = null;
        } else if (ch === '"' || ch === "'") quote = ch;
        else if (ch === open) depth++;
        else if (ch === close && --depth === 0) return i + 1;
    }
    return src.length;
}

// Index just past a Blade construct starting at i, or -1 if none starts here.
function skipBlade(src, i) {
    if (src.startsWith('{{', i)) return src.indexOf('}}', i + 2) + 2;
    if (src.startsWith('{!!', i)) return src.indexOf('!!}', i + 3) + 3;
    const directive = /^@(js|json|class|style|checked|selected|disabled|readonly|required)\s*\(/.exec(src.slice(i, i + 20));
    if (directive) return skipBalanced(src, i + directive[0].length - 1, '(', ')');
    return -1;
}

function* tags(src) {
    const tagStart = /<([a-zA-Z][\w:.-]*)/g;
    let m;
    while ((m = tagStart.exec(src))) {
        const name = m[1];
        let i = tagStart.lastIndex;
        const attrs = [];
        while (i < src.length) {
            while (/\s/.test(src[i])) i++;
            if (src[i] === '>' || src.startsWith('/>', i)) break;
            const blade = skipBlade(src, i);
            if (blade > 0) { i = blade; continue; }
            if (src[i] === '@' && /^@\w+\s*\(/.test(src.slice(i, i + 40))) {
                i = skipBalanced(src, src.indexOf('(', i), '(', ')');
                continue;
            }
            const nameMatch = /^[^\s=>\/"']+/.exec(src.slice(i));
            if (!nameMatch) { i++; continue; }
            const attrName = nameMatch[0];
            const attrPos = i;
            i += attrName.length;
            let value = null;
            if (src[i] === '=') {
                i++;
                const q = src[i];
                if (q === '"' || q === "'") {
                    let j = i + 1;
                    let out = '';
                    while (j < src.length && src[j] !== q) {
                        const end = skipBlade(src, j);
                        if (end > 0) {
                            const chunk = src.slice(j, end);
                            out += /\bJs::from\(/.test(chunk) ? '__BLADE_JS__' : '__BLADE__';
                            j = end;
                        } else out += src[j++];
                    }
                    value = out;
                    i = j + 1;
                } else {
                    const v = /^[^\s>]+/.exec(src.slice(i));
                    value = v ? v[0] : '';
                    i += value.length;
                }
            }
            attrs.push({ name: attrName, value, pos: attrPos });
        }
        tagStart.lastIndex = i;
        yield { name, attrs };
    }
}

function alpineExpression(tagName, attr) {
    let name = attr.name;
    if (tagName.startsWith('x-')) {
        // On Blade components a single ':' marks a PHP expression; '::' escapes
        // an Alpine binding through to the rendered element.
        if (name.startsWith('::')) name = name.slice(1);
        else if (name.startsWith(':')) return null;
    }
    const isAlpine = name.startsWith('x-') || name.startsWith('@') || name.startsWith(':');
    if (!isAlpine || NON_EXPRESSION.test(name) || attr.value == null) return null;
    let expr = attr.value
        .replace(/&quot;/g, '"').replace(/&#0?39;|&apos;/g, "'")
        .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
    if (expr.trim() === '') return null;
    if (name === 'x-for') {
        const m = /^\s*(?:\([^)]*\)|[\w$]+)\s+(?:in|of)\s+([\s\S]+)$/.exec(expr);
        expr = m ? m[1] : expr;
    }
    return { name, expr };
}

function screen(node, issues) {
    if (!node || typeof node !== 'object') return;
    if (Array.isArray(node)) { node.forEach((n) => screen(n, issues)); return; }
    switch (node.type) {
        case 'Identifier':
            if (GLOBALS.has(node.name)) issues.add(`global "${node.name}" is not in Alpine scope`);
            break;
        case 'MemberExpression':
            // `a.b` keeps `b` as an Identifier node that is a property name, not a variable.
            screen(node.object, issues);
            if (node.computed) screen(node.property, issues);
            return;
        case 'AssignmentExpression':
        case 'UpdateExpression': {
            const target = node.left ?? node.argument;
            let root = target;
            while (root?.type === 'MemberExpression') root = root.object;
            if (target?.type === 'MemberExpression' && root?.type === 'Identifier' && DOM_ROOTS.has(root.name)) {
                issues.add(`writes to a DOM object via ${root.name}`);
            }
            break;
        }
        case 'CallExpression':
            if (node.callee?.type === 'MemberExpression' && !node.callee.computed && BLOCKED_METHODS.has(node.callee.property.name)) {
                issues.add(`calls blocked method ${node.callee.property.name}()`);
            }
            break;
        case 'ObjectExpression':
            for (const prop of node.properties ?? []) {
                if (prop.computed) screen(prop.key, issues);
                screen(prop.value, issues);
            }
            return;
    }
    for (const [key, value] of Object.entries(node)) {
        if (key !== 'type' && value && typeof value === 'object') screen(value, issues);
    }
}

// x-data may only name components registered with Alpine.data(): the CSP
// build cannot reach a global function defined in an inline <script>.
const REGISTERED = new Set();
for (const file of [...walk(join(ROOT, 'resources/js'), '.js'), ...walk(VIEWS)]) {
    for (const m of readFileSync(file, 'utf8').matchAll(/Alpine\.data\(\s*['"]([\w$]+)['"]/g)) REGISTERED.add(m[1]);
}

function check(expr, attrName) {
    const issues = new Set();
    if (expr.includes('__BLADE_JS__')) {
        issues.add('Js::from() renders JSON.parse(...), a global; use @js() or JsLiteral::from()');
    }
    const source = expr.replace(/__BLADE(_JS)?__/g, '__blade__');
    try {
        const ast = new Parser(new Tokenizer(source).tokenize()).parse();
        screen(ast, issues);
        const component = ast.type === 'CallExpression' ? ast.callee : ast;
        if (attrName === 'x-data' && component.type === 'Identifier' && !REGISTERED.has(component.name)) {
            issues.add(`x-data component "${component.name}" is not registered with Alpine.data()`);
        }
    } catch (e) {
        issues.add(`parse error: ${e.message}`);
    }
    return [...issues];
}

const filters = process.argv.slice(2).filter((a) => !a.startsWith('--'));
const wanted = (file) => !filters.length || filters.some((f) => relative(ROOT, file).includes(f));

let problems = 0;
let checked = 0;
const report = (file, raw, pos, what, issues) => {
    problems++;
    const line = raw.slice(0, pos).split('\n').length;
    const shown = what.replace(/\s+/g, ' ').trim();
    console.log(`${relative(ROOT, file)}:${line}  ${verbose ? shown : shown.slice(0, 140)}`);
    issues.forEach((issue) => console.log(`    - ${issue}`));
};

// on*= event handlers written into HTML strings by JavaScript.
const JS_MARKUP = /(?:^|[\s"'`<])(on[a-z]{3,})\s*=\s*\\?["'`$]/g;
function checkScript(file, raw, code, offset) {
    for (const m of code.matchAll(JS_MARKUP)) {
        const lineStart = code.lastIndexOf('\n', m.index) + 1;
        const lineText = code.slice(lineStart, code.indexOf('\n', m.index) === -1 ? undefined : code.indexOf('\n', m.index));
        if (/^\s*(\/\/|\*)/.test(lineText)) continue;
        report(file, raw, offset + m.index, lineText, [`${m[1]}="" in HTML built by JavaScript is blocked by the CSP`]);
    }
}

for (const file of walk(VIEWS)) {
    if (file.includes('/views/mail/') || !wanted(file)) continue;
    const raw = readFileSync(file, 'utf8');
    const src = stripNonMarkup(raw);
    for (const tag of tags(src)) {
        const tagName = tag.name.toLowerCase();
        if (tagName === 'script' || tagName === 'style') {
            const names = tag.attrs.map((a) => a.name);
            const external = tagName === 'script' && names.includes('src');
            const data = tag.attrs.some((a) => a.name === 'type' && /json|template|x-tmpl/i.test(a.value ?? ''));
            if (!external && !data && !names.includes('@nonce') && !names.includes('nonce')) {
                report(file, raw, tag.attrs[0]?.pos ?? 0, `<${tagName}>`, [`inline <${tagName}> needs @nonce`]);
            }
        }
        for (const attr of tag.attrs) {
            if (/^on[a-z]+$/i.test(attr.name) && attr.value != null && !tagName.startsWith('x-')) {
                report(file, raw, attr.pos, `${attr.name}="${attr.value}"`, ['inline event handler is blocked by the CSP; use @event or addEventListener']);
                continue;
            }
            const found = alpineExpression(tag.name, attr);
            if (!found) continue;
            checked++;
            const issues = found.name === 'x-html'
                ? ['x-html is disabled in the CSP build; use x-text or render server-side']
                : check(found.expr, found.name);
            if (issues.length) report(file, raw, attr.pos, `${found.name}="${found.expr}"`, issues);
        }
    }
    for (const m of raw.matchAll(/<script\b(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/gi)) {
        checkScript(file, raw, m[1], m.index + m[0].indexOf(m[1]));
    }
}

for (const file of walk(join(ROOT, 'resources/js'), '.js')) {
    if (!wanted(file)) continue;
    const raw = readFileSync(file, 'utf8');
    checkScript(file, raw, raw, 0);
}

console.log(`\n${checked} Alpine expressions checked; ${problems} CSP problem(s) found.`);
process.exit(problems ? 1 : 0);
