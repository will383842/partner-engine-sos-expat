<?php

namespace App\Filament\Partner\Resources\PartnerInvoiceResource\Pages;

use App\Filament\Partner\Resources\PartnerInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPartnerInvoice extends ViewRecord
{
    protected static string $resource = PartnerInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('Télécharger le PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn() => !empty($this->record->pdf_path))
                ->url(fn() => '/api/partner/sos-call/invoices/' . $this->record->id . '/pdf', shouldOpenInNewTab: true),
            Actions\Action::make('payOnline')
                ->label('Payer en ligne')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->visible(fn() => $this->record->status === 'pending' && !empty($this->record->stripe_hosted_url))
                ->url(fn() => $this->record->stripe_hosted_url, shouldOpenInNewTab: true),
        ];
    }
}
