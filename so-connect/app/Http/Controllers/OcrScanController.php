<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFormScan;
use App\Models\Form;
use App\Models\FormScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OcrScanController extends Controller
{
    /**
     * Accept a scanned image, queue OCR processing, and return the scan id.
     */
    public function store(Request $request, Form $form): JsonResponse
    {
        $this->authorizeScan($request, $form);

        $request->validate([
            'scan' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $disk = config('documents.disk', 'public');

        $path = $request->file('scan')->store("form-scans/{$form->id}", $disk);

        $scan = FormScan::create([
            'form_id' => $form->id,
            'uploaded_by' => $request->user()?->getKey(),
            'scan_image_path' => $path,
            'status' => FormScan::STATUS_PENDING,
        ]);

        ProcessFormScan::dispatch($scan);

        return response()->json([
            'scan_id' => $scan->id,
            'status' => $scan->status,
        ], 202);
    }

    /**
     * Poll endpoint: returns the current status and (when done) the matched fields.
     */
    public function result(FormScan $scan): JsonResponse
    {
        return response()->json([
            'status' => $scan->status,
            'ocr_result' => $scan->ocr_result ?? [],
            'error_message' => $scan->status === FormScan::STATUS_FAILED ? $scan->error_message : null,
        ]);
    }

    /**
     * The route is public; this controller enforces access. A scan is allowed when
     * the form opts into guest scanning, or when the requester is authenticated.
     */
    private function authorizeScan(Request $request, Form $form): void
    {
        abort_unless($form->allows_guest_scan || $request->user() !== null, 403);
    }
}
