<?php

namespace App\Filament\Resources\LegalDocumentTemplateResource\Pages;

use App\Filament\Resources\LegalDocumentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLegalDocumentTemplate extends EditRecord
{
    protected static string $resource = LegalDocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        if (empty($data['is_published'])) {
            $data['published_at'] = null;
        }
        return $data;
    }
}
