<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

/**
 * Custom profile page for admin users.
 *
 * Adds a "2FA by email" toggle on top of the default email/password form,
 * letting each admin opt in / out of email-based two-factor authentication
 * for their own account.
 *
 * Wired via AdminPanelProvider::profile(isSimple: false). The standalone
 * partner panel keeps the simple Filament profile (no 2FA there for now).
 */
class EditAdminProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin.profile.identity_section'))
                    ->description(__('admin.profile.identity_section_desc'))
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ])
                    ->columns(2),

                Section::make(__('admin.profile.password_section'))
                    ->description(__('admin.profile.password_section_desc'))
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->columns(2),

                Section::make(__('admin.profile.security_section'))
                    ->description(__('admin.profile.security_section_desc'))
                    ->schema([
                        Toggle::make('two_factor_email_enabled')
                            ->label(__('admin.profile.two_factor_email_label'))
                            ->helperText(__('admin.profile.two_factor_email_help'))
                            ->onColor('success')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
