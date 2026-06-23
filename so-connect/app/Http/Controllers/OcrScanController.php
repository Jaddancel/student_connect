<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFormScan;
use App\Models\Form;
use App\Models\FormScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OcrScanController extends Controller
{
    /**
     * Accept a scan image for a form, queue OCR processing, and return the scan id.
     *
     * No route-level auth middleware: a guest may scan only when the form opts in
     * via allows_guest_scan; otherwise an authenticated user is required.
     */
    public function store(Request $request, Form $form): JsonResponse
    {
        if (! $form->allows_guest_scan && ! $request->user()) {
            abort(403, 'Scanning is not available for this form.');
        }

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $disk = config('documents.disk', 'public');
        $file = $request->file('image');
        $filename = Str::lower(Str::random(24)).'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs(
            'form-scans/'.$form->id,
            $filename,
            ['disk' => $disk]
        );

        $scan = FormScan::create([
            'form_id' => $form->id,
            'uploaded_by' => $request->user()?->getKey(),
            'scan_image_path' => $path,
            'status' => 'pending',
        ]);

        ProcessFormScan::dispatch($scan);

        return response()->json([
            'scan_id' => $scan->id,
            'status' => $scan->status,
        ]);
    }

    /**
     * Return the current status and (if done) the matched field values.
     * Polled by the front-end scanner component.
     */
    public function result(FormScan $scan): JsonResponse
    {
        return response()->json([
            'status' => $scan->status,
            'ocr_result' => $scan->ocr_result ?? [],
            'error_message' => $scan->error_message,
        ]);
    }
}
