<?php

namespace App\Jobs;

use App\Models\FormScan;
use App\Services\OcrFieldMatcherService;
use App\Services\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessFormScan implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public FormScan $formScan)
    {
    }

    public function handle(OcrService $ocr, OcrFieldMatcherService $matcher): void
    {
        $this->formScan->update(['status' => 'processing']);

        $disk = config('documents.disk', 'public');
        $absolutePath = Storage::disk($disk)->path($this->formScan->scan_image_path);

        // 1. Extract raw OCR blocks.
        $blocks = $ocr->extract($absolutePath);
        $this->formScan->update(['ocr_raw' => $blocks]);

        // 2. Load the form's fields with their (optional) OCR regions.
        $fields = $this->formScan->form
            ->fields()
            ->get();

        // 3. Match blocks to fields.
        $dimensions = $matcher->getImageDimensions($absolutePath);
        $matched = $matcher->match($blocks, $fields, $dimensions);

        // 4. Persist the matched result.
        $this->formScan->update([
            'status' => 'done',
            'ocr_result' => $matched,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->formScan->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
