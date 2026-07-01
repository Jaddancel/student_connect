<?php

namespace Database\Seeders;

use App\Forms\FieldType;
use App\Models\Form;
use App\Models\Form\FormDescription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds demonstration forms built with the WYSIWYG system: a real `layout`
 * (rows/columns + header) plus `form_descriptions` exercising every field type.
 *
 * One form uses a designed logo/title header; the other uses an uploaded banner
 * image (copied from the bundled sample asset when available).
 */
class BuilderFormSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMembershipForm();
        $this->seedEventFeedbackForm();
    }

    private function seedMembershipForm(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'demo-membership'],
            [
                'name' => 'Demo — Membership Application',
                'is_active' => true,
                'is_published' => true,
                'sidebar_group' => ['admin'],
            ],
        );

        $fields = [
            ['field_key' => 'section_personal', 'field_label' => 'Personal Information', 'field_type' => FieldType::HEADING, 'is_required' => false],
            ['field_key' => 'full_name',  'field_label' => 'Full Name',     'field_type' => FieldType::TEXT,   'is_required' => true,  'placeholder_hint' => 'Last, First, M.I.'],
            ['field_key' => 'age',        'field_label' => 'Age',           'field_type' => FieldType::AGE,    'is_required' => true,  'field_options' => ['min' => 16, 'max' => 99, 'step' => 1]],
            ['field_key' => 'email',      'field_label' => 'Email',         'field_type' => FieldType::EMAIL,  'is_required' => true],
            ['field_key' => 'birthday',   'field_label' => 'Birthday',      'field_type' => FieldType::DATE,   'is_required' => true],
            ['field_key' => 'sex',        'field_label' => 'Sex',           'field_type' => FieldType::RADIO,  'is_required' => true,  'field_options' => ['options' => [['value' => 'male', 'label' => 'Male'], ['value' => 'female', 'label' => 'Female']]]],
            ['field_key' => 'year_level', 'field_label' => 'Year Level',    'field_type' => FieldType::SELECT, 'is_required' => true,  'field_options' => ['options' => [['value' => '1', 'label' => '1st Year'], ['value' => '2', 'label' => '2nd Year'], ['value' => '3', 'label' => '3rd Year'], ['value' => '4', 'label' => '4th Year']]]],
            ['field_key' => 'interests',  'field_label' => 'Areas of Interest', 'field_type' => FieldType::CHECKBOX, 'is_required' => false, 'field_options' => ['options' => [['value' => 'sports', 'label' => 'Sports'], ['value' => 'arts', 'label' => 'Arts'], ['value' => 'academics', 'label' => 'Academics']]]],
            ['field_key' => 'section_extra', 'field_label' => 'Supporting Details', 'field_type' => FieldType::HEADING, 'is_required' => false],
            ['field_key' => 'motivation', 'field_label' => 'Why do you want to join?', 'field_type' => FieldType::TEXTAREA, 'is_required' => true, 'field_options' => ['rows' => 5]],
            ['field_key' => 'photo',      'field_label' => 'ID Photo',      'field_type' => FieldType::IMAGE,  'is_required' => false],
            ['field_key' => 'requirements', 'field_label' => 'Requirements (PDF)', 'field_type' => FieldType::FILE, 'is_required' => false, 'field_options' => ['accept' => 'pdf']],
            ['field_key' => 'members_count', 'field_label' => 'Endorsing Members', 'field_type' => FieldType::NUMBER, 'is_required' => false, 'field_options' => ['min' => 0]],
            ['field_key' => 'agreement', 'field_label' => 'I certify the above is true.', 'field_type' => FieldType::CHECKBOX, 'is_required' => true],
            ['field_key' => 'signature', 'field_label' => 'Applicant Signature', 'field_type' => FieldType::SIGNATURE, 'is_required' => true],
        ];

        $this->syncFields($form, $fields);

        $form->update([
            'layout' => [
                'header' => [
                    'title' => 'Student Organization Membership',
                    'subtitle' => 'Office of Student Services and Development',
                    'align' => 'center',
                ],
                'rows' => [
                    ['columns' => [['span' => 12, 'fields' => ['section_personal']]]],
                    ['columns' => [['span' => 6, 'fields' => ['full_name']], ['span' => 3, 'fields' => ['age']], ['span' => 3, 'fields' => ['birthday']]]],
                    ['columns' => [['span' => 6, 'fields' => ['email']], ['span' => 3, 'fields' => ['sex']], ['span' => 3, 'fields' => ['year_level']]]],
                    ['columns' => [['span' => 12, 'fields' => ['interests']]]],
                    ['columns' => [['span' => 12, 'fields' => ['section_extra']]]],
                    ['columns' => [['span' => 12, 'fields' => ['motivation']]]],
                    ['columns' => [['span' => 6, 'fields' => ['photo']], ['span' => 6, 'fields' => ['requirements']]]],
                    ['columns' => [['span' => 4, 'fields' => ['members_count']], ['span' => 8, 'fields' => ['agreement']]]],
                    ['columns' => [['span' => 12, 'fields' => ['signature']]]],
                ],
            ],
        ]);
    }

    private function seedEventFeedbackForm(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'demo-event-feedback'],
            [
                'name' => 'Demo — Event Feedback',
                'is_active' => true,
                'is_published' => true,
                'sidebar_group' => ['admin'],
            ],
        );

        $fields = [
            ['field_key' => 'intro', 'field_label' => 'Thank you for attending! Please share your feedback below.', 'field_type' => FieldType::STATIC_TEXT, 'is_required' => false, 'field_options' => ['content' => 'Thank you for attending! Please share your feedback below.']],
            ['field_key' => 'attendee', 'field_label' => 'Your Name', 'field_type' => FieldType::TEXT, 'is_required' => false],
            ['field_key' => 'event_date', 'field_label' => 'Event Date', 'field_type' => FieldType::DATE, 'is_required' => true],
            ['field_key' => 'rating', 'field_label' => 'Overall Rating', 'field_type' => FieldType::SELECT, 'is_required' => true, 'field_options' => ['options' => [['value' => '5', 'label' => 'Excellent'], ['value' => '4', 'label' => 'Good'], ['value' => '3', 'label' => 'Average'], ['value' => '2', 'label' => 'Poor'], ['value' => '1', 'label' => 'Very Poor']]]],
            ['field_key' => 'comments', 'field_label' => 'Comments', 'field_type' => FieldType::TEXTAREA, 'is_required' => false, 'field_options' => ['rows' => 4]],
        ];

        $this->syncFields($form, $fields);

        $headerImage = $this->ensureSampleBanner();

        $form->update([
            'layout' => [
                'header' => array_filter([
                    'image' => $headerImage,
                    'title' => $headerImage ? null : 'Event Feedback',
                    'align' => 'center',
                ]),
                'rows' => [
                    ['columns' => [['span' => 12, 'fields' => ['intro']]]],
                    ['columns' => [['span' => 6, 'fields' => ['attendee']], ['span' => 6, 'fields' => ['event_date']]]],
                    ['columns' => [['span' => 12, 'fields' => ['rating']]]],
                    ['columns' => [['span' => 12, 'fields' => ['comments']]]],
                ],
            ],
        ]);
    }

    /**
     * Copy the bundled sample image to the uploads disk for the banner demo.
     * Returns the disk-relative path, or null if no sample asset is available.
     */
    private function ensureSampleBanner(): ?string
    {
        $disk = (string) config('documents.disk', 'public');
        $target = 'form-headers/demo-banner.png';

        if (Storage::disk($disk)->exists($target)) {
            return $target;
        }

        $candidates = [
            base_path('tailadmin-laravel.png'),
            public_path('images/logo/logo.png'),
        ];

        foreach ($candidates as $source) {
            if (is_file($source)) {
                Storage::disk($disk)->put($target, (string) file_get_contents($source));

                return $target;
            }
        }

        return null;
    }

    /**
     * @param  array<int,array<string,mixed>>  $fields
     */
    private function syncFields(Form $form, array $fields): void
    {
        $keys = [];
        foreach ($fields as $order => $field) {
            $keys[] = $field['field_key'];
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                [
                    'field_label' => $field['field_label'],
                    'field_type' => $field['field_type'],
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'field_order' => $order + 1,
                    'placeholder_hint' => $field['placeholder_hint'] ?? null,
                    'field_options' => $field['field_options'] ?? null,
                ],
            );
        }

        FormDescription::where('form_id', $form->id)
            ->whereNotIn('field_key', $keys)
            ->delete();
    }
}
