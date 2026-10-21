<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\ActionLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = SupportTicket::query()
            ->with(['requester.profileRecord', 'assignee.profileRecord'])
            ->where('requester_id', $request->user()->getKey())
            ->latest('updated_at')
            ->paginate(20);

        return view('pages.support-tickets.index', compact('tickets') + [
            'title' => 'My Support Tickets',
            'isSupportAgent' => false,
            'statuses' => SupportTicket::statuses(),
        ]);
    }

    public function adminIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $query = SupportTicket::query()->with(['requester.profileRecord', 'assignee.profileRecord']);
        if (array_key_exists($status, SupportTicket::statuses())) {
            $query->where('status', $status);
        }

        return view('pages.support-tickets.index', [
            'title' => 'Support Ticket Queue',
            'tickets' => $query->orderByRaw("CASE WHEN status IN ('open', 'in_progress') THEN 0 ELSE 1 END")
                ->latest('updated_at')->paginate(20)->withQueryString(),
            'isSupportAgent' => true,
            'statuses' => SupportTicket::statuses(),
            'selectedStatus' => $status,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'current_path' => ['nullable', 'string', 'max:2048'],
            'messages' => ['nullable', 'array', 'max:20'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $ticket = SupportTicket::query()->create([
            'requester_id' => $request->user()->getKey(),
            'subject' => trim($validated['subject']),
            'description' => trim($validated['description']),
            'page_path' => $validated['current_path'] ?? null,
            'assistant_context' => collect($validated['messages'] ?? [])->map(fn (array $message) => [
                'role' => $message['role'],
                'content' => $message['content'],
            ])->values()->all(),
            'last_response_at' => now(),
        ]);

        ActionLogger::log(ActionLogger::CATEGORY_SUPPORT, 'support_ticket_created',
            "Created support ticket #{$ticket->getKey()}", ['source' => 'assistant'], $ticket);

        $payload = [
            'ok' => true,
            'ticket_id' => $ticket->getKey(),
            'url' => route('support-tickets.show', $ticket),
        ];

        return $request->expectsJson()
            ? response()->json($payload, 201)
            : redirect($payload['url'])->with('success', 'Support ticket created.');
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $this->authorizeAccess($request, $ticket);
        $ticket->load(['requester.profileRecord', 'assignee.profileRecord', 'messages.user.profileRecord']);

        return view('pages.support-tickets.show', [
            'title' => 'Support Ticket #'.$ticket->getKey(),
            'ticket' => $ticket,
            'isSupportAgent' => $this->isSupportAgent($request),
            'statuses' => SupportTicket::statuses(),
            'agents' => $this->isSupportAgent($request)
                ? User::query()->with('profileRecord')->whereIn('user_type', [User::TYPE_SUPERADMIN, User::TYPE_ADMIN])->get()
                : collect(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeAccess($request, $ticket);
        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $support = $this->isSupportAgent($request);

        DB::transaction(function () use ($request, $ticket, $validated, $support) {
            $ticket->messages()->create([
                'user_id' => $request->user()->getKey(),
                'body' => trim($validated['body']),
                'from_support' => $support,
            ]);

            $changes = ['last_response_at' => now()];
            if ($support && $ticket->status === SupportTicket::STATUS_OPEN) {
                $changes['status'] = SupportTicket::STATUS_IN_PROGRESS;
            } elseif (! $support && in_array($ticket->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
                $changes['status'] = SupportTicket::STATUS_OPEN;
                $changes['resolved_at'] = null;
            }
            $ticket->update($changes);
        });

        ActionLogger::log(ActionLogger::CATEGORY_SUPPORT, 'support_ticket_replied',
            "Replied to support ticket #{$ticket->getKey()}", ['from_support' => $support], $ticket);

        return back()->with('success', 'Reply sent.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::statuses()))],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'user_id')->whereIn('user_type', [User::TYPE_SUPERADMIN, User::TYPE_ADMIN])],
        ]);

        $ticket->update([
            'status' => $validated['status'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'resolved_at' => in_array($validated['status'], [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true) ? now() : null,
        ]);

        ActionLogger::log(ActionLogger::CATEGORY_SUPPORT, 'support_ticket_updated',
            "Updated support ticket #{$ticket->getKey()}", $ticket->only(['status', 'assigned_to']), $ticket);

        return back()->with('success', 'Ticket updated.');
    }

    private function authorizeAccess(Request $request, SupportTicket $ticket): void
    {
        abort_unless($this->isSupportAgent($request) || (int) $ticket->requester_id === (int) $request->user()->getKey(), 403);
    }

    private function isSupportAgent(Request $request): bool
    {
        return in_array((int) $request->user()->user_type, [User::TYPE_SUPERADMIN, User::TYPE_ADMIN], true);
    }
}
