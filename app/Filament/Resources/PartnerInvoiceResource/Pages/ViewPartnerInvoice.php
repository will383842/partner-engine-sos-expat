<?php

namespace App\Filament\Resources\PartnerInvoiceResource\Pages;

use App\Filament\Resources\PartnerInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPartnerInvoice extends ViewRecord
{
    protected static string $resource = PartnerInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
