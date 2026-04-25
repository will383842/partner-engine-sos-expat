<?php

namespace App\Filament\Partner\Resources\PartnerApiKeyResource\Pages;

use App\Filament\Partner\Resources\PartnerApiKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartnerApiKeys extends ListRecords
{
    protected static string $resource = PartnerApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(fn() => __('panel.api_key.action_create')),
        ];
    }
}
