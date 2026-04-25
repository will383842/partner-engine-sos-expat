<?php

namespace App\Filament\Partner\Resources\TeamMemberResource\Pages;

use App\Filament\Partner\Resources\TeamMemberResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateTeamMember extends CreateRecord
{
    protected static string $resource = TeamMemberResource::class;

    /**
     * Force the role and partner_firebase_id server-side. The form has no
     * fields for these — never trust client input. Without this, a tampered
     * request could create a `partner` (group admin) under another partner.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if (!($user instanceof User) || !$user->hasFullPartnerAccess() || empty($user->partner_firebase_id)) {
            abort(403);
        }

        $data['role'] = User::ROLE_BRANCH_MANAGER;
        $data['partner_firebase_id'] = $user->partner_firebase_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
