<?php

namespace App\Filament\Resources\LegalDocumentTemplateResource\Pages;

use App\Filament\Resources\LegalDocumentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLegalDocumentTemplates extends ListRecords
{
    protected static string $resource = LegalDocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
