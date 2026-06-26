<?php

namespace App\Jobs;

use App\Helpers\FormTemplateHelper;
use App\Models\Form;
use App\Services\FieldCandidateExtractor;
use App\Services\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProcessFormWizardAiReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(private readonly int $formId) {}

    public function handle(LlmService $llm): void
    {
        $cacheKey = 'wizard_ai_result_'.$this->formId;

        try {
            $form = Form::findOrFail($this->formId);

            $disk = config('documents.disk', 'public');
            $docxPath = Storage::disk($disk)->path($form->ocr_reference_docx);

            if (! is_file($docxPath)) {
                Cache::put($cacheKey, ['status' => 'failed', 'error' => 'DOCX file not found.', 'fields' => []], 3600);

                return;
            }

            // Read the DOCX text directly from its XML — lossless, complete, and
            // in reading order. No LibreOffice render and no OCR: the document
            // already contains perfect digital text, so the LLM gets clean input.
            $text = FormTemplateHelper::extractTextFromDocx($docxPath);

            if (trim($text) === '') {
                Cache::put($cacheKey, ['status' => 'done', 'fields' => []], 3600);

                return;
            }

            // Deterministic candidate extraction is the floor: complete and
            // never empty. The LLM only refines (prunes/retypes) it when enabled,
            // and falls back to the candidates on any failure — so detection is
            // never worse than the deterministic result.
            $candidates = app(FieldCandidateExtractor::class)->extract($text);

            $fields = config('services.ollama.refine', true)
                ? $llm->refineFields($candidates)
                : $candidates;

            if ($fields === []) {
                $fields = $candidates;
            }

            Cache::put($cacheKey, [
                'status' => 'done',
                'fields' => $fields,
            ], 3600);
        } catch (\Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'fields' => [],
            ], 3600);
        }
    }
}
