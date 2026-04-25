<?php

namespace App\Filament\Resources\LegalDocumentTemplateResource\Pages;

use App\Filament\Resources\LegalDocumentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegalDocumentTemplate extends CreateRecord
{
    protected static string $resource = LegalDocumentTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        $data['published_by'] = $data['published_by'] ?? 'admin:filament';
        return $data;
    }
}
