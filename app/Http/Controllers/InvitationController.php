<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    /**
     * Show the set-password form for an invitation token.
     */
    public function show(string $token): View
    {
        $user = $this->resolveUser($token);

        return view('pages.invitation.accept', ['user' => $user, 'token' => $token]);
    }

    /**
     * Accept the invitation: validate password, persist, log the user in,
     * redirect to the Coach.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $this->resolveUser($token);

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

        return redirect('/');
    }

    protected function resolveUser(string $token): User
    {
        $user = User::where('invitation_token', $token)->first();

        if (! $user) {
            abort(404, 'Convite inválido ou já utilizado.');
        }

        // Invitations expire after 7 days.
        if ($user->invited_at && $user->invited_at->lt(now()->subDays(7))) {
            abort(410, 'Convite expirado. Peça um novo pro admin.');
        }

        return $user;
    }
}
