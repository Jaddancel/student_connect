{{--
    Token palette — an OnlyOffice Document Server plugin, served per form.

    This exists because ONLYOFFICE Community Edition has no Automation API
    (`docEditor.createConnector()` is Developer-Edition-only), so the host page
    cannot insert content into the open document. A plugin runs *inside* the
    editor and can, via `Asc.plugin.executeMethod('PasteText', …)`.

    The form's tokens are baked in at render time rather than fetched, which is
    what lets a single generic plugin know which form it is editing — the
    plugin URL is minted per form in FormPrintTemplateController.

    The panel deliberately mirrors the form builder's own field palette: a
    field-type icon + label, the {{key}} it inserts, and category sections
    (Basic / Choice / Media, then Profile / Organization). It stays a narrow
    single column because the panel is docked at 320px and each row also shows
    the token text.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field tokens</title>
    <script type="text/javascript" src="{{ $sdkBase }}/sdkjs-plugins/v1/plugins.js"></script>
    <script type="text/javascript" src="{{ $sdkBase }}/sdkjs-plugins/v1/plugins-ui.js"></script>
    <style>
        /* Mirrors the form builder's palette (green brand accent, rounded card
           rows, icon column). Kept self-contained: this page can't reach Vite,
           Tailwind or the app's CSS variables. */
        :root {
            --brand-50: #dcfce7;
            --brand-500: #16a34a;
            --brand-400: #22c55e;
            --ink: #1f2430;
            --muted: #8b93a3;
            --line: #e3e6ec;
        }
        * { box-sizing: border-box; }
        /* Fill the docked panel exactly and confine scrolling to the list, so
           the hint + search stay pinned while a long field list scrolls on its
           own. The panel iframe clips overflow, so the body must NOT grow past
           it — it lays its children out as a flex column instead. OnlyOffice
           draws the panel's own title bar (with the plugin name + close) around
           this iframe, so this page adds none of its own. */
        html, body { height: 100%; }
        body {
            margin: 0;
            padding: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font: 13px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--ink);
            background: #fff;
        }
        .hint { flex: 0 0 auto; margin: 0 0 10px; color: #6b7280; font-size: 12px; }
        .search {
            flex: 0 0 auto;
            width: 100%; padding: 7px 9px 7px 30px; margin-bottom: 12px;
            border: 1px solid #d5d9e0; border-radius: 8px; font-size: 13px;
            background: #fff url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%238b93a3' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='7'/><line x1='21' y1='21' x2='16.65' y2='16.65'/></svg>") no-repeat 8px 50%;
        }
        .search:focus { outline: none; border-color: var(--brand-400); box-shadow: 0 0 0 3px rgba(34, 197, 94, .15); }
        /* Only the list scrolls. min-height:0 lets this flex child shrink below
           its content height — without it the child refuses to scroll and the
           overflow is clipped by the body instead. The negative margins let the
           scrollbar sit at the panel edge while rows keep their 12px inset. */
        #list { flex: 1 1 auto; min-height: 0; overflow-y: auto; margin: 0 -12px; padding: 0 12px; }
        .group { margin-bottom: 14px; }
        .group h2 {
            margin: 0 0 6px; font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .04em; color: var(--muted);
        }
        .token {
            display: flex; align-items: center; gap: 9px; width: 100%;
            margin-bottom: 5px; padding: 8px 9px; text-align: left;
            background: #fff; border: 1px solid var(--line);
            border-radius: 9px; cursor: pointer; font: inherit; color: var(--ink);
            transition: border-color .12s, background-color .12s;
        }
        .token:hover { border-color: var(--brand-400); background: var(--brand-50); }
        .token:focus-visible { outline: none; border-color: var(--brand-400); box-shadow: 0 0 0 3px rgba(34, 197, 94, .18); }
        .token .icon {
            flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 7px;
            background: var(--brand-50); color: var(--brand-500);
        }
        .token:hover .icon { background: #fff; }
        .token .icon svg { display: block; }
        .token .text { min-width: 0; }
        .token .label { display: block; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .token .key {
            display: block; font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 11px; color: var(--muted);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .empty { flex: 0 0 auto; color: var(--muted); font-size: 12px; }
        /* Activity-table column children: an indented, collapsible list under
           the parent table token. */
        .children { margin: 0 0 8px 14px; padding-left: 8px; border-left: 2px solid var(--line); }
        .children-toggle {
            display: block; width: 100%; text-align: left; background: none; border: 0;
            padding: 3px 2px; margin-bottom: 4px; color: var(--muted); font: inherit;
            font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; cursor: pointer;
        }
        .children-toggle:hover { color: var(--brand-500); }
        .token.child { padding: 6px 8px; }
    </style>
</head>
<body data-form-id="{{ $formId ?? '' }}">
    <p class="hint">Place the cursor in the document, then click a field to insert its token. A Table field inserts a table that prints one row per entry at generation time.</p>
    <input type="text" id="search" class="search" placeholder="Search fields…" autocomplete="off">
    <div id="list"></div>
    <p id="empty" class="empty" hidden>No matching fields.</p>

    <script id="token-data" type="application/json">@json($tokens)</script>

    @verbatim
    <script type="text/javascript">
        (function () {
            var tokens = JSON.parse(document.getElementById('token-data').textContent) || [];
            var list = document.getElementById('list');
            var empty = document.getElementById('empty');
            var search = document.getElementById('search');

            // Trusted, static SVG bodies keyed by the FieldType icon name the
            // server sends. Icons come ONLY from this map (never from token
            // data), so label/key stay rendered via textContent — XSS-safe.
            var ICON_BODY = {
                text:      '<path d="M5 6V5h14v1M12 5v14M9 19h6"/>',
                paragraph: '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/>',
                number:    '<line x1="10" y1="4" x2="8" y2="20"/><line x1="16" y1="4" x2="14" y2="20"/><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/>',
                age:       '<line x1="10" y1="4" x2="8" y2="20"/><line x1="16" y1="4" x2="14" y2="20"/><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/>',
                email:     '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
                date:      '<rect x="3" y="4" width="18" height="17" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/>',
                select:    '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M9 11l3 3 3-3"/>',
                radio:     '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.4" fill="currentColor" stroke="none"/>',
                checkbox:  '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8.5 12.5l2.4 2.4 4.6-5"/>',
                signature: '<path d="M3 17c3 0 3-8 6-8s3 8 6 8 3-4 6-4"/>',
                image:     '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5-8 8"/>',
                file:      '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/>',
                table:     '<rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="14" x2="21" y2="14"/><line x1="9" y1="4" x2="9" y2="20"/><line x1="15" y1="4" x2="15" y2="20"/>'
            };
            var ICON_DEFAULT = '<rect x="4" y="4" width="16" height="16" rx="4"/>';

            function iconSvg(name) {
                var body = ICON_BODY[name] || ICON_DEFAULT;
                return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
                    + ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + body + '</svg>';
            }

            // Built by concatenation so the braces are never mistaken for a
            // template expression by anything processing this file.
            function placeholder(key) {
                return '{' + '{' + key + '}' + '}';
            }

            // Per-form UI state for the Activity Table tokens: which have been
            // inserted (so their column children show) and which are expanded.
            // Hydrated from localStorage; a session-only fallback is acceptable.
            var formId = document.body.getAttribute('data-form-id') || '';
            var storageKey = 'tokenPalette:' + formId;
            var state = { inserted: {}, expanded: {} };
            try {
                var saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
                if (saved && typeof saved === 'object') {
                    state.inserted = saved.inserted || {};
                    state.expanded = saved.expanded || {};
                }
            } catch (e) { /* private mode / disabled storage: session-only */ }

            function persist() {
                try { window.localStorage.setItem(storageKey, JSON.stringify(state)); } catch (e) {}
            }

            function escapeHtml(value) {
                return String(value).replace(/[&<>"]/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
                });
            }

            // A token (or any of its children, for table tokens) matches the filter.
            function matches(token, needle) {
                if (!needle) return true;
                var hay = token.label + ' ' + token.key;
                (token.children || []).forEach(function (c) { hay += ' ' + c.label + ' ' + c.key; });
                return hay.toLowerCase().indexOf(needle) !== -1;
            }

            function childFilterMatch(token, needle) {
                return !!needle && (token.children || []).some(function (c) {
                    return (c.label + ' ' + c.key).toLowerCase().indexOf(needle) !== -1;
                });
            }

            function render(filter) {
                var needle = (filter || '').trim().toLowerCase();
                var groups = {};
                var order = [];
                var shown = 0;

                tokens.forEach(function (token) {
                    if (!matches(token, needle)) {
                        return;
                    }
                    if (!groups[token.group]) {
                        groups[token.group] = [];
                        order.push(token.group);
                    }
                    groups[token.group].push(token);
                    shown++;
                });

                list.textContent = '';

                order.forEach(function (name) {
                    var section = document.createElement('div');
                    section.className = 'group';

                    var heading = document.createElement('h2');
                    heading.textContent = name;
                    section.appendChild(heading);

                    groups[name].forEach(function (token) {
                        section.appendChild(tokenRow(token, needle));
                    });

                    list.appendChild(section);
                });

                empty.hidden = shown !== 0;
            }

            // One parent token row, plus (for table tokens that have been
            // inserted, or whose columns match the filter) its column children.
            function tokenRow(token, needle) {
                var wrap = document.createElement('div');

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'token';

                var icon = document.createElement('span');
                icon.className = 'icon';
                // Trusted static markup (see ICON_BODY); token.icon is only ever
                // used as a lookup key.
                icon.innerHTML = iconSvg(token.icon);

                var text = document.createElement('span');
                text.className = 'text';

                var label = document.createElement('span');
                label.className = 'label';
                label.textContent = token.label;

                var hasChildren = !!(token.children && token.children.length);

                var key = document.createElement('span');
                key.className = 'key';
                key.textContent = (token.insert === 'table') ? 'Inserts a table' : placeholder(token.key);

                text.appendChild(label);
                text.appendChild(key);
                button.appendChild(icon);
                button.appendChild(text);
                button.addEventListener('click', function () { insert(token); });
                wrap.appendChild(button);

                if (hasChildren && (state.inserted[token.key] || childFilterMatch(token, needle))) {
                    wrap.appendChild(childList(token, childFilterMatch(token, needle)));
                }

                return wrap;
            }

            function childList(token, forceExpanded) {
                var expanded = forceExpanded || !!state.expanded[token.key];
                var box = document.createElement('div');
                box.className = 'children';

                var toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'children-toggle';
                toggle.textContent = (expanded ? '▾ ' : '▸ ') + 'Columns (' + token.children.length + ')';
                toggle.addEventListener('click', function () {
                    state.expanded[token.key] = !state.expanded[token.key];
                    persist();
                    render(search.value);
                });
                box.appendChild(toggle);

                if (expanded) {
                    token.children.forEach(function (child) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'token child';

                        var ic = document.createElement('span');
                        ic.className = 'icon';
                        ic.innerHTML = iconSvg(child.icon);

                        var t = document.createElement('span');
                        t.className = 'text';
                        var l = document.createElement('span');
                        l.className = 'label';
                        l.textContent = child.label;
                        var k = document.createElement('span');
                        k.className = 'key';
                        k.textContent = placeholder(child.key + '#');
                        t.appendChild(l);
                        t.appendChild(k);
                        b.appendChild(ic);
                        b.appendChild(t);
                        // A single-cell repair: drop just this column's repeating token.
                        b.addEventListener('click', function () {
                            window.Asc.plugin.executeMethod('PasteText', [placeholder(child.key + '#')]);
                        });
                        box.appendChild(b);
                    });
                }

                return box;
            }

            function insert(token) {
                if (token.insert === 'table') {
                    insertTable(token);
                    return;
                }
                // PasteText drops the token at the cursor. Community Edition
                // exposes this to plugins even though the host page has no
                // equivalent.
                window.Asc.plugin.executeMethod('PasteText', [placeholder(token.key)]);
            }

            // Build a real Word table: a heading row of column labels and a data
            // row of `{{key.col#}}` tokens. Plain tr/td (not thead/th) so
            // PasteHtml maps row-for-row and the token cells land inside <w:tr>,
            // which is what cloneRow needs to repeat per approved activity.
            //
            // No inline CSS (borders/padding/width): hardcoded styling won't
            // match the template's institutional table/heading style and bakes a
            // mismatch into every generated row. Letting the table adopt the
            // document's default table style keeps the generated document
            // consistent with the rest of the template; the author can apply a
            // named table style in OnlyOffice after inserting.
            function insertTable(token) {
                var cols = token.children || [];
                if (!cols.length) {
                    // No columns chosen yet: fall back to one repeating token.
                    window.Asc.plugin.executeMethod('PasteText', [placeholder(token.key + '#')]);
                } else {
                    var head = '<tr>' + cols.map(function (c) {
                        return '<td><b>' + escapeHtml(c.label) + '</b></td>';
                    }).join('') + '</tr>';
                    var body = '<tr>' + cols.map(function (c) {
                        return '<td>' + escapeHtml(placeholder(c.key + '#')) + '</td>';
                    }).join('') + '</tr>';
                    var html = '<table>' + head + body + '</table>';
                    window.Asc.plugin.executeMethod('PasteHtml', [html]);
                }
                state.inserted[token.key] = true;
                state.expanded[token.key] = true;
                persist();
                render(search.value);
            }

            search.addEventListener('input', function () {
                render(search.value);
            });

            window.Asc.plugin.init = function () {
                render('');
            };

            window.Asc.plugin.button = function () {
                this.executeCommand('close', '');
            };
        })();
    </script>
    @endverbatim
</body>
</html>
