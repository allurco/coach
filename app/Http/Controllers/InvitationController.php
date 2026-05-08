<?php

namespace App\Http\Controllers;

use App\Filament\Pages\Coach;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class InvitationController extends Controller
{
    /**
     * Show the set-password form for an invitation token. If the token
     * doesn't resolve cleanly (already used, expired, never existed),
     * render a friendly page explaining what happened instead of a
     * raw 404 from abort().
     */
    public function show(string $token): View|Response
    {
        $resolution = $this->resolve($token);

        if ($resolution['status'] !== 'ok') {
            return $this->renderError($resolution['status']);
        }

        return view('pages.invitation.accept', [
            'user' => $resolution['user'],
            'token' => $token,
        ]);
    }

    /**
     * Accept the invitation: validate password, persist, log the user in,
     * redirect to the Coach.
     */
    public function accept(Request $request, string $token): RedirectResponse|Response
    {
        $resolution = $this->resolve($token);

        if ($resolution['status'] !== 'ok') {
            return $this->renderError($resolution['status']);
        }

        $user = $resolution['user'];

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            'invitation_token' => null,
            'accepted_invitation_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Redirect explicitly to the Coach page rather than '/' — Filament's
        // panel root can 404 transiently when its internal route cache is
        // stale (post-deploy). Coach::getUrl() always resolves correctly.
        return redirect(Coach::getUrl());
    }

    /**
     * Find the user behind a token and classify the result. Returns one of:
     *   - ['status' => 'ok',         'user' => $user]
     *   - ['status' => 'used',       'user' => $user]   token already accepted
     *   - ['status' => 'expired',    'user' => $user]   over 7 days since invite
     *   - ['status' => 'not_found',  'user' => null]    no user has this token
     *
     * @return array{status: string, user: ?User}
     */
    protected function resolve(string $token): array
    {
        $user = User::where('invitation_token', $token)->first();

        if (! $user) {
            // Maybe the token WAS valid but already used — invitation_token
            // gets cleared on accept. Worth surfacing that distinction.
            $alreadyAccepted = User::whereNotNull('accepted_invitation_at')
                ->whereNull('invitation_token')
                ->exists();

            return ['status' => $alreadyAccepted ? 'used' : 'not_found', 'user' => null];
        }

        if ($user->invited_at && $user->invited_at->lt(now()->subDays(7))) {
            return ['status' => 'expired', 'user' => $user];
        }

        return ['status' => 'ok', 'user' => $user];
    }

    protected function renderError(string $status): Response
    {
        $httpCode = match ($status) {
            'used' => 410,       // Gone
            'expired' => 410,    // Gone
            default => 404,      // Not Found
        };

        return response()->view('pages.invitation.error', [
            'status' => $status,
        ], $httpCode);
    }
}
