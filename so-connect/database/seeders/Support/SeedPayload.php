<?php

namespace Database\Seeders\Support;

use App\Forms\FieldType;
use App\Models\Form;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

final class SeedPayload
{
    public static function for(Form $form, Organization $organization, User $user, Carbon $date): array
    {
        $profile = $user->profile()->firstOrFail();
        $detail = $organization->detail()->firstOrFail();
        $president = User::query()->findOrFail($organization->officersOfThisOrganization()->where('role', 'president')->value('user'));
        $presidentProfile = $president->profile()->firstOrFail();
        $advisers = collect([1, 2])->map(fn ($index) => User::query()
            ->where('user_email', 'adviser-'.$organization->getKey().'-'.$index.'@example.com')->firstOrFail());
        $names = $advisers->map(fn (User $adviser) => self::name($adviser))->all();
        $adviserProfiles = $advisers->map(fn (User $adviser) => $adviser->profile()->firstOrFail());
        $semester = self::semesterFor($date);
        $special = [
            'organization_id' => (int) $organization->getKey(),
            'organization_name' => $detail->name,
            'organization_initials' => $detail->initials,
            'organization_description' => $detail->detail_text,
            'organization_type' => (int) $organization->organization_type,
            'first_name' => $profile->first_name,
            'middle_name' => $profile->middle_name,
            'last_name' => $profile->last_name,
            'officer_first_name' => $profile->first_name,
            'officer_middle_name' => $profile->middle_name,
            'officer_last_name' => $profile->last_name,
            'email' => $user->user_email,
            'password' => $user->user_password,
            'student_id' => $profile->student_id,
            'contact_number' => $profile->contact_number,
            'age' => $profile->age,
            'sex' => $profile->sex,
            'religious_affiliation' => $profile->religion,
            'nationality' => $profile->nationality,
            'birthday' => $profile->birthday,
            'birthplace' => $profile->birthplace,
            'present_address' => $profile->addressOfUser?->barangay ?? $profile->home_address,
            'home_address' => $profile->home_address,
            'parents_guardian' => $profile->parents_guardian,
            'course' => $profile->course,
            'year_level' => $profile->year_section,
            'talents_hobbies' => $profile->talents_hobbies,
            'photo' => $profile->photo,
            'signature' => $profile->signature_path,
            'officer_signature' => $profile->signature_path,
            'president_signature' => $presidentProfile->signature_path,
            'president_name' => self::name($president),
            'name_of_president' => self::name($president),
            'president_contact' => $presidentProfile->contact_number,
            'adviser_name' => $names[0],
            'adviser1_name' => $names[0],
            'adviser_1_name' => $names[0],
            'adviser2_name' => $names[1],
            'adviser_2_name' => $names[1],
            'adviser1_contact' => $adviserProfiles[0]->contact_number,
            'adviser2_contact' => $adviserProfiles[1]->contact_number,
            'adviser_signature' => $adviserProfiles[0]->signature_path,
            'adviser_1_signature' => $adviserProfiles[0]->signature_path,
            'adviser_2_signature' => $adviserProfiles[1]->signature_path,
            'adviser_names' => $names,
            'faculty_advisers' => $names,
            'school_year' => $semester->schoolYear(),
            'current_school_year' => $semester->schoolYear(),
            'current_semester' => $semester->semesterLabel().' Semester',
            'date' => $date->toDateString(),
            'current_date' => $date->toDateString(),
            'target_date' => $date->toDateString(),
            'event_start_time' => '09:00',
            'event_end_time' => '16:00',
            'title' => fake()->randomElement([
                'Mental Health Awareness', 'Environmental Stewardship', 'Financial Literacy', 'Disaster Preparedness',
                'Digital Citizenship', 'Leadership Development', 'Career Readiness', 'Gender and Development',
                'Cultural Heritage', 'Research and Innovation', 'Community Health', 'Peer Tutoring',
                'Tree Planting', 'Blood Donation', 'Outreach and Feeding', 'Sports and Wellness',
            ]).' '.fake()->randomElement(['Seminar', 'Forum', 'Workshop', 'Summit', 'Drive', 'Caravan', 'Symposium', 'Training']),
            'event_location' => fake()->randomElement(['TAU Auditorium', 'College Hall', 'University Gym']),
            'purpose_of_activity' => 'Develop student leadership, community service and collaborative learning.',
            'persons_involved' => 'Organization officers, student participants and faculty advisers.',
            'problems_encountered' => 'Minor scheduling conflicts resolved by the organizing committee.',
            'university_facilities' => ['Auditorium', 'Sound system'],
            'waiver_required' => 'option_2',
            'co_sponsored' => fake()->numberBetween(0, 1),
            'dropdown' => [(int) Organization::query()->whereKeyNot($organization->getKey())->inRandomOrder()->value('organization_id')],
            'freshman' => fake()->numberBetween(15, 30),
            'sophomore' => fake()->numberBetween(15, 30),
            'junior' => fake()->numberBetween(15, 30),
            'number_of_organization_members_attended' => fake()->numberBetween(30, 90),
            'actual_duration_of_time_in_minutes' => fake()->numberBetween(60, 180),
            'individual_awards' => fake()->numberBetween(1, 3),
            'group_awards' => fake()->numberBetween(1, 3),
            'donation_amount' => fake()->numberBetween(1, 10) * 500,
            'in_kinds' => ['School supplies', 'Food packs'],
            'projectTitle' => 'Student community development project',
            'natureOfProject' => 'Community service and student development',
            'projectArea' => 'Tarlac Agricultural University',
            'letterOfIntent' => 'We request approval to conduct this project for students and the local community.',
        ];
        $payload = [];
        foreach ($form->fields as $field) {
            $key = $field->field_key;
            if (FieldType::isPresentational($field->field_type)) {
                continue;
            }
            if (array_key_exists($key, $special)) {
                $payload[$key] = $special[$key];

                continue;
            }
            $options = (array) $field->field_options;
            $payload[$key] = match ($field->field_type) {
                FieldType::TEXT, FieldType::TEXTAREA => fake()->sentence(6),
                FieldType::NUMBER, FieldType::AGE => max(1, (int) ($options['min'] ?? 1)),
                FieldType::DATE => $date->toDateString(),
                FieldType::TIME => '09:00',
                FieldType::CHECKBOX => fake()->numberBetween(0, 1),
                FieldType::POSITION_SELECT => 'Others',
                FieldType::ORG_SELECT => (int) $organization->getKey(),
                FieldType::ORGANIZATION_TYPE_SELECT => (int) $organization->organization_type,
                FieldType::SELECT, FieldType::RADIO => fake()->randomElement(array_column($options['options'] ?? [], 'value')),
                FieldType::SIGNATURE => $profile->signature_path,
                FieldType::IMAGE => $profile->photo,
                FieldType::MULTI_IMAGE => SeedData::eventPhotos(),
                FieldType::TEXT_LIST => ['Student officers', 'Faculty advisers'],
                FieldType::TABLE_INPUT => [self::tableRow($options)],
                FieldType::WORKPLAN_EVENTS, FieldType::ACTIVITY_TABLE => [],
                FieldType::WORKPLAN_SELECT, FieldType::WAIVER_SCAN => null,
                FieldType::COMPUTED => 0,
                default => throw new RuntimeException('Unsupported seed field type: '.$field->field_type),
            };
        }

        return self::calculate($form, $payload);
    }

    public static function calculate(Form $form, array $payload): array
    {
        foreach ($form->fields as $field) {
            $options = (array) $field->field_options;
            if ($field->field_type === FieldType::TABLE_INPUT) {
                $multipliers = $options['row_total']['multiply'] ?? [];
                $totalKey = $options['row_total']['key'] ?? null;
                if ($multipliers !== [] && $totalKey !== null) {
                    foreach ($payload[$field->field_key] as &$row) {
                        $row[$totalKey] = array_product(array_map(fn ($key) => (float) ($row[$key] ?? 0), $multipliers));
                    }
                    unset($row);
                }
            }
        }
        foreach ($form->fields as $field) {
            $options = (array) $field->field_options;
            if ($field->field_type === FieldType::COMPUTED && ($options['formula'] ?? '') === 'sum') {
                $payload[$field->field_key] = array_sum(array_map(fn ($key) => (float) ($payload[$key] ?? 0), $options['args'] ?? []));
            }
            if (isset($options['calculate_from'], $options['calculate_column'])) {
                $payload[$field->field_key] = array_sum(array_column($payload[$options['calculate_from']] ?? [], $options['calculate_column']));
            }
        }

        return $payload;
    }

    private static function tableRow(array $options): array
    {
        $row = [];
        foreach ($options['columns'] ?? [] as $column) {
            $row[$column['key']] = ($column['type'] ?? 'text') === 'number'
                ? fake()->numberBetween(1, 5) * ($column['key'] === 'quantity' ? 1 : 100)
                : fake()->randomElement(['Materials', 'Transportation', 'Refreshments']);
        }

        return $row;
    }

    public static function name(User $user): string
    {
        $profile = $user->profile()->firstOrFail();

        return $profile->first_name.' '.$profile->last_name;
    }

    public static function president(Organization $organization): User
    {
        return User::query()->findOrFail(Officer::query()->where('organization', $organization->getKey())->where('role', 'president')->value('user'));
    }

    public static function semesterFor(Carbon $date): Semester
    {
        return Semester::query()->orderBy('starts_at')->get()
            ->first(fn (Semester $semester) => $date->between($semester->starts_at, $semester->endsAt()))
            ?? Semester::query()->where('starts_at', '>=', $date)->orderBy('starts_at')->firstOrFail();
    }
}
