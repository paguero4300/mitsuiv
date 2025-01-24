<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    // Añadimos esta propiedad
    public bool $hasPasswordReset = true;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    // Añadimos este método
    public function hasPasswordReset(): bool
    {
        return $this->hasPasswordReset;
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Correo electrónico')
            ->email()
            ->required()
            ->prefixIcon('heroicon-o-envelope')
            ->placeholder('Correo electrónico')
            ->extraInputAttributes(['class' => 'w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->password()
            ->required()
            ->prefixIcon('heroicon-o-lock-closed')
            ->placeholder('Contraseña')
            ->extraInputAttributes(['class' => 'w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500']);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Recordarme')
            ->extraAttributes(['class' => 'h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded']);
    }
}
