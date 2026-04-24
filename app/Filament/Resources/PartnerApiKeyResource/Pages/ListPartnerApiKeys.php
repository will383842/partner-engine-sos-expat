<?php

namespace App\Filament\Resources\PartnerApiKeyResource\Pages;

use App\Filament\Resources\PartnerApiKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartnerApiKeys extends ListRecords
{
    protected static string $resource = PartnerApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Générer une clé API'),
        ];
    }
}
