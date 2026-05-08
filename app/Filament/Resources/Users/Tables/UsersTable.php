<?php

namespace App\Filament\Resources\Users\Tables;

use App\Mail\UserInvitation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('users.table.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('users.table.email'))
                    ->searchable()
                    ->copyable()
                    ->copyMessage(__('users.table.email_copied')),
                IconColumn::make('is_admin')
                    ->label(__('users.table.is_admin'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('users.table.status'))
                    ->state(fn (User $u) => match (true) {
                        $u->isInvitationPending() => __('users.table.status_invited'),
                        $u->accepted_invitation_at !== null => __('users.table.status_active_invited'),
                        default => __('users.table.status_active'),
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === __('users.table.status_invited') ? 'warning' : 'success'),
                TextColumn::make('created_at')
                    ->label(__('users.table.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('resendInvite')
                    ->label(__('users.table.resend_invite'))
                    ->icon('heroicon-o-envelope')
                    ->visible(fn (User $u) => $u->isInvitationPending())
                    ->requiresConfirmation()
                    ->action(function (User $user) {
                        $user->forceFill([
                            'invitation_token' => Str::random(64),
                            'invited_at' => now(),
                        ])->save();

                        Mail::to($user->email)
                            ->locale($user->locale ?? config('app.locale'))
                            ->send(new UserInvitation(
                                user: $user,
                                acceptUrl: route('invitation.show', $user->invitation_token),
                                invitedByName: auth()->user()?->name,
                            ));

                        Notification::make()
                            ->title(__('users.notifications.invite_resent_title'))
                            ->body(__('users.notifications.invite_resent_body', ['email' => $user->email]))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
