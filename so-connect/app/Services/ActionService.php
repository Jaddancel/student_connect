<?php

namespace App\Services;

use App\Models\Request;

class ActionService
{
    // Receive action by code
    public function passAction($command, $code)
    {
        // For demonstration, we will just return a simple message.
        // In a real application, you would look up the action by code and perform the necessary logic.
        switch ($code) {
            case 0:
                return $this->createMembershipRequest($command, $code);
            case 1:
            case 2:
                return $this->createEventRequest($command, $code);
            default:
                return null;
        }
    }

    protected function createMembershipRequest($command, $code)
    {
        // For creation of membership request in organizations, sans college organizations.
        return Request::create([
            'action' => $command['organization_id'].'|'.$command['user_id'],
            'action_type' => $code,
        ]);
    }

    protected function createEventRequest($command, $code)
    {
        // For creation of event request in organizations, sans college organizations.
        return Request::create([
            'action' => $command['organization_id'].'|'.
            $command['user_id'].'|'.
            $command['event_name'].'|'.
            $command['event_start_time'].'|'.
            $command['event_end_time'].'|'.
            ($command['event_desc_text'] ?? '').'|'.
            ($command['event_location'] ?? ''),
            'action_type' => $code,
            'user' => $command['user_id'] ?? null,
        ]);
    }

    protected function createEvaluationRequest($command, $code)
    {
        // For creation of evaluation request in organizations, sans college organizations.
        Request::create([
            'action' => $command['evaluation_description'].'|'.
            $command['evaluation_author'].'|'.
            $command['evaluation_score'],
            'action_type' => $code,
        ]);
    }

    protected function createDocumentGenerationRequest($command, $code)
    {
        // For creation of document request in organizations, sans college organizations.
        Request::create([
            'action' => $command['document_title'].'|'.$command['document_desc_text'].'|'.$command['document_link'].'|'.$command['document_author'],
            'action_type' => $code,
        ]);
    }

    protected function createDocumentAccessRequest($command, $code)
    {
        // For creation of document access request in organizations, sans college organizations.
        Request::create([
            'action' => $command['document_id'].'|'.$command['member_id'],
            'action_type' => $code,
        ]);
    }

    protected function createReportGenerationRequest($command, $code)
    {
        // For creation of report request in organizations, sans college organizations.
        Request::create([
            'action' => $command['report_type'].'|'.$command['report_description'].'|'.$command['report_author'],
            'action_type' => $code,
        ]);
    }

    protected function createFormUploadRequest($command, $code)
    {
        Request::create([
            // TODO: Create action for form uploads.
            'action_type' => $code,
        ]);
    }

    protected function createRoleChangeRequest($command, $code)
    {
        // For creation of role change request in organizations, sans college organizations.
        Request::create([
            'action' => $command['organization_id'].'|'.$command['member_id'].'|'.$command['new_role'],
            'action_type' => $code,
        ]);
    }
}
