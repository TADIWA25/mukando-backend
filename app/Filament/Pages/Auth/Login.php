<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    /**
     * Override the default email field with a phone number field.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')   // key must still be 'email' so the parent form wiring works
            ->label('Phone Number')
            ->placeholder('e.g. 0771234567')
            ->tel()
            ->required()
            ->autocomplete('tel')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * Map the phone field value to the 'phone' database column for auth::attempt().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        // Normalize Zimbabwe numbers: 077... → 26377...
        $phone = preg_replace('/\D/', '', $data['email']);

        if (str_starts_with($phone, '0')) {
            $phone = '263' . substr($phone, 1);
        }

        return [
            'phone'    => $phone,
            'password' => $data['password'],
        ];
    }
}
