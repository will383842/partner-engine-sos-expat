<?php

namespace App\Filament\Partner\Resources\SubscriberResource\Pages;

use App\Filament\Partner\Resources\SubscriberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriber extends CreateRecord
{
    protected static string $resource = SubscriberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SubscriberResource::mutateFormDataBeforeCreate($data);
    }
}
