<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('users.form.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('users.form.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_admin')
                    ->label(__('users.form.is_admin'))
                    ->helperText(__('users.form.is_admin_help')),
            ]);
    }
}
