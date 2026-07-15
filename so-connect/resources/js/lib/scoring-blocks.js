/**
 * Custom Blockly blocks for the scoring trigger editor, plus the compiler
 * that turns a workspace into the server's trigger AST (see
 * App\Services\Scoring\TriggerValidator for the grammar).
 *
 * The block set is deliberately Scratch-simple:
 *   scoring_rule  — the single top-level block: WHEN <source> [IF <cond>] THEN <adder>
 *   cond_compare  — <variable> <op> <value>            (Boolean)
 *   cond_not_empty— <variable> is not empty            (Boolean)
 *   built-in logic_operation / logic_negate            (AND / OR / NOT)
 *   add_const     — add N instance(s)                  (Adder)
 *   add_floor_div — add 1 instance per N of <variable> (Adder)
 *   add_count_list— add 1 instance per row of <variable> (Adder)
 *
 * Variables are namespaced dropdown options: field:<key> (form fields),
 * plan:<key> (event-plan columns), universal:<key> (profile fields).
 */

const COMPARE_OPS = [
    ['=', '='],
    ['≠', '!='],
    ['>', '>'],
    ['≥', '>='],
    ['<', '<'],
    ['≤', '<='],
    ['contains', 'contains'],
];

function sourceOptions(variables) {
    const options = [['an event plan is approved', 'event_plan']];
    (variables.forms || []).forEach((form) => {
        options.push([`a “${form.name}” submission is approved`, `form:${form.id}`]);
    });
    return options;
}

function variableOptions(variables) {
    const options = [];
    (variables.plan || []).forEach((v) => options.push([`plan · ${v.label}`, `plan:${v.key}`]));
    (variables.forms || []).forEach((form) => {
        form.fields.forEach((f) => options.push([`${form.name} · ${f.label}`, `field:${f.key}`]));
    });
    (variables.universal || []).forEach((v) => options.push([`universal · ${v.label}`, `universal:${v.key}`]));
    return options.length ? options : [['(no variables available)', 'field:none']];
}

export function defineScoringBlocks(Blockly, variables) {
    const sources = sourceOptions(variables);
    const vars = variableOptions(variables);

    Blockly.Blocks.scoring_rule = {
        init() {
            this.appendDummyInput()
                .appendField('when')
                .appendField(new Blockly.FieldDropdown(sources), 'SOURCE');
            this.appendValueInput('IF').setCheck('Boolean').appendField('if');
            this.appendValueInput('THEN').setCheck('Adder').appendField('then');
            this.setColour(35);
            this.setDeletable(false);
            this.setTooltip('The rule: when a record of this source is approved, and the condition holds, add instances to this criterion.');
        },
    };

    Blockly.Blocks.cond_compare = {
        init() {
            this.appendDummyInput()
                .appendField(new Blockly.FieldDropdown(vars), 'VAR')
                .appendField(new Blockly.FieldDropdown(COMPARE_OPS), 'OP')
                .appendField(new Blockly.FieldTextInput(''), 'VALUE');
            this.setOutput(true, 'Boolean');
            this.setColour(210);
            this.setTooltip('Compare a variable against a value (numbers compare numerically; yes/no and true/false are interchangeable). For a checkbox, use 1 for checked and 0 for unchecked.');
        },
    };

    Blockly.Blocks.cond_not_empty = {
        init() {
            this.appendDummyInput()
                .appendField(new Blockly.FieldDropdown(vars), 'VAR')
                .appendField('is not empty');
            this.setOutput(true, 'Boolean');
            this.setColour(210);
        },
    };

    Blockly.Blocks.add_const = {
        init() {
            this.appendDummyInput()
                .appendField('add')
                .appendField(new Blockly.FieldNumber(1, 1, 1000, 1), 'VALUE')
                .appendField('instance(s)');
            this.setOutput(true, 'Adder');
            this.setColour(120);
        },
    };

    Blockly.Blocks.add_floor_div = {
        init() {
            this.appendDummyInput()
                .appendField('add 1 instance per')
                .appendField(new Blockly.FieldNumber(500, 0.01), 'DIVISOR')
                .appendField('of')
                .appendField(new Blockly.FieldDropdown(vars), 'VAR');
            this.setOutput(true, 'Adder');
            this.setColour(120);
            this.setTooltip('e.g. 1 instance per ₱500 of cash on hand (rounded down).');
        },
    };

    Blockly.Blocks.add_count_list = {
        init() {
            this.appendDummyInput()
                .appendField('add 1 instance per row of')
                .appendField(new Blockly.FieldDropdown(vars), 'VAR');
            this.setOutput(true, 'Adder');
            this.setColour(120);
            this.setTooltip('Counts the non-empty entries of a list field (e.g. in-kind donation rows).');
        },
    };
}

export const TOOLBOX = {
    kind: 'flyoutToolbox',
    contents: [
        { kind: 'block', type: 'cond_compare' },
        { kind: 'block', type: 'cond_not_empty' },
        { kind: 'block', type: 'logic_operation' },
        { kind: 'block', type: 'logic_negate' },
        { kind: 'block', type: 'add_const' },
        { kind: 'block', type: 'add_floor_div' },
        { kind: 'block', type: 'add_count_list' },
    ],
};

/** The starter workspace: a lone scoring_rule block. */
export const STARTER_WORKSPACE = {
    blocks: { languageVersion: 0, blocks: [{ type: 'scoring_rule', x: 24, y: 24 }] },
};

function compileCondition(block) {
    if (!block) return null;

    switch (block.type) {
        case 'logic_operation': {
            const op = block.getFieldValue('OP') === 'AND' ? 'and' : 'or';
            const a = compileCondition(block.getInputTargetBlock('A'));
            const b = compileCondition(block.getInputTargetBlock('B'));
            const children = [a, b].filter(Boolean);
            if (!children.length) return null;
            return children.length === 1 ? children[0] : { op, children };
        }
        case 'logic_negate': {
            const child = compileCondition(block.getInputTargetBlock('BOOL'));
            return child ? { op: 'not', children: [child] } : null;
        }
        case 'cond_compare':
            return {
                op: block.getFieldValue('OP'),
                left: { var: block.getFieldValue('VAR') },
                right: { value: block.getFieldValue('VALUE') },
            };
        case 'cond_not_empty':
            return {
                op: 'not_empty',
                left: { var: block.getFieldValue('VAR') },
            };
        default:
            return null;
    }
}

function compileAdder(block) {
    if (!block) return null;

    switch (block.type) {
        case 'add_const':
            return { kind: 'const', value: Number(block.getFieldValue('VALUE')) || 1 };
        case 'add_floor_div':
            return {
                kind: 'floor_div',
                var: block.getFieldValue('VAR'),
                divisor: Number(block.getFieldValue('DIVISOR')) || 1,
            };
        case 'add_count_list':
            return { kind: 'count_list', var: block.getFieldValue('VAR') };
        default:
            return null;
    }
}

/**
 * Compile the workspace's scoring_rule block into the trigger AST.
 * Returns { trigger } or { error } with a human-readable message.
 */
export function compileWorkspace(workspace) {
    const rule = workspace.getTopBlocks(false).find((b) => b.type === 'scoring_rule');
    if (!rule) {
        return { error: 'The rule block is missing — reset the workspace.' };
    }

    const source = rule.getFieldValue('SOURCE') || '';
    const when = source.startsWith('form:')
        ? { source: 'form_submission', form_id: Number(source.slice(5)), status: 'approved' }
        : { source: 'event_plan', status: 'approved' };

    const add = compileAdder(rule.getInputTargetBlock('THEN'));
    if (!add) {
        return { error: 'Attach an “add … instance(s)” block to the “then” socket.' };
    }

    return {
        trigger: {
            when,
            if: compileCondition(rule.getInputTargetBlock('IF')),
            then: { add },
        },
    };
}
