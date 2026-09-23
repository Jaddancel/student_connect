<?php

namespace App\Jobs;

use App\Services\ManualForm\TemplateManualSchemaGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerates a template's baseline manual-filling schema after an explicit
 * Form Builder save has adopted the Step 2 draft. Dispatched from
 * FormBuilderController — never from an OnlyOffice autosave callback, which
 * would fire repeatedly.
 *
 * The captured version guards against stale runs: if the template moves on
 * before this executes, the generator no-ops.
 */
class GenerateTemplateManualSchema implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $templateId,
        public readonly int $templateVersion,
    ) {}

    public function handle(TemplateManualSchemaGenerator $generator): void
    {
        $generator->generate($this->templateId, $this->templateVersion);
    }
}
