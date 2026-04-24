<?php

namespace App\Filament\Resources\PartnerInvoiceResource\Pages;

use App\Filament\Resources\PartnerInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnerInvoice extends EditRecord
{
    protected static string $resource = PartnerInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
