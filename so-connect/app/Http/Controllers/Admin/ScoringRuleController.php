<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\ScoringCriterion;
use App\Models\ScoringRule;
use App\Services\ActionLogger;
use App\Services\Scoring\ScoringCatalog;
use App\Services\Scoring\TriggerValidator;
use App\Support\UniversalField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin editor for the scoring system's configuration: the criteria catalog
 * (custom criteria can be added/edited/removed; the seeded system criteria
 * stay fixed for score parity) and each criterion's trigger — authored in a
 * Scratch-like Blockly workspace and compiled to the AST
 * {@see \App\Services\Scoring\ScoringRuleEngine} evaluates.
 *
 * Every mutation lands in the administrator action log under scoring_config.
 */
class ScoringRuleController extends Controller
{
    /** Event-plan columns exposed to rules as `plan:<key>` variables. */
    private const PLAN_VARIABLES = [
        ['key' => 'activity_types', 'label' => 'Activity types (list)', 'type' => 'list'],
        ['key' => 'seminar_level', 'label' => 'Seminar level', 'type' => 'text'],
        ['key' => 'related_to_organization', 'label' => 'Related to organization', 'type' => 'boolean'],
        ['key' => 'extension_services', 'label' => 'Extension services', 'type' => 'boolean'],
        ['key' => 'sponsor', 'label' => 'Sponsor', 'type' => 'text'],
        ['key' => 'cosponsor_count', 'label' => 'Co-sponsor count', 'type' => 'number'],
        ['key' => 'area_scope', 'label' => 'Area scope', 'type' => 'text'],
        ['key' => 'members_attended', 'label' => 'Members attended', 'type' => 'number'],
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['key' => 'target_date', 'label' => 'Target date', 'type' => 'text'],
    ];

    public function index()
    {
        $criteria = ScoringCriterion::query()
            ->with('rule')
            ->orderBy('sort_order')
            ->orderBy('scoring_criterion_id')
            ->get()
            ->groupBy('category_key');

        return view('pages.admin.scoring.rules.index', [
            'title' => 'Scoring Rules',
            'criteriaByCategory' => $criteria,
            'categories' => ScoringCatalog::categories(),
        ]);
    }

    public function storeCriterion(Request $request)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'category_key' => ['required', 'string', Rule::in(array_keys(ScoringCatalog::categories()))],
            'weight' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $base = 'custom_'.Str::slug($validated['label'], '_');
        $key = mb_substr($base, 0, 56);
        for ($i = 2; ScoringCriterion::query()->where('key', $key)->exists(); $i++) {
            $key = mb_substr($base, 0, 56).'_'.$i;
        }

        $criterion = ScoringCriterion::query()->create([
            'key' => $key,
            'category_key' => $validated['category_key'],
            'label' => $validated['label'],
            'weight' => (int) $validated['weight'],
            'sort_order' => 1000,
            'is_system' => false,
            'is_active' => true,
        ]);
        ScoringCatalog::flush();

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING_CONFIG,
            'criterion_created',
            'Added scoring criterion "'.$criterion->label.'"',
            ['key' => $criterion->key, 'category' => $criterion->category_key, 'weight' => (int) $criterion->weight],
            $criterion,
        );

        return redirect()->route('admin.scoring.rules.index')
            ->with('toast', 'Saved!');
    }

    public function updateCriterion(Request $request, ScoringCriterion $criterion)
    {
        abort_if($criterion->is_system, 403, 'System criteria are fixed; only their triggers can be configured.');

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'category_key' => ['required', 'string', Rule::in(array_keys(ScoringCatalog::categories()))],
            'weight' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $criterion->update($validated);
        ScoringCatalog::flush();

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING_CONFIG,
            'criterion_updated',
            'Updated scoring criterion "'.$criterion->label.'"',
            ['key' => $criterion->key, 'category' => $criterion->category_key, 'weight' => (int) $criterion->weight],
            $criterion,
        );

        return redirect()->route('admin.scoring.rules.index')
            ->with('toast', 'Saved!');
    }

    public function destroyCriterion(ScoringCriterion $criterion)
    {
        abort_if($criterion->is_system, 403, 'System criteria cannot be deleted.');

        $criterion->delete();
        ScoringCatalog::flush();

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING_CONFIG,
            'criterion_deleted',
            'Deleted scoring criterion "'.$criterion->label.'"',
            ['key' => $criterion->key],
        );

        return redirect()->route('admin.scoring.rules.index')
            ->with('toast', 'Saved!');
    }

    public function editRule(ScoringCriterion $criterion)
    {
        return view('pages.admin.scoring.rules.editor', [
            'title' => 'Trigger — '.$criterion->label,
            'criterion' => $criterion,
            'rule' => $criterion->rule,
            'trigger' => $criterion->rule?->trigger,
            'categories' => ScoringCatalog::categories(),
            'variables' => $this->variablesPayload(),
        ]);
    }

    public function updateRule(Request $request, ScoringCriterion $criterion, TriggerValidator $validator)
    {
        $validated = $request->validate([
            'workspace' => ['nullable', 'array'],
            'trigger' => ['required', 'array'],
            'enabled' => ['boolean'],
        ]);

        $validator->validate($validated['trigger']);

        $rule = ScoringRule::query()->updateOrCreate(
            ['criterion_id' => (int) $criterion->getKey()],
            [
                'workspace' => $validated['workspace'] ?? null,
                'trigger' => $validated['trigger'],
                'enabled' => (bool) ($validated['enabled'] ?? true),
                'updated_by' => $request->user()?->getKey(),
            ],
        );

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING_CONFIG,
            'rule_updated',
            'Configured trigger for "'.$criterion->label.'"',
            ['criterion' => $criterion->key, 'enabled' => (bool) $rule->enabled, 'trigger' => $validated['trigger']],
            $rule,
        );

        session()->flash('toast', 'Saved!');

        return response()->json([
            'message' => 'Saved!',
            'redirect' => route('admin.scoring.rules.index'),
        ]);
    }

    public function toggleRule(Request $request, ScoringCriterion $criterion)
    {
        $rule = $criterion->rule;
        abort_unless($rule, 404);

        $rule->update(['enabled' => ! $rule->enabled, 'updated_by' => $request->user()?->getKey()]);

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING_CONFIG,
            $rule->enabled ? 'rule_enabled' : 'rule_disabled',
            ($rule->enabled ? 'Enabled' : 'Disabled').' trigger for "'.$criterion->label.'"',
            ['criterion' => $criterion->key],
            $rule,
        );

        return redirect()->route('admin.scoring.rules.index')
            ->with('toast', 'Saved!');
    }

    public function destroyRule(ScoringCriterion $criterion)
    {
        $criterion->rule?->delete();

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING_CONFIG,
            'rule_deleted',
            'Removed trigger for "'.$criterion->label.'"',
            ['criterion' => $criterion->key],
        );

        return redirect()->route('admin.scoring.rules.index')
            ->with('toast', 'Saved!');
    }

    /**
     * Every variable the block editor can offer: each form page's fields
     * (`field:<key>`), the universal fields (`universal:<key>`), and the
     * event-plan columns (`plan:<key>`).
     *
     * @return array<string,mixed>
     */
    private function variablesPayload(): array
    {
        $forms = Form::query()
            ->with('fields')
            ->orderBy('name')
            ->get()
            ->map(fn (Form $form) => [
                'id' => (int) $form->getKey(),
                'name' => $form->name,
                'fields' => $form->fields
                    ->filter(fn ($f) => ! \App\Forms\FieldType::isPresentational($f->field_type))
                    ->map(fn ($f) => [
                        'key' => $f->field_key,
                        'label' => $f->field_label,
                        'type' => $f->field_type,
                        // Selectable states for the value picker: a dynamic
                        // source resolves its entries (admin/unscoped — scoring
                        // spans every organization); a static field uses its
                        // hand-typed options.
                        'options' => self::fieldOptions($f),
                    ])->values()->all(),
            ])->values()->all();

        $universal = collect(UniversalField::catalog())
            ->map(fn (array $meta, string $key) => ['key' => $key, 'label' => $meta['label'], 'type' => $meta['type']])
            ->values()
            ->all();

        return [
            'forms' => $forms,
            'universal' => $universal,
            'plan' => self::PLAN_VARIABLES,
        ];
    }

    /**
     * The value-picker options for a form field in the tally editor. A field
     * drawing from a dynamic OptionSource resolves its entries unscoped (scoring
     * runs across every organization); a static optioned field uses its own
     * options. Sourced lists are capped so a large source can't bloat the page.
     *
     * @return array<int,array{value:string,label:string}>
     */
    private static function fieldOptions(\App\Models\Form\FormDescription $field): array
    {
        $options = (array) ($field->field_options ?? []);

        $source = \App\Forms\OptionSource::forField($options);
        if ($source !== null) {
            return array_slice(
                \App\Forms\OptionSource::options($source, user: null, scoped: false),
                0,
                500,
            );
        }

        return \App\Forms\FieldType::isOptioned($field->field_type)
            ? \App\Forms\FieldType::optionPairs($options)
            : [];
    }
}
