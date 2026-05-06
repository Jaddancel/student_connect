<?php

namespace Database\Factories;

use App\Models\GeneratedDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedDocument>
 */
class GeneratedDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_submission_id' => null,
            'template_id' => null,
            'request_id' => null,
            'document_id' => null,
            'generated_by' => null,
            'docx_path' => 'generated-documents/'.fake()->uuid().'.docx',
            'pdf_path' => 'generated-documents/'.fake()->uuid().'.pdf',
            'status' => 'generated',
            'failure_reason' => null,
            'generated_at' => now(),
        ];
    }
}
