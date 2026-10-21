<?php

use App\Models\ActionLog;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a support ticket from an assistant conversation', function () {
    $user = recordsUser(User::TYPE_OFFICER);

    $response = $this->actingAs($user)->postJson(route('support-tickets.store'), [
        'subject' => 'Cannot submit event plan',
        'description' => 'The submit button is disabled.',
        'current_path' => '/event-plans',
        'messages' => [
            ['role' => 'user', 'content' => 'Why can I not submit?'],
            ['role' => 'assistant', 'content' => 'Check the required fields.'],
        ],
    ])->assertCreated()->assertJson(['ok' => true, 'ticket_id' => 1]);

    $ticket = SupportTicket::query()->firstOrFail();
    expect($ticket->requester_id)->toBe($user->getKey())
        ->and($ticket->status)->toBe(SupportTicket::STATUS_OPEN)
        ->and($ticket->assistant_context)->toHaveCount(2)
        ->and($response->json('url'))->toBe(route('support-tickets.show', $ticket));

    expect(ActionLog::query()->where('action', 'support_ticket_created')->exists())->toBeTrue();
});

it('only lets a requester or support agent view and reply to a ticket', function () {
    $owner = recordsUser(User::TYPE_OFFICER);
    $stranger = recordsUser(User::TYPE_OFFICER);
    $admin = recordsUser(User::TYPE_ADMIN);
    $ticket = SupportTicket::query()->create([
        'requester_id' => $owner->getKey(),
        'subject' => 'Help needed',
        'description' => 'Details',
    ]);

    $this->actingAs($stranger)->get(route('support-tickets.show', $ticket))->assertForbidden();
    $this->actingAs($stranger)->post(route('support-tickets.reply', $ticket), ['body' => 'No access'])->assertForbidden();

    $this->actingAs($admin)->post(route('support-tickets.reply', $ticket), ['body' => 'We are checking this.'])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicket::STATUS_IN_PROGRESS)
        ->and($ticket->messages()->first()->from_support)->toBeTrue();

    $this->actingAs($owner)->get(route('support-tickets.show', $ticket))
        ->assertOk()->assertSee('We are checking this.');
});

it('lets admins assign and resolve tickets while blocking ordinary users', function () {
    $owner = recordsUser(User::TYPE_OFFICER);
    $admin = recordsUser(User::TYPE_ADMIN);
    $ticket = SupportTicket::query()->create([
        'requester_id' => $owner->getKey(),
        'subject' => 'Workflow question',
        'description' => 'Details',
    ]);

    $payload = ['status' => SupportTicket::STATUS_RESOLVED, 'assigned_to' => $admin->getKey()];
    $this->actingAs($owner)->patch(route('admin.support-tickets.update', $ticket), $payload)->assertForbidden();
    $this->actingAs($admin)->patch(route('admin.support-tickets.update', $ticket), $payload)->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicket::STATUS_RESOLVED)
        ->and($ticket->assigned_to)->toBe($admin->getKey())
        ->and($ticket->resolved_at)->not->toBeNull();
});

it('reopens a resolved ticket when its requester replies', function () {
    $owner = recordsUser(User::TYPE_OFFICER);
    $ticket = SupportTicket::query()->create([
        'requester_id' => $owner->getKey(),
        'subject' => 'Still blocked',
        'description' => 'Details',
        'status' => SupportTicket::STATUS_RESOLVED,
        'resolved_at' => now(),
    ]);

    $this->actingAs($owner)->post(route('support-tickets.reply', $ticket), ['body' => 'This is still happening.'])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicket::STATUS_OPEN)
        ->and($ticket->resolved_at)->toBeNull();
});

it('shows the assistant escalation control to authenticated users', function () {
    $this->actingAs(recordsUser(User::TYPE_OFFICER))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Still need help? Contact support');
});
