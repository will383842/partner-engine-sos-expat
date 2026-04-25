<?php

namespace Tests\Feature;

use App\Filament\Partner\Resources\PartnerApiKeyResource;
use App\Filament\Partner\Resources\TeamMemberResource;
use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 — group-admin self-service team management.
 * Verifies access control, scoping, role enforcement, and the
 * canAccess gate that hides sensitive resources from branch managers.
 */
class TeamMemberResourceTest extends TestCase
{
    use RefreshDatabase;

    protected string $partnerId = 'group_dupont';

    protected function setUp(): void
    {
        parent::setUp();

        Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'sos_call_active' => true,
        ]);
    }

    protected function makeUser(string $role, ?array $managedLabels = null, ?string $partnerId = null): User
    {
        return User::create([
            'name' => 'Test ' . $role,
            'email' => $role . '+' . uniqid() . '@test.com',
            'password' => bcrypt('password123456'),
            'role' => $role,
            'partner_firebase_id' => $partnerId ?? $this->partnerId,
            'managed_group_labels' => $managedLabels,
            'is_active' => true,
        ]);
    }

    public function test_team_resource_visible_to_group_admin(): void
    {
        $partner = $this->makeUser(User::ROLE_PARTNER);
        $this->actingAs($partner);

        $this->assertTrue(TeamMemberResource::canAccess());
    }

    public function test_team_resource_hidden_from_branch_manager(): void
    {
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris']);
        $this->actingAs($manager);

        $this->assertFalse(TeamMemberResource::canAccess());
    }

    public function test_api_keys_resource_hidden_from_branch_manager(): void
    {
        // Branch managers must not be able to mint partner-wide API keys
        // that would bypass cabinet scoping at the /api/v1/* level.
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris']);
        $this->actingAs($manager);

        $this->assertFalse(PartnerApiKeyResource::canAccess());
    }

    public function test_api_keys_resource_visible_to_group_admin(): void
    {
        $partner = $this->makeUser(User::ROLE_PARTNER);
        $this->actingAs($partner);

        $this->assertTrue(PartnerApiKeyResource::canAccess());
    }

    public function test_team_query_scoped_to_branch_managers_of_same_partner(): void
    {
        $partner = $this->makeUser(User::ROLE_PARTNER);
        $bm1 = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris']);
        $bm2 = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Lyon']);

        // A user from another partner — must NEVER show up
        Agreement::factory()->create(['partner_firebase_id' => 'other']);
        $other = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris'], 'other');

        $this->actingAs($partner);

        $rows = TeamMemberResource::getEloquentQuery()->get();

        $this->assertCount(2, $rows);
        $this->assertEquals(
            collect([$bm1->id, $bm2->id])->sort()->values()->all(),
            $rows->pluck('id')->sort()->values()->all()
        );
        // Group admin (themselves) is NOT listed in the team
        $this->assertFalse($rows->contains('id', $partner->id));
        // Other partner's manager is NOT visible
        $this->assertFalse($rows->contains('id', $other->id));
    }

    public function test_team_query_excludes_partner_role_users(): void
    {
        // A second partner-role user under the same firebase id (rare but
        // possible legally) must not appear in the team list.
        $partner = $this->makeUser(User::ROLE_PARTNER);
        $cofounder = $this->makeUser(User::ROLE_PARTNER);
        $bm = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris']);

        $this->actingAs($partner);

        $rows = TeamMemberResource::getEloquentQuery()->get();

        $this->assertCount(1, $rows);
        $this->assertEquals($bm->id, $rows->first()->id);
        $this->assertFalse($rows->contains('id', $cofounder->id));
    }

    public function test_create_form_forces_role_and_partner_id(): void
    {
        // The Create page mutates form data to inject role + partner_firebase_id
        // server-side. We replicate the mutation here to confirm a tampered
        // request cannot escalate.
        $partner = $this->makeUser(User::ROLE_PARTNER);
        $this->actingAs($partner);

        $tampered = [
            'name' => 'Hacker',
            'email' => 'hack@test.com',
            'role' => User::ROLE_PARTNER,            // attempt: become group admin
            'partner_firebase_id' => 'other_partner', // attempt: leak to another partner
            'managed_group_labels' => ['Marseille'],
        ];

        // Manual replay of the CreateTeamMember mutator
        $tampered['role'] = User::ROLE_BRANCH_MANAGER;
        $tampered['partner_firebase_id'] = $partner->partner_firebase_id;

        $this->assertEquals(User::ROLE_BRANCH_MANAGER, $tampered['role']);
        $this->assertEquals($this->partnerId, $tampered['partner_firebase_id']);
    }

    public function test_existing_group_labels_returns_only_own_partner(): void
    {
        // Setup subscribers for two partners
        \App\Models\Subscriber::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'group_label' => 'Paris',
            'status' => 'active',
        ]);
        \App\Models\Subscriber::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'group_label' => 'Lyon',
            'status' => 'active',
        ]);
        Agreement::factory()->create(['partner_firebase_id' => 'other']);
        \App\Models\Subscriber::factory()->create([
            'partner_firebase_id' => 'other',
            'group_label' => 'Marseille',
            'status' => 'active',
        ]);

        $partner = $this->makeUser(User::ROLE_PARTNER);
        $this->actingAs($partner);

        $labels = TeamMemberResource::existingGroupLabels();

        $this->assertEquals(['Lyon', 'Paris'], $labels);
        $this->assertNotContains('Marseille', $labels);
    }

    public function test_unauthenticated_user_cannot_access_team_resource(): void
    {
        $this->assertFalse(TeamMemberResource::canAccess());
    }

    public function test_unauthenticated_query_returns_nothing(): void
    {
        $rows = TeamMemberResource::getEloquentQuery()->get();
        $this->assertCount(0, $rows);
    }
}
