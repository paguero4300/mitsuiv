<?php

namespace App\Filament\Pages\Auth;

use Solutionforest\FilamentLoginScreen\Filament\Pages\Auth\Themes\Theme1\LoginScreenPage as BaseLoginPage;

class LoginPage extends BaseLoginPage
{
    protected function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'darkMode' => false,
        ];
    }
} 