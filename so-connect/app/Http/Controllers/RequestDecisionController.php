<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApprovalResource;
use App\Models\Approval;
use App\Models\Request as ActionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RequestDecisionController extends Controller
{
    public function store(Request $request, int $requestId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
        ]);

        $actionRequest = ActionRequest::query()->findOrFail($requestId);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $requiredDashboard = (int) $actionRequest->action_type === 3 ? 'president' : 'admin';

        if (! Gate::forUser($user)->allows('access-dashboard', $requiredDashboard)) {
            return response()->json([
                'message' => 'You are not authorized to decide this request.',
            ], 403);
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => (int) $actionRequest->getKey()],
            [
                'admin' => (int) $user->getKey(),
                'approved_at' => now(),
                'is_rejected' => $validated['decision'] === 'reject',
            ]
        );

        return response()->json([
            'message' => $validated['decision'] === 'approve'
                ? 'Request approved successfully.'
                : 'Request rejected successfully.',
            'data' => ApprovalResource::make($approval->fresh()),
        ]);
    }
}
