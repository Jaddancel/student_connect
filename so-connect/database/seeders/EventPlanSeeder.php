<?php

namespace Database\Seeders;

use App\Models\EventPlan;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventPlanSeeder extends Seeder
{
    public function run(): void
    {
        $eventPlanRequestType = RequestType::query()->where('system_key', RequestType::SYSTEM_KEY_EVENT_PLAN)->first();

        $organizations = Organization::query()->get('organization_id');
        $officerUsers = User::query()->where('user_type', 3)->get(['user_id']);

        if ($officerUsers->isEmpty() || $organizations->isEmpty()) {
            return;
        }

        $statuses = ['pending', 'pending', 'pending', 'approved', 'approved', 'rejected', 'junked'];

        foreach ($organizations as $org) {
            for ($i = 0; $i < 10; $i++) {
                $user = $officerUsers->random();
                $status = $statuses[array_rand($statuses)];

                $actionRequest = ActionRequest::query()->create([
                    'action' => '',
                    'requested_at' => now()->subDays(rand(1, 30)),
                    'action_type' => 10,
                    'user' => $user->user_id,
                    'request_type_id' => $eventPlanRequestType?->request_type_id,
                    'organization_id' => $org->organization_id,
                    'payload' => [],
                ]);

                $plan = EventPlan::query()->create([
                    'organization_id' => $org->organization_id,
                    'created_by' => $user->user_id,
                    'title' => $this->randomTitle(),
                    'target_date' => now()->addDays(rand(5, 90))->format('Y-m-d'),
                    'resources_needed' => rand(0, 1) ? $this->randomResources() : null,
                    'persons_responsible' => [],
                    'status' => $status,
                    'event_id' => null,
                    'request_id' => $actionRequest->request_id,
                ]);

                $actionRequest->update(['payload' => ['event_plan_id' => $plan->event_plan_id, 'organization_id' => $org->organization_id]]);
            }
        }
    }

    private function randomTitle(): string
    {
        $titles = [
            'General Assembly',
            'Leadership Training Seminar',
            'Sports Fest',
            'Community Outreach Program',
            'Fund Raising Drive',
            'Year-End Party',
            'Academic Forum',
            'Tree Planting Activity',
            'Health Awareness Campaign',
            'Sportsfest Orientation',
            'Freshmen Orientation',
            'Acquaintance Party',
            'Research Symposium',
            'Cultural Night',
            'Clean-Up Drive',
        ];

        return $titles[array_rand($titles)];
    }

    private function randomResources(): string
    {
        $resources = [
            'Sound system, chairs, tables, projector, and microphone.',
            'Transportation, food packs, and first aid kit.',
            'Tarpaulin, flyers, and printing materials.',
            'Venue reservation, audio-visual equipment, and snacks.',
            'Sports equipment, referees, and prizes.',
        ];

        return $resources[array_rand($resources)];
    }
}
