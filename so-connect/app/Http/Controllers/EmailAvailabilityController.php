<?php

namespace App\Http\Controllers;

use App\Forms\Handlers\NewOrganizationRegistrationHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailAvailabilityController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->query('email', '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['registered' => false, 'valid' => false]);
        }

        $handler = app(NewOrganizationRegistrationHandler::class);

        return response()->json([
            'registered' => $handler->isRegisteredEmail($email),
            'valid' => true,
        ]);
    }
}
