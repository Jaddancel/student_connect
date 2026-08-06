{{--
    Token palette — an OnlyOffice Document Server plugin, served per form.

    This exists because ONLYOFFICE Community Edition has no Automation API
    (`docEditor.createConnector()` is Developer-Edition-only), so the host page
    cannot insert content into the open document. A plugin runs *inside* the
    editor and can, via `Asc.plugin.executeMethod('PasteText', …)`.

    The form's tokens are baked in at render time rather than fetched, which is
    what lets a single generic plugin know which form it is editing — the
    plugin URL is minted per form in FormPrintTemplateController.
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
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            font: 13px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1f2430;
            background: #fff;
        }
        .hint { margin: 0 0 10px; color: #6b7280; font-size: 12px; }
        .search {
            width: 100%; padding: 7px 9px; margin-bottom: 12px;
            border: 1px solid #d5d9e0; border-radius: 6px; font-size: 13px;
        }
        .search:focus { outline: none; border-color: #4f7df3; }
        .group { margin-bottom: 14px; }
        .group h2 {
            margin: 0 0 6px; font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .04em; color: #8b93a3;
        }
        .token {
            display: block; width: 100%; margin-bottom: 4px; padding: 7px 9px;
            text-align: left; background: #fff; border: 1px solid #e3e6ec;
            border-radius: 6px; cursor: pointer; font: inherit;
        }
        .token:hover { border-color: #4f7df3; background: #f3f6fe; }
        .token .label { display: block; font-weight: 500; }
        .token .key { display: block; font-family: ui-monospace, Menlo, Consolas, monospace;
                      font-size: 11px; color: #8b93a3; }
        .empty { color: #8b93a3; font-size: 12px; }
    </style>
</head>
<body>
    <p class="hint">Place the cursor in the document, then click a field to insert its token.</p>
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

            // Built by concatenation so the braces are never mistaken for a
            // template expression by anything processing this file.
            function placeholder(key) {
                return '{' + '{' + key + '}' + '}';
            }

            function render(filter) {
                var needle = (filter || '').trim().toLowerCase();
                var groups = {};
                var order = [];
                var shown = 0;

                tokens.forEach(function (token) {
                    var haystack = (token.label + ' ' + token.key).toLowerCase();
                    if (needle && haystack.indexOf(needle) === -1) {
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
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'token';

                        var label = document.createElement('span');
                        label.className = 'label';
                        label.textContent = token.label;

                        var key = document.createElement('span');
                        key.className = 'key';
                        key.textContent = placeholder(token.key);

                        button.appendChild(label);
                        button.appendChild(key);
                        button.addEventListener('click', function () {
                            insert(token.key);
                        });

                        section.appendChild(button);
                    });

                    list.appendChild(section);
                });

                empty.hidden = shown !== 0;
            }

            function insert(key) {
                // PasteText drops the token at the cursor. Community Edition
                // exposes this to plugins even though the host page has no
                // equivalent.
                window.Asc.plugin.executeMethod('PasteText', [placeholder(key)]);
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
