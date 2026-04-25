<?php

namespace App\Filament\Partner\Resources\SubscriberResource\Pages;

use App\Filament\Partner\Resources\SubscriberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubscriber extends EditRecord
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Hook the Resource-level guard so a branch_manager cannot edit a subscriber
     * to flip its group_label outside their managed cabinets.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SubscriberResource::mutateFormDataBeforeSave($data);
    }
}
