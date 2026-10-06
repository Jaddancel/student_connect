<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class OrganizationInvitationController extends Controller
{
    public function redeem(Request $request): RedirectResponse
    {
        $rawToken = (string) $request->query('token', '');

        if ($rawToken === '') {
            return redirect()->route('login')
                ->withErrors(['user_email' => 'This invitation link is invalid or has expired.']);
        }

        $invitation = OrganizationInvitation::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($invitation === null) {
            return redirect()->route('login')
                ->withErrors(['user_email' => 'This invitation link is invalid or has expired.']);
        }

        $form = SystemFunction::form(SystemFunction::SIGN_UP);
        if ($form === null || ! $form->is_active || ! $form->route_name) {
            return redirect()->route('login')
                ->withErrors(['user_email' => 'The sign-up form is not available right now.']);
        }

        $prefill = base64_encode(Crypt::encryptString(json_encode([
            'email' => $invitation->email,
            'organization_id' => (int) $invitation->organization_id,
            'role' => $invitation->role,
            'position' => $invitation->position,
            'invitation_id' => (int) $invitation->getKey(),
            'token' => $rawToken,
        ])));

        return redirect()->route('forms.render', [
            'routeName' => $form->route_name,
            'prefill' => $prefill,
        ])->with('success', 'Please complete the sign-up form to accept your organization invitation.');
    }
}
