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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public FormScan $formScan) {}

    public function handle(OcrService $ocr, OcrFieldMatcherService $matcher): void
    {
        $scan = $this->formScan;

        $scan->update([
            'status' => FormScan::STATUS_PROCESSING,
            'job_id' => $this->job?->getJobId(),
        ]);

        $disk = config('documents.disk', 'public');
        $absolutePath = Storage::disk($disk)->path($scan->scan_image_path);

        // 1. Run OCR → raw blocks (text + bbox + confidence).
        $blocks = $ocr->extract($absolutePath);
        $scan->update(['ocr_raw' => $blocks]);

        // 2. Load the form's fields (with calibrated regions, if any).
        $fields = $scan->form()->first()?->fields()->get() ?? collect();

        // 3. Map blocks onto fields.
        $dimensions = $matcher->getImageDimensions($absolutePath);
        $result = $matcher->match($blocks, $fields, $dimensions);

        // 4. Done.
        $scan->update([
            'ocr_result' => $result,
            'status' => FormScan::STATUS_DONE,
            'error_message' => null,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->formScan->update([
            'status' => FormScan::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ]);
    }
}
