<?php

namespace App\Filament\Partner\Resources\TeamMemberResource\Pages;

use App\Filament\Partner\Resources\TeamMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeamMembers extends ListRecords
{
    protected static string $resource = TeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(fn() => __('panel.team.action_create')),
        ];
    }
}
