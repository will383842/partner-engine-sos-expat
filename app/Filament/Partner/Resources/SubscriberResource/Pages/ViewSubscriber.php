<?php

namespace App\Filament\Partner\Resources\SubscriberResource\Pages;

use App\Filament\Partner\Resources\SubscriberResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriber extends ViewRecord
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
