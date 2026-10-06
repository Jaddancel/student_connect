<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use App\Services\FormPrintTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Element\Section;

/**
 * Recreates the two formerly hardcoded admin reports — "Registered
 * Organizations" and "Organization Officers" — plus the "Financial Report"
 * as report templates (token definition + printed .docx). Idempotent:
 * existing templates with the same name are left untouched.
 */
class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::definitions() as $name => $meta) {
            if (ReportTemplate::query()->where('name', $name)->exists()) {
                continue;
            }

            $report = ReportTemplate::query()->create([
                'name' => $name,
                'description' => $meta['description'],
                'icon' => $meta['icon'],
                'audience' => ReportTemplate::AUDIENCE_ADMINS,
                'definition' => $meta['definition'],
                'is_active' => true,
            ]);

            app(FormPrintTemplateService::class)->createReportSlot(
                $report,
                $name,
                self::document($meta['layout']),
                0,
                null,
            );
        }
    }

    /**
     * @return array<string, array{description:string, icon:string, definition:array, layout:callable(Section):void}>
     */
    public static function definitions(): array
    {
        $text = fn (string $fallback = '') => ['type' => 'text', 'pattern' => null, 'fallback' => $fallback];
        $money = ['type' => 'number', 'pattern' => '2', 'fallback' => '0.00'];

        return [
            'Registered Organizations' => [
                'description' => 'Every registered organization with its type and officer count.',
                'icon' => 'tables',
                'definition' => [
                    'parameters' => [],
                    'tokens' => [
                        [
                            'id' => 'total_orgs', 'kind' => 'value', 'name' => 'total_orgs', 'mode' => 'aggregate',
                            'from' => 'organizations', 'fn' => 'count', 'column' => null, 'where' => [], 'format' => $text('0'),
                        ],
                        [
                            'id' => 'orgs', 'kind' => 'group', 'name' => 'orgs', 'entity' => 'organizations',
                            'where' => [], 'order' => [['column' => 'organization_id', 'dir' => 'asc']], 'limit' => null,
                            'children' => [
                                ['id' => 'org_id', 'kind' => 'value', 'name' => 'org_id', 'mode' => 'field', 'path' => ['organization_id'], 'format' => $text()],
                                ['id' => 'org_name', 'kind' => 'value', 'name' => 'org_name', 'mode' => 'field', 'path' => ['detail', 'name'], 'format' => $text('Unnamed Organization')],
                                ['id' => 'initials', 'kind' => 'value', 'name' => 'initials', 'mode' => 'field', 'path' => ['detail', 'initials'], 'format' => $text('—')],
                                ['id' => 'type', 'kind' => 'value', 'name' => 'type', 'mode' => 'field', 'path' => ['organization_type'], 'format' => $text('—')],
                                [
                                    'id' => 'officer_count', 'kind' => 'value', 'name' => 'officer_count', 'mode' => 'aggregate',
                                    'fn' => 'count', 'relation' => 'organization_officers', 'column' => null,
                                    'where' => [['column' => 'role', 'op' => 'in', 'value' => ['officer', 'president']]],
                                    'format' => $text('0'),
                                ],
                            ],
                        ],
                    ],
                ],
                'layout' => function (Section $section) {
                    self::header($section, 'Registered Organizations');
                    $table = self::table($section);
                    self::row($table, ['#', 'Name', 'Initials', 'Type', 'Officers'], true);
                    self::row($table, ['{{orgs.org_id#}}', '{{orgs.org_name#}}', '{{orgs.initials#}}', '{{orgs.type#}}', '{{orgs.officer_count#}}']);
                    $section->addTextBreak();
                    $section->addText('Total registered organizations: {{total_orgs}}', ['size' => 9, 'color' => '6B7280']);
                },
            ],

            'Organization Officers' => [
                'description' => 'The officers of each organization. Optionally limit it to one organization.',
                'icon' => 'user-profile',
                'definition' => [
                    'parameters' => [[
                        'name' => 'organization', 'label' => 'Organization', 'type' => 'entity',
                        'entity' => 'organizations', 'display' => ['detail', 'name'], 'required' => false,
                    ]],
                    'tokens' => [[
                        'id' => 'orgs', 'kind' => 'group', 'name' => 'orgs', 'entity' => 'organizations',
                        'where' => [['column' => 'organization_id', 'op' => '=', 'param' => 'organization']],
                        'order' => [['column' => 'organization_id', 'dir' => 'asc']], 'limit' => null,
                        'children' => [
                            ['id' => 'o_name', 'kind' => 'value', 'name' => 'org_name', 'mode' => 'field', 'path' => ['detail', 'name'], 'format' => $text('Unnamed Organization')],
                            ['id' => 'o_initials', 'kind' => 'value', 'name' => 'initials', 'mode' => 'field', 'path' => ['detail', 'initials'], 'format' => $text('—')],
                            [
                                'id' => 'officers', 'kind' => 'group', 'name' => 'officers', 'relation' => 'organization_officers',
                                'where' => [['column' => 'role', 'op' => 'in', 'value' => ['officer', 'president']]],
                                'order' => [['column' => 'role', 'dir' => 'desc']], 'limit' => null,
                                'children' => [
                                    ['id' => 'first_name', 'kind' => 'value', 'name' => 'first_name', 'mode' => 'field', 'path' => ['user', 'profile', 'first_name'], 'format' => $text()],
                                    ['id' => 'middle_name', 'kind' => 'value', 'name' => 'middle_name', 'mode' => 'field', 'path' => ['user', 'profile', 'middle_name'], 'format' => $text()],
                                    ['id' => 'last_name', 'kind' => 'value', 'name' => 'last_name', 'mode' => 'field', 'path' => ['user', 'profile', 'last_name'], 'format' => $text()],
                                    ['id' => 'email', 'kind' => 'value', 'name' => 'email', 'mode' => 'field', 'path' => ['user', 'user_email'], 'format' => $text('—')],
                                    ['id' => 'role', 'kind' => 'value', 'name' => 'role', 'mode' => 'field', 'path' => ['role'], 'format' => ['type' => 'title', 'pattern' => null, 'fallback' => '—']],
                                    ['id' => 'position', 'kind' => 'value', 'name' => 'position', 'mode' => 'field', 'path' => ['position'], 'format' => $text('—')],
                                    ['id' => 'member_since', 'kind' => 'value', 'name' => 'member_since', 'mode' => 'field', 'path' => ['member_since'], 'format' => ['type' => 'date', 'pattern' => 'Y-m-d', 'fallback' => '—']],
                                ],
                            ],
                        ],
                    ]],
                ],
                'layout' => function (Section $section) {
                    self::header($section, 'Organization Officers');
                    $section->addText('{{#orgs}}');
                    $section->addText('{{orgs.org_name}} ({{orgs.initials}})', ['bold' => true, 'size' => 13, 'color' => '111827']);
                    $table = self::table($section);
                    self::row($table, ['Name', 'Email', 'Role', 'Position', 'Member Since'], true);
                    self::row($table, [
                        '{{orgs.officers.first_name#}} {{orgs.officers.middle_name#}} {{orgs.officers.last_name#}}',
                        '{{orgs.officers.email#}}',
                        '{{orgs.officers.role#}}',
                        '{{orgs.officers.position#}}',
                        '{{orgs.officers.member_since#}}',
                    ]);
                    $section->addTextBreak();
                    $section->addText('{{/orgs}}');
                },
            ],

            'Financial Report' => [
                'description' => 'The selected organization\'s funds (latest approved Organization Fund Form) against the expenses of its After Event Reports this semester.',
                'icon' => 'tables',
                'definition' => [
                    'parameters' => [[
                        'name' => 'organization', 'label' => 'Organization', 'type' => 'entity',
                        'entity' => 'organizations', 'display' => ['detail', 'name'], 'required' => true,
                        'context' => 'active_organization',
                    ]],
                    'tokens' => [
                        [
                            'id' => 'funds', 'kind' => 'group', 'name' => 'funds', 'entity' => 'organization_funds',
                            'where' => [['column' => 'organization_id', 'op' => '=', 'param' => 'organization']],
                            'order' => [['column' => 'row_id', 'dir' => 'asc']], 'limit' => null,
                            'children' => [
                                ['id' => 'fund_source', 'kind' => 'value', 'name' => 'fund_source', 'mode' => 'field', 'path' => ['fund_source'], 'format' => $text('—')],
                                ['id' => 'fund_amount', 'kind' => 'value', 'name' => 'amount', 'mode' => 'field', 'path' => ['amount'], 'format' => $money],
                            ],
                        ],
                        [
                            'id' => 'total_funds', 'kind' => 'value', 'name' => 'total_funds', 'mode' => 'aggregate',
                            'from' => 'organization_funds', 'fn' => 'sum', 'column' => 'amount',
                            'where' => [['column' => 'organization_id', 'op' => '=', 'param' => 'organization']],
                            'format' => $money,
                        ],
                        [
                            'id' => 'expenses', 'kind' => 'group', 'name' => 'expenses', 'entity' => 'event_expenses',
                            'where' => [['column' => 'organization_id', 'op' => '=', 'param' => 'organization']],
                            'order' => [['column' => 'event_start', 'dir' => 'asc'], ['column' => 'row_id', 'dir' => 'asc']], 'limit' => null,
                            'children' => [
                                ['id' => 'activity', 'kind' => 'value', 'name' => 'activity', 'mode' => 'field', 'path' => ['event_name'], 'format' => $text('—')],
                                ['id' => 'activity_date', 'kind' => 'value', 'name' => 'activity_date', 'mode' => 'field', 'path' => ['event_start'], 'format' => ['type' => 'date', 'pattern' => 'M j, Y', 'fallback' => '—']],
                                ['id' => 'item', 'kind' => 'value', 'name' => 'item', 'mode' => 'field', 'path' => ['item'], 'format' => $text('—')],
                                ['id' => 'price_per_unit', 'kind' => 'value', 'name' => 'price_per_unit', 'mode' => 'field', 'path' => ['price_per_unit'], 'format' => $money],
                                ['id' => 'quantity', 'kind' => 'value', 'name' => 'quantity', 'mode' => 'field', 'path' => ['quantity'], 'format' => ['type' => 'number', 'pattern' => null, 'fallback' => '0']],
                                ['id' => 'total_price', 'kind' => 'value', 'name' => 'total_price', 'mode' => 'field', 'path' => ['total_row'], 'format' => $money],
                            ],
                        ],
                        [
                            'id' => 'total_expenses', 'kind' => 'value', 'name' => 'total_expenses', 'mode' => 'aggregate',
                            'from' => 'event_expenses', 'fn' => 'sum', 'column' => 'total_row',
                            'where' => [['column' => 'organization_id', 'op' => '=', 'param' => 'organization']],
                            'format' => $money,
                        ],
                        [
                            'id' => 'balance', 'kind' => 'value', 'name' => 'balance', 'mode' => 'compute',
                            'expression' => 'total_funds - total_expenses', 'format' => $money,
                        ],
                    ],
                ],
                'layout' => function (Section $section) {
                    $section->addText('Financial Report', ['bold' => true, 'size' => 18]);
                    $section->addText('{{profile.org_name}}', ['bold' => true, 'size' => 12]);
                    $section->addText('{{system.current_semester}}, S.Y. {{system.current_school_year}}', ['size' => 10, 'color' => '6B7280']);
                    $section->addTextBreak();

                    $section->addText('Fund Table', ['bold' => true, 'size' => 12]);
                    $table = self::table($section);
                    self::row($table, ['Fund Source', 'Fund Amount (PHP)'], true);
                    self::row($table, ['{{funds.fund_source#}}', '{{funds.amount#}}']);
                    $section->addText('Total Funds: {{total_funds}}', ['bold' => true]);
                    $section->addTextBreak();

                    $section->addText('Expenses Summary', ['bold' => true, 'size' => 12]);
                    $table = self::table($section);
                    self::row($table, ['Activity Title', 'Date', 'Item', 'Amount per Unit', 'Quantity', 'Total Price'], true);
                    self::row($table, [
                        '{{expenses.activity#}}', '{{expenses.activity_date#}}', '{{expenses.item#}}',
                        '{{expenses.price_per_unit#}}', '{{expenses.quantity#}}', '{{expenses.total_price#}}',
                    ]);
                    $section->addText('Total Expenses: {{total_expenses}}', ['bold' => true]);
                    $section->addText('Sum (Total Funds − Total Expenses): {{balance}}', ['bold' => true]);
                    $section->addTextBreak(2);

                    // Auditor signs for the organization; the adviser's name
                    // and signature stay blank to be filled in by hand.
                    $signatures = $section->addTable(['borderSize' => 0, 'cellMargin' => 80]);
                    $signatures->addRow();
                    $officer = $signatures->addCell(4500);
                    $officer->addText('Prepared by:', ['size' => 9, 'color' => '6B7280']);
                    $officer->addText('{{profile.org_auditor_signature}}');
                    $officer->addText('{{profile.org_auditor}}', ['bold' => true]);
                    $officer->addText('Auditor', ['size' => 9]);
                    $adviser = $signatures->addCell(4500);
                    $adviser->addText('Noted by:', ['size' => 9, 'color' => '6B7280']);
                    $adviser->addTextBreak(2);
                    $adviser->addText('______________________________');
                    $adviser->addText('Adviser', ['size' => 9]);
                },
            ],
        ];
    }

    private static function header(Section $section, string $title): void
    {
        $section->addText($title, ['bold' => true, 'size' => 18]);
        $section->addText('Generated {{system.current_datetime}}', ['size' => 9, 'color' => '6B7280']);
        $section->addTextBreak();
    }

    private static function table(Section $section): \PhpOffice\PhpWord\Element\Table
    {
        return $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'D1D5DB',
            'cellMargin' => 80,
            'width' => 100 * 50,
            'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT,
        ]);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private static function row(\PhpOffice\PhpWord\Element\Table $table, array $cells, bool $heading = false): void
    {
        $table->addRow();
        foreach ($cells as $cell) {
            $table->addCell(null, $heading ? ['bgColor' => 'F3F4F6'] : [])
                ->addText($cell, ['bold' => $heading, 'size' => $heading ? 9 : 10]);
        }
    }

    /**
     * Build the .docx bytes for a layout callback.
     */
    public static function document(callable $layout): string
    {
        \PhpOffice\PhpWord\Settings::setDefaultFontName('Arial');
        \PhpOffice\PhpWord\Settings::setDefaultFontSize(10);

        $phpWord = new PhpWord;
        $layout($phpWord->addSection());

        $directory = storage_path('app/tmp/report-seed-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory);
        $path = $directory.'/report.docx';

        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($path);

            return File::get($path);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
