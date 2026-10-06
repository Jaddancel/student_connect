import test from 'node:test';
import assert from 'node:assert/strict';
import '../../public/js/field-token-tools.js';

const tools = globalThis.FieldTokenTools;
const tokens = [
    { key: 'full_name' },
    { key: 'email' },
    { key: 'expenses', children: [{ key: 'expenses.amount' }, { key: 'expenses.total' }] },
];

test('recognizes exact assigned keys and repeating table columns', () => {
    const known = tools.knownKeys(tokens);
    for (const key of ['full_name', 'full_name#', 'expenses.amount', 'expenses.amount#']) {
        assert.equal(known[key], true);
    }
    assert.equal(known.Full_name, undefined);
    assert.equal(known[' full_name '], undefined);
    assert.equal(known.constructor, undefined);
});

test('ranks closest tokens deterministically and preserves repeating suffixes', () => {
    assert.equal(tools.suggestions('ful_name', tokens)[0], 'full_name');
    assert.equal(tools.suggestions('FULL_NAME', tokens)[0], 'full_name');
    assert.equal(tools.suggestions('expenses.amont#', tokens)[0], 'expenses.amount#');
    assert.equal(tools.suggestions('ful_name#', tokens)[0], 'full_name#');
    assert.equal(tools.suggestions('anything', tokens).length, 5);
    assert.deepEqual(tools.suggestions('anything', []), []);
});

test('distinguishes live current-document usage from other documents', () => {
    const documents = [
        { id: 'one', name: 'Open', keys: ['email', 'expenses.amount#'] },
        { id: 'two', name: 'Other', keys: ['full_name', 'expenses.total#'] },
        { id: 'three', name: 'Empty', keys: [] },
    ];
    assert.deepEqual(tools.usage(tokens[0], { full_name: 2 }, documents, 'one'), { here: 2, elsewhere: ['Other'] });
    assert.deepEqual(tools.usage(tokens[1], {}, documents, 'one'), { here: 0, elsewhere: [] });
    assert.deepEqual(tools.usage(tokens[2], { 'expenses.amount#': 1, 'expenses.total#': 2 }, documents, 'one'), { here: 3, elsewhere: ['Other'] });
});

test('limits placements of tokens that carry a limit', () => {
    const photos = { key: 'photos', label: 'Photos', limit: 1 };
    const pool = [...tokens, photos];

    assert.equal(tools.remaining(tokens[0], { full_name: 5 }), Infinity);
    assert.equal(tools.remaining(photos, {}), 1);
    assert.equal(tools.remaining(photos, { photos: 1 }), 0);
    assert.equal(tools.remaining(photos, { 'photos#': 1 }), 0);
    assert.equal(tools.remaining(photos, { photos: 2 }), 0);

    assert.deepEqual(tools.overLimit(pool, { photos: 1, full_name: 3 }), []);
    assert.deepEqual(tools.overLimit(pool, { photos: 1, 'photos#': 1 }), [photos]);

    assert.equal(tools.placeableSuggestions('photo', pool, {})[0], 'photos');
    assert.ok(!tools.placeableSuggestions('photo', pool, { photos: 1 }).includes('photos'));
    assert.ok(!tools.placeableSuggestions('photo#', pool, { photos: 1 }).includes('photos#'));
});

test('unknown scanning includes punctuation and blanks, but ignores incomplete tokens', () => {
    const text = '{{full_name}} {{full_name}} {{full-name}} {{}} {{ email }} {{constructor}} {{unfinished';
    const api = { GetVersion: () => 'future-version' };
    const document = {
        Document: { GetApi: () => api },
        GetAllParagraphs: () => [{ GetText: () => text }],
    };
    globalThis.Api = { GetDocument: () => document };
    globalThis.Asc = { scope: { fieldTokenRequest: { action: 'scan', known: tools.knownKeys(tokens) } } };
    const result = tools.editorCommand();
    assert.equal(result.current.full_name, 2);
    assert.equal(result.current.constructor, 1);
    assert.equal(result.unknown, 4);
    assert.match(result.error, /require ONLYOFFICE 9\.4\.0/);
});

test('counts table-chart column tokens from series names and categories once per chart', () => {
    const series = (name, category) => ({ getSeriesName: () => name, getCatName: () => category });
    const api = { GetVersion: () => 'future-version' };
    const document = {
        Document: { GetApi: () => api },
        GetAllParagraphs: () => [],
        GetAllCharts: () => [
            { Chart: { getAllSeries: () => [series('{{expenses.amount#}}', '{{expenses.item#}}'), series('{{expenses.total#}}', '{{expenses.item#}}')] } },
            { Chart: { getAllSeries: () => [series('Sales', '1')] } },
        ],
    };
    globalThis.Api = { GetDocument: () => document };
    globalThis.Asc = { scope: { fieldTokenRequest: { action: 'scan', known: tools.knownKeys(tokens) } } };
    const result = tools.editorCommand();
    assert.deepEqual({ ...result.current }, { 'expenses.amount#': 1, 'expenses.total#': 1, 'expenses.item#': 1 });
    assert.equal(result.unknown, 0);
});

test('replacement refuses stale tokens rather than overwriting unrelated text', () => {
    const api = { __fieldTokenTarget: { text: '{{ful_name}}', range: { GetText: () => 'changed' } } };
    globalThis.Api = { GetDocument: () => ({ Document: { GetApi: () => api } }) };
    globalThis.Asc = { scope: { fieldTokenRequest: { action: 'selectReplacement', text: '{{ful_name}}' } } };
    assert.match(tools.editorCommand().error, /token changed/);
});

test('underlines complete split-run tokens on wrapped pages without changing content or selection', () => {
    const flat = [];
    const runs = ['Hello {{ful', '', '_name}}'].map((text, index) => {
        if (index) flat.push('');
        flat.push(...text);
        return {
            Content: Array.from(text, char => ({
                IsText: () => char !== ' ',
                IsSpace: () => char === ' ',
                GetCharCode: () => char.codePointAt(0),
            })),
        };
    });
    let selected;
    const paragraph = {
        GetText: () => 'Hello {{ful_name}}',
        GetRange: (start, end) => ({
            GetText: () => flat.slice(start, end).join(''),
            Select: () => { selected = [start, end]; },
        }),
        Paragraph: {
            CheckRunContent: callback => runs.forEach(callback),
            Pages: [{}, {}],
            DrawSelectionOnPage: page => drawing.AddPageSelection(page, 10, 5, 12, 2),
        },
    };
    const originalAdd = () => assert.fail('Native selection must not be painted');
    const drawing = {
        AddPageSelection: originalAdd,
        AddPageSelection2: originalAdd,
        ConvertCoordsToCursor: (x, y) => ({ X: x * 10, Y: y * 10, Error: false }),
    };
    const parent = { appendChild: () => {} };
    const dom = {
        defaultView: { setInterval: () => 1, clearInterval: () => {} },
        getElementById: () => null,
        createDocumentFragment: () => ({ children: [], appendChild(node) { this.children.push(node); } }),
        createElement: () => ({
            style: {}, isConnected: true, setAttribute: () => {}, remove: () => {},
            replaceChildren(fragment) { this.children = fragment.children; },
        }),
    };
    let restored = 0;
    const state = { originalSelection: true };
    const api = {
        GetVersion: () => '9.4.0',
        __fieldTokenPointerReady: '1.2.3',
        WordControl: { m_oDrawingDocument: drawing, m_oOverlay: { HtmlElement: { ownerDocument: dom, parentElement: parent } } },
    };
    const logic = {
        GetApi: () => api,
        GetSelectionBounds: () => {},
        SaveDocumentState: () => state,
        LoadDocumentState: saved => { assert.equal(saved, state); restored++; },
    };
    globalThis.Api = { GetDocument: () => ({ Document: logic, GetAllParagraphs: () => [paragraph] }) };
    globalThis.Asc = { scope: { fieldTokenRequest: { action: 'scan', known: tools.knownKeys(tokens) } } };
    const result = tools.editorCommand();
    assert.equal(result.unknown, 1);
    assert.equal(result.error, '');
    assert.equal(restored, 1);
    assert.equal(drawing.AddPageSelection, originalAdd);
    assert.equal(drawing.AddPageSelection2, originalAdd);
    assert.equal(api.__fieldTokenOverlay.segments.length, 2);
    assert.equal(api.__fieldTokenOverlay.segments[0].token.text, '{{ful_name}}');
    assert.equal(api.__fieldTokenOverlay.layer.children[0].style.width, '120px');
    assert.equal(paragraph.GetText(), 'Hello {{ful_name}}');

    api.__fieldTokenPointerPoint = { x: 110, y: 60, time: Date.now() };
    globalThis.Asc.scope.fieldTokenRequest.action = 'target';
    assert.deepEqual(tools.editorCommand().target, { key: 'ful_name', text: '{{ful_name}}' });
    globalThis.Asc.scope.fieldTokenRequest = { action: 'selectReplacement', text: '{{ful_name}}' };
    assert.equal(tools.editorCommand().selected, true);
    assert.equal(flat.slice(...selected).join(''), '{{ful_name}}');
});
