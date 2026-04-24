<?php

namespace App\Filament\Partner\Resources\SubscriberResource\Pages;

use App\Filament\Partner\Resources\SubscriberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscribers extends ListRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ajouter un client'),
        ];
    }
}
