<?php

namespace App\Filament\Resources\PartnerApiKeyResource\Pages;

use App\Filament\Resources\PartnerApiKeyResource;
use App\Models\PartnerApiKey;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePartnerApiKey extends CreateRecord
{
    protected static string $resource = PartnerApiKeyResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $scopes = is_array($data['scopes_array'] ?? null)
            ? implode(',', $data['scopes_array'])
            : 'subscribers:write,subscribers:read,activity:read';

        $result = PartnerApiKey::generate(
            $data['partner_firebase_id'],
            $data['name'],
            $data['environment'] ?? 'live',
            $scopes
        );

        // Persist the plain value in the session so the next page can display it ONCE
        session()->flash('new_api_key_plain', $result['plain']);

        // Display via Filament notification as well (more visible)
        Notification::make()
            ->title('⚠️ Copiez cette clé MAINTENANT — elle ne sera plus jamais affichée')
            ->body($result['plain'])
            ->persistent()
            ->warning()
            ->send();

        return $result['key'];
    }
}
