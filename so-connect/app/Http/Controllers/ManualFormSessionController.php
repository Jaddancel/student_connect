<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Jobs\ParseManualFormScan;
use App\Models\Form;
use App\Models\ManualFormSession;
use App\Services\ManualForm\ManualFormSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Drives the manual-filling lifecycle from the browser: starting a draft from a
 * form page, downloading the frozen partial PDF, uploading the completed scan,
 * polling parse status, and resuming/deleting a draft. Every action authorizes
 * per session — the owner, or a public form's resume token — so these routes
 * can live outside the auth group alongside the public form renderer.
 */
class ManualFormSessionController extends Controller
{
    public function __construct(private readonly ManualFormSessionService $sessions) {}

    /**
     * Begin a manual-filling draft for the given form. Mirrors the form-access
     * gate of the online submit, then queues the partial PDF preparation.
     */
    public function start(Request $request, string $routeName): JsonResponse
    {
        $form = Form::query()
            ->where('route_name', $routeName)
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless(
            in_array((string) $form->system_function, [SystemFunction::SIGN_UP, SystemFunction::NEW_ORGANIZATION_REGISTRATION], true)
            || Form::isAccessibleBy($request->user()),
            403,
            'Form pages are available to organization officers only.',
        );

        $pdfTemplate = (array) ($form->pdf_template ?? []);
        if (trim((string) ($pdfTemplate['html'] ?? '')) === '') {
            return response()->json(['ok' => false, 'error' => 'This form has no printed template yet.'], 422);
        }

        ['session' => $session, 'token' => $token] = $this->sessions->start($form, $request);

        return response()->json([
            'ok' => true,
            'session_id' => (string) $session->getKey(),
            'status' => $session->status,
            'resume_url' => $this->resumeUrl($session, $token),
            'download_url' => route('manual.download', $session).($token ? '?t='.$token : ''),
            'status_url' => route('manual.status', $session).($token ? '?t='.$token : ''),
            'token' => $token,
        ]);
    }

    /** JSON status poll used while a draft prepares or parses. */
    public function status(Request $request, ManualFormSession $session): JsonResponse
    {
        $this->authorize403($request, $session);

        return response()->json([
            'ok' => true,
            'status' => $session->status,
            'error' => $session->parse_error,
            'warnings' => $session->parse_warnings ?? [],
            'download_ready' => $session->partial_pdf_path !== null,
        ]);
    }

    /** Stream the exact frozen partial PDF for printing. */
    public function download(Request $request, ManualFormSession $session)
    {
        $this->authorize403($request, $session);

        $disk = (string) config('documents.disk', 'public');
        abort_if($session->partial_pdf_path === null || ! Storage::disk($disk)->exists($session->partial_pdf_path), 404);

        return response(Storage::disk($disk)->get($session->partial_pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($session->form?->route_name ?: 'form').'-to-fill.pdf"',
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /** Resume page: the drop area, parsing state, or the review form. */
    public function show(Request $request, ManualFormSession $session)
    {
        $this->authorize403($request, $session);

        $token = (string) $request->query('t', '');
        $form = $session->form;

        // When parsing is done, render the ordinary form pre-filled with the
        // known + extracted values for review before the real submission.
        $review = null;
        if ($session->status === ManualFormSession::STATUS_REVIEW && $form !== null) {
            $context = \App\Forms\FormRenderContext::build($form, $request);
            $parsed = (array) ($session->parse_result['values'] ?? []);
            $prefill = array_merge(
                (array) $context['prefill'],
                (array) $session->draft_payload,
                (array) $session->known_fields,
                $parsed,
            );
            $hidden = (array) $context['hidden'];
            $hidden['manual_session_id'] = (string) $session->getKey();
            if ($token !== '') {
                $hidden['manual_token'] = $token;
            }

            $review = array_merge($context, [
                'prefill' => $prefill,
                'hidden' => $hidden,
                'manualEnabled' => false,
                'submitLabel' => 'Submit reviewed request',
                'confidence' => (array) $session->parse_confidence,
                'unresolved' => (array) ($session->parse_result['unresolved'] ?? []),
                'lowConfidence' => $this->lowConfidenceKeys($session),
            ]);
        }

        return view('pages.manual.show', [
            'title' => 'Manual filling',
            'session' => $session,
            'form' => $form,
            'token' => $token,
            'review' => $review,
        ]);
    }

    /**
     * Extracted keys whose confidence is below the configured threshold — shown
     * as "please check" hints in review.
     *
     * @return array<int,string>
     */
    private function lowConfidenceKeys(ManualFormSession $session): array
    {
        $threshold = (float) config('services.document_vision.confidence_threshold', 0.55);

        return collect((array) $session->parse_confidence)
            ->filter(fn ($score) => (float) $score < $threshold)
            ->keys()
            ->map(fn ($k) => (string) $k)
            ->all();
    }

    /** Accept the completed scan(s) and kick off parsing. */
    public function upload(Request $request, ManualFormSession $session): JsonResponse
    {
        $this->authorize403($request, $session);

        if (! in_array($session->status, [
            ManualFormSession::STATUS_AWAITING_SCAN,
            ManualFormSession::STATUS_FAILED,
            ManualFormSession::STATUS_REVIEW,
        ], true)) {
            return response()->json(['ok' => false, 'error' => 'This draft is not ready for a scan yet.'], 422);
        }

        $maxPages = (int) config('services.document_vision.max_pages', 10);
        $request->validate([
            'scans' => ['required', 'array', 'max:'.$maxPages],
            'scans.*' => ['file', 'mimes:pdf,jpeg,jpg,png', 'max:10240'],
        ]);

        $disk = (string) config('documents.disk', 'public');
        $dir = 'manual-form/'.$session->getKey().'/scans';
        Storage::disk($disk)->deleteDirectory($dir);

        $paths = [];
        foreach ($request->file('scans', []) as $file) {
            $paths[] = $file->store($dir, $disk);
        }

        $session->forceFill([
            'scan_paths' => $paths,
            'status' => ManualFormSession::STATUS_PARSING,
            'parse_error' => null,
            'parse_result' => null,
            'parse_confidence' => null,
            'parse_warnings' => null,
        ])->save();

        ParseManualFormScan::dispatch((string) $session->getKey());

        return response()->json(['ok' => true, 'status' => $session->status]);
    }

    /** Re-run parsing on the already-uploaded scan after a failure. */
    public function retry(Request $request, ManualFormSession $session): JsonResponse
    {
        $this->authorize403($request, $session);

        if (empty($session->scan_paths)) {
            return response()->json(['ok' => false, 'error' => 'Upload a scan first.'], 422);
        }

        $session->forceFill([
            'status' => ManualFormSession::STATUS_PARSING,
            'parse_error' => null,
        ])->save();

        ParseManualFormScan::dispatch((string) $session->getKey());

        return response()->json(['ok' => true, 'status' => $session->status]);
    }

    /** Delete a draft and its files. */
    public function destroy(Request $request, ManualFormSession $session)
    {
        $this->authorize403($request, $session);

        $disk = (string) config('documents.disk', 'public');
        Storage::disk($disk)->deleteDirectory('manual-form/'.$session->getKey());
        $session->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('manual.drafts')->with('success', 'Draft deleted.');
    }

    /**
     * The officer Drafts page: the signed-in user's own open manual drafts,
     * filterable by date range and form. Public guests never reach here — they
     * resume through their private link.
     */
    public function drafts(Request $request)
    {
        $user = $request->user();
        abort_unless($user !== null && (int) $user->user_type === 3, 403);

        $query = ManualFormSession::query()
            ->where('user_id', (int) $user->getKey())
            ->whereIn('status', ManualFormSession::OPEN_STATUSES)
            ->with('form:id,name,route_name')
            ->latest('updated_at');

        if (($from = (string) $request->query('from', '')) !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if (($to = (string) $request->query('to', '')) !== '') {
            $query->whereDate('created_at', '<=', $to);
        }
        if (($formId = (int) $request->query('form_id', 0)) > 0) {
            $query->where('form_id', $formId);
        }

        $drafts = $query->paginate(15)->withQueryString();

        // Forms the user actually has drafts for, for the filter dropdown.
        $formOptions = ManualFormSession::query()
            ->where('user_id', (int) $user->getKey())
            ->whereIn('status', ManualFormSession::OPEN_STATUSES)
            ->with('form:id,name')
            ->get()
            ->pluck('form')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('pages.manual.drafts', [
            'title' => 'Drafts',
            'drafts' => $drafts,
            'formOptions' => $formOptions,
            'filters' => [
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'form_id' => (int) $request->query('form_id', 0),
            ],
        ]);
    }

    private function authorize403(Request $request, ManualFormSession $session): void
    {
        // A mismatch is a 404, not a 403 — a wrong/absent token must not confirm
        // the draft exists.
        abort_unless($this->sessions->authorize($request, $session), 404);
    }

    private function resumeUrl(ManualFormSession $session, ?string $token): string
    {
        return route('manual.show', $session).($token ? '?t='.$token : '');
    }
}
