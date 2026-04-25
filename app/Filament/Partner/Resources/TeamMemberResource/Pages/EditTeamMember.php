<?php

namespace App\Filament\Partner\Resources\TeamMemberResource\Pages;

use App\Filament\Partner\Resources\TeamMemberResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeamMember extends EditRecord
{
    protected static string $resource = TeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalHeading(fn() => __('panel.team.delete_heading'))
                ->modalDescription(fn() => __('panel.team.delete_desc'))
                ->successNotificationTitle(fn() => __('panel.team.delete_done')),
        ];
    }

    /**
     * Defense-in-depth: even on edit, force the role and partner scope so
     * a crafted PATCH cannot escalate the team member to a group admin or
     * move them to another partner.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if (!($user instanceof User) || !$user->hasFullPartnerAccess() || empty($user->partner_firebase_id)) {
            abort(403);
        }

        $data['role'] = User::ROLE_BRANCH_MANAGER;
        $data['partner_firebase_id'] = $user->partner_firebase_id;

        return $data;
    }
}
