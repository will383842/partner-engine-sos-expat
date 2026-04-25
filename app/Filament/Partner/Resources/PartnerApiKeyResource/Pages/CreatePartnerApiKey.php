<?php

namespace App\Filament\Partner\Resources\PartnerApiKeyResource\Pages;

use App\Filament\Partner\Resources\PartnerApiKeyResource;
use App\Models\PartnerApiKey;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePartnerApiKey extends CreateRecord
{
    protected static string $resource = PartnerApiKeyResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Force the partner_firebase_id to the connected user's — never trust the form.
        $partnerFirebaseId = auth()->user()?->partner_firebase_id;
        if (!$partnerFirebaseId) {
            abort(403, 'Account not linked to a partner.');
        }

        $scopes = is_array($data['scopes_array'] ?? null) && !empty($data['scopes_array'])
            ? implode(',', $data['scopes_array'])
            : 'subscribers:read,activity:read';

        $result = PartnerApiKey::generate(
            $partnerFirebaseId,
            $data['name'],
            $data['environment'] ?? 'live',
            $scopes
        );

        // Show the plain token once, persistent so the partner has time to copy it.
        Notification::make()
            ->title(__('panel.api_key.created_title'))
            ->body(__('panel.api_key.created_body', ['token' => $result['plain']]))
            ->persistent()
            ->warning()
            ->send();

        return $result['key'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
