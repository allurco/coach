<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Mail\UserInvitation;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Override creation: build the user with an invitation token and email it
     * the accept-invite link instead of asking the admin for a password.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => null,
            'is_admin' => $data['is_admin'] ?? false,
            'invitation_token' => Str::random(64),
            'invited_at' => now(),
        ]);

        Mail::to($user->email)->send(new UserInvitation(
            user: $user,
            acceptUrl: route('invitation.show', $user->invitation_token),
            invitedByName: auth()->user()?->name,
        ));

        Notification::make()
            ->title(__('users.notifications.invite_sent_title'))
            ->body(__('users.notifications.invite_sent_body', ['email' => $user->email]))
            ->success()
            ->send();

        return $user;
    }
}
