<?php

use App\Helpers\FormTemplateHelper;
use App\Models\Form;
use App\Models\RequestType;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('uses the form request type when creating a document generation request', function () {
    $requester = recordsUser(3);
    $organization = recordsOrganization('Docs Org', 'DOC');

    $requestType = RequestType::query()->create([
        'name' => 'Specific Form Request',
        'category' => RequestType::CATEGORY_ORGANIZATION,
        'system_key' => 'form:custom',
        'created_by' => $requester->getKey(),
        'is_active' => true,
    ]);

    $form = Form::query()->create([
        'name' => 'Document Form',
        'description_text' => 'Test form',
        'request_type_id' => $requestType->getKey(),
        'organization_id' => $organization->organization_id,
        'created_by' => $requester->getKey(),
        'is_active' => true,
        'is_published' => true,
        'route_name' => 'document-form',
        'pdf_template' => ['html' => '<p>Template</p>'],
    ]);

    $created = app(DocumentGenerationService::class)->createDocumentGenerationRequest(
        (int) $organization->organization_id,
        123,
        (int) $form->getKey(),
        (int) $requester->getKey(),
    );

    expect((int) $created->request_type_id)->toBe((int) $requestType->getKey())
        ->and((int) $created->action_type)->toBe(FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
        ->and((int) $created->form_id)->toBe((int) $form->getKey())
        ->and((int) $created->organization_id)->toBe((int) $organization->organization_id)
        ->and((int) (($created->payload ?? [])['submission_id'] ?? 0))->toBe(123);
});

it('falls back to the system form generation request type when form type is absent', function () {
    $requester = recordsUser(3);
    $organization = recordsOrganization('Fallback Org', 'FBO');

    $created = app(DocumentGenerationService::class)->createDocumentGenerationRequest(
        (int) $organization->organization_id,
        456,
        0,
        (int) $requester->getKey(),
    );

    $systemType = RequestType::query()->where('system_key', RequestType::SYSTEM_KEY_FORM_GENERATION)->first();

    expect($systemType)->not->toBeNull()
        ->and((int) $created->request_type_id)->toBe((int) $systemType->getKey())
        ->and((int) $created->action_type)->toBe(FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
        ->and((int) (($created->payload ?? [])['submission_id'] ?? 0))->toBe(456);
});
