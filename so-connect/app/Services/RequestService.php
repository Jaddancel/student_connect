<?php

namespace App\Services;

class RequestService
{
    public function createRequest($action, $actionTypeCode)
    {
        $request = new \App\Models\ActionRequest;
        $request->action = $action;
        $request->request_action_type = $actionTypeCode;
        if ($this->validateRequest($action)) {
            $request->save();
        } else {
            return response()->json(['message' => 'Invalid request format'], 400);
        }

        return $request;
    }

    private function validateRequest($action)
    {
        return strpos($action, '|') !== false;
    }
}
