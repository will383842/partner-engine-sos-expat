<?php

namespace App\Filament\Resources\PartnerInvoiceResource\Pages;

use App\Filament\Resources\PartnerInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartnerInvoices extends ListRecords
{
    protected static string $resource = PartnerInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
