(function (root) {
    'use strict';

    // Capture pointer events before the editor consumes legacy mouse events.
    // This static script also runs in the editor, outside the macro sandbox.
    if (root.Asc && root.Asc.editor && root.document && root.Asc.editor.__fieldTokenPointerReady !== '1.2.3') {
        root.document.addEventListener('pointerdown', function (event) {
            var api = root.Asc.editor;
            var host = api.__fieldTokenOverlay;
            if (!host) return;
            var rect = host.layer.getBoundingClientRect();
            api.__fieldTokenPointerPoint = event.button === 2
                ? { x: event.clientX - rect.left, y: event.clientY - rect.top, time: Date.now() } : null;
        }, true);
        root.Asc.editor.__fieldTokenPointerReady = '1.2.3';
    }

    function catalog(tokens) {
        var keys = [];
        tokens.forEach(function (token) {
            keys.push(token.key);
            (token.children || []).forEach(function (child) { keys.push(child.key + '#'); });
            // Extra literal keys a token owns, e.g. a report group's
            // `#group` / `/group` block markers.
            (token.known || []).forEach(function (key) { keys.push(key); });
        });
        return Array.from(new Set(keys));
    }

    function knownKeys(tokens) {
        var known = Object.create(null);
        catalog(tokens).forEach(function (key) {
            known[key] = true;
            known[key.endsWith('#') ? key.slice(0, -1) : key + '#'] = true;
        });
        return known;
    }

    function distance(a, b) {
        var row = Array.from({ length: b.length + 1 }, function (_, i) { return i; });
        for (var i = 1; i <= a.length; i++) {
            var previous = row[0];
            row[0] = i;
            for (var j = 1; j <= b.length; j++) {
                var above = row[j];
                row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1));
                previous = above;
            }
        }
        return row[b.length];
    }

    function suggestions(key, tokens) {
        return catalog(tokens).map(function (candidate) {
            if (key.endsWith('#') && !candidate.endsWith('#')) candidate += '#';
            return { key: candidate, score: distance(key.toLowerCase(), candidate.toLowerCase()) };
        }).sort(function (a, b) {
            return a.score - b.score || a.key.localeCompare(b.key);
        }).slice(0, 5).map(function (candidate) { return candidate.key; });
    }

    function usage(token, current, documents, documentId) {
        var keys = [token.key, token.key + '#'];
        (token.children || []).forEach(function (child) { keys.push(child.key, child.key + '#'); });
        return {
            here: keys.reduce(function (sum, key) { return sum + (current[key] || 0); }, 0),
            elsewhere: documents.filter(function (doc) {
                return String(doc.id) !== String(documentId) && doc.keys.some(function (key) { return keys.includes(key); });
            }).map(function (doc) { return doc.name; }),
        };
    }

    // Serialized by callCommand: all dependencies must come from Api or Asc.scope.
    function editorCommand() {
        var args = Asc.scope.fieldTokenRequest;
        var doc = Api.GetDocument();
        var logic = doc.Document;
        var api = logic.GetApi();
        var host = api.__fieldTokenOverlay;
        function tokenRanges(paragraph) {
            var text = '';
            var starts = [];
            var ends = [];
            var flat = 0;
            var first = true;
            // Range indices count run boundaries; plain-text offsets do not.
            paragraph.Paragraph.CheckRunContent(function (run) {
                if (!first) flat++;
                first = false;
                run.Content.forEach(function (character, index) {
                    var value = character.IsText && character.IsText() ? String.fromCodePoint(character.GetCharCode())
                        : character.IsSpace && character.IsSpace() ? ' ' : '\n';
                    for (var n = 0; n < value.length; n++) {
                        starts.push(flat + index);
                        ends.push(flat + index + 1);
                    }
                    text += value;
                });
                flat += run.Content.length;
            });
            var pattern = /\{\{([^{}\r\n]*)\}\}/g;
            var result = [];
            var match;
            while ((match = pattern.exec(text))) {
                var range = paragraph.GetRange(starts[match.index], ends[match.index + match[0].length - 1]);
                if (!range || range.GetText() !== match[0]) throw new Error('Unable to locate the complete field token.');
                result.push({ key: match[1], text: match[0], range: range, paragraph: paragraph });
            }
            return result;
        }
        try {
            if (args.action === 'target') {
                api.__fieldTokenTarget = null;
                if (host && api.__fieldTokenPointerPoint && Date.now() - api.__fieldTokenPointerPoint.time < 2000) {
                    var point = api.__fieldTokenPointerPoint;
                    api.__fieldTokenPointerPoint = null;
                    for (var hit = 0; hit < host.segments.length; hit++) {
                        var segment = host.segments[hit];
                        var drawingDocument = api.WordControl.m_oDrawingDocument;
                        var corner = drawingDocument.ConvertCoordsToCursor(segment.x, segment.y - segment.height, segment.page);
                        var opposite = drawingDocument.ConvertCoordsToCursor(segment.end, segment.y, segment.page);
                        if (!corner.Error && !opposite.Error && point.x >= Math.min(corner.X, opposite.X)
                            && point.x <= Math.max(corner.X, opposite.X) && point.y >= corner.Y && point.y <= opposite.Y) {
                            if (segment.token.range.GetText() !== segment.token.text) {
                                return { error: 'The token changed. Right-click it again.' };
                            }
                            api.__fieldTokenTarget = { range: segment.token.range, text: segment.token.text };
                            return { target: { key: segment.token.key, text: segment.token.text } };
                        }
                    }
                    return { target: null };
                }
                var paragraph = doc.GetCurrentParagraph();
                var selection = doc.GetRangeBySelect();
                if (!paragraph || !selection) return { target: null };
                var candidates = tokenRanges(paragraph);
                for (var c = 0; c < candidates.length; c++) {
                    var candidate = candidates[c];
                    if (Object.prototype.hasOwnProperty.call(args.known, candidate.key)) continue;
                    var targetRange = candidate.range;
                    if (targetRange && targetRange.GetStartPos() <= selection.GetStartPos()
                        && targetRange.GetEndPos() >= selection.GetEndPos()) {
                        api.__fieldTokenTarget = { range: targetRange, text: candidate.text };
                        return { target: { key: candidate.key, text: candidate.text } };
                    }
                }
                return { target: null };
            }
            if (args.action === 'selectReplacement') {
                var target = api.__fieldTokenTarget;
                if (!target || target.text !== args.text || target.range.GetText() !== args.text) {
                    return { error: 'The token changed while the menu was open. Right-click it again.' };
                }
                target.range.Select();
                api.__fieldTokenTarget = null;
                return { selected: true };
            }
            if (args.action === 'clear') {
                if (host) host.dispose();
                return { cleared: true };
            }

            var current = Object.create(null);
            var unknown = [];
            doc.GetAllParagraphs().forEach(function (p) {
                var pattern = /\{\{([^{}\r\n]*)\}\}/g;
                var text = p.GetText();
                var match;
                while ((match = pattern.exec(text))) {
                    current[match[1]] = (current[match[1]] || 0) + 1;
                    if (!Object.prototype.hasOwnProperty.call(args.known, match[1])) unknown.push({ paragraph: p, text: match[0] });
                }
            });

            var wc = api.WordControl;
            if (api.GetVersion() !== '9.4.0' || !wc || !wc.m_oOverlay || !wc.m_oOverlay.HtmlElement
                || typeof logic.GetSelectionBounds !== 'function'
                || typeof wc.m_oDrawingDocument.ConvertCoordsToCursor !== 'function'
                || typeof wc.m_oDrawingDocument.AddPageSelection !== 'function') {
                if (host) host.dispose();
                return { current: current, unknown: unknown.length, error: 'Yellow token underlines require ONLYOFFICE 9.4.0. Usage and suggestions remain available.' };
            }

            var canvas = wc.m_oOverlay.HtmlElement;
            var dom = canvas.ownerDocument;
            var view = dom.defaultView;
            var installedScript = dom.getElementById('field-token-pointer-script');
            if (api.__fieldTokenPointerReady !== '1.2.3' && (!installedScript || installedScript.getAttribute('data-version') !== '1.2.3')) {
                if (installedScript) installedScript.remove();
                var pointerScript = dom.createElement('script');
                pointerScript.id = 'field-token-pointer-script';
                pointerScript.setAttribute('data-version', '1.2.3');
                pointerScript.src = args.adapterUrl + '?v=1.2.3';
                dom.head.appendChild(pointerScript);
            }
            if (host && host.revision !== '1.2.2') {
                host.dispose();
                host = null;
            }
            if (!host) {
                var layer = dom.createElement('div');
                layer.id = 'field-token-warning-overlay';
                layer.setAttribute('aria-hidden', 'true');
                layer.style.cssText = 'position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:3';
                canvas.parentElement.appendChild(layer);
                host = {
                    revision: '1.2.2', layer: layer, segments: [], expires: 0,
                    dispose: function () {
                        view.clearInterval(this.timer);
                        this.layer.remove();
                        delete api.__fieldTokenOverlay;
                    },
                    paint: function () {
                        if (Date.now() > this.expires || !this.layer.isConnected) {
                            this.dispose();
                            return;
                        }
                        var drawing = wc.m_oDrawingDocument;
                        var fragment = dom.createDocumentFragment();
                        this.segments.forEach(function (segment) {
                            var from = drawing.ConvertCoordsToCursor(segment.x, segment.y, segment.page);
                            var to = drawing.ConvertCoordsToCursor(segment.end, segment.y, segment.page);
                            if (from.Error || to.Error) return;
                            var line = dom.createElement('div');
                            line.style.cssText = 'position:absolute;height:3px;border-bottom:2px solid #eab308';
                            line.style.left = Math.min(from.X, to.X) + 'px';
                            line.style.top = from.Y + 'px';
                            line.style.width = Math.max(1, Math.abs(to.X - from.X)) + 'px';
                            fragment.appendChild(line);
                        });
                        this.layer.replaceChildren(fragment);
                    },
                };
                api.__fieldTokenOverlay = host;
                host.timer = view.setInterval(function () { host.paint(); }, 100);
            }

            // Capture native selection rectangles, including wrapped lines and
            // split runs, without painting a selection or changing the document.
            var state = logic.SaveDocumentState();
            var segments = [];
            var drawing = wc.m_oDrawingDocument;
            var addSelection = drawing.AddPageSelection;
            var addSelection2 = drawing.AddPageSelection2;
            var measuringToken;
            try {
                drawing.AddPageSelection = drawing.AddPageSelection2 = function (page, x, y, width, height) {
                    if (width > 0 && height > 0) segments.push({ page: page, x: x, end: x + width, y: y + height, height: height, token: measuringToken });
                };
                var paragraphs = Array.from(new Set(unknown.map(function (item) { return item.paragraph; })));
                paragraphs.forEach(function (p) {
                    tokenRanges(p).forEach(function (item) {
                        if (Object.prototype.hasOwnProperty.call(args.known, item.key)) return;
                        measuringToken = item;
                        item.range.Select(false);
                        for (var page = 0; page < p.Paragraph.Pages.length; page++) {
                            p.Paragraph.DrawSelectionOnPage(page);
                        }
                    });
                });
            } finally {
                drawing.AddPageSelection = addSelection;
                drawing.AddPageSelection2 = addSelection2;
                logic.LoadDocumentState(state);
            }
            host.segments = segments;
            host.expires = Date.now() + 5000;
            host.paint();
            return {
                current: current,
                unknown: unknown.length,
                error: api.__fieldTokenPointerReady === '1.2.3' ? ''
                    : 'The editor pointer adapter is loading or blocked. Right-click suggestions may be unavailable.',
            };
        } catch (error) {
            if (host) host.dispose();
            return { error: 'Token checking failed: ' + error.message };
        }
    }

    root.FieldTokenTools = { catalog: catalog, knownKeys: knownKeys, distance: distance, suggestions: suggestions, usage: usage, editorCommand: editorCommand };
})(globalThis);
