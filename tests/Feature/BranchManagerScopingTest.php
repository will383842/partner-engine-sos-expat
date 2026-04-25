<?php

namespace Tests\Feature;

use App\Filament\Partner\Resources\SubscriberResource;
use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 multi-cabinet support — verifies that:
 *   1. ROLE_PARTNER (group admin) sees ALL subscribers across all cabinets.
 *   2. ROLE_BRANCH_MANAGER sees ONLY subscribers whose group_label matches
 *      managed_group_labels.
 *   3. ROLE_BRANCH_MANAGER with no labels assigned sees nothing (fail-closed).
 *   4. The trait still fails closed if no user is authenticated.
 */
class BranchManagerScopingTest extends TestCase
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

        // 3 cabinets: Paris (2 subs), Lyon (3 subs), Marseille (1 sub)
        Subscriber::factory()->count(2)->create([
            'partner_firebase_id' => $this->partnerId,
            'group_label' => 'Paris',
            'status' => 'active',
        ]);
        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => $this->partnerId,
            'group_label' => 'Lyon',
            'status' => 'active',
        ]);
        Subscriber::factory()->count(1)->create([
            'partner_firebase_id' => $this->partnerId,
            'group_label' => 'Marseille',
            'status' => 'active',
        ]);
    }

    protected function makeUser(string $role, ?array $managedLabels): User
    {
        return User::create([
            'name' => 'Test ' . $role,
            'email' => $role . '+' . uniqid() . '@test.com',
            'password' => bcrypt('password123456'),
            'role' => $role,
            'partner_firebase_id' => $this->partnerId,
            'managed_group_labels' => $managedLabels,
            'is_active' => true,
        ]);
    }

    public function test_partner_role_sees_all_cabinets(): void
    {
        $partner = $this->makeUser(User::ROLE_PARTNER, null);
        $this->actingAs($partner);

        $count = SubscriberResource::getEloquentQuery()->count();

        // 2 + 3 + 1 = 6 subscribers across all cabinets
        $this->assertEquals(6, $count);
    }

    public function test_branch_manager_paris_sees_only_paris(): void
    {
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris']);
        $this->actingAs($manager);

        $subs = SubscriberResource::getEloquentQuery()->get();

        $this->assertCount(2, $subs);
        $this->assertEquals(['Paris', 'Paris'], $subs->pluck('group_label')->toArray());
    }

    public function test_branch_manager_multi_cabinet_sees_assigned_only(): void
    {
        // A regional manager covering Paris + Lyon (but NOT Marseille)
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris', 'Lyon']);
        $this->actingAs($manager);

        $count = SubscriberResource::getEloquentQuery()->count();

        // 2 (Paris) + 3 (Lyon) = 5
        $this->assertEquals(5, $count);

        // Confirm Marseille is NOT in the result set
        $labels = SubscriberResource::getEloquentQuery()->pluck('group_label')->unique();
        $this->assertFalse($labels->contains('Marseille'));
    }

    public function test_branch_manager_with_no_labels_sees_nothing(): void
    {
        // Edge case: branch_manager created but no cabinets assigned yet.
        // Must fail-closed (sees zero subscribers), never the full table.
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, []);
        $this->actingAs($manager);

        $count = SubscriberResource::getEloquentQuery()->count();
        $this->assertEquals(0, $count);
    }

    public function test_branch_manager_with_null_labels_sees_nothing(): void
    {
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, null);
        $this->actingAs($manager);

        $count = SubscriberResource::getEloquentQuery()->count();
        $this->assertEquals(0, $count);
    }

    public function test_unauthenticated_query_returns_nothing(): void
    {
        // No actingAs — global scope must still fail-closed
        $count = SubscriberResource::getEloquentQuery()->count();
        $this->assertEquals(0, $count);
    }

    public function test_branch_manager_can_access_partner_panel(): void
    {
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris']);

        $this->assertTrue($manager->isPartner());
        $this->assertTrue($manager->isBranchManager());
        $this->assertFalse($manager->hasFullPartnerAccess());
        $this->assertEquals(['Paris'], $manager->getManagedGroupLabels());
    }

    public function test_partner_has_full_access_helpers(): void
    {
        $partner = $this->makeUser(User::ROLE_PARTNER, null);

        $this->assertTrue($partner->isPartner());
        $this->assertFalse($partner->isBranchManager());
        $this->assertTrue($partner->hasFullPartnerAccess());
    }

    public function test_branch_manager_cannot_see_other_partners_subscribers(): void
    {
        // Setup another partner with their own cabinets
        Agreement::factory()->create([
            'partner_firebase_id' => 'other_partner',
            'sos_call_active' => true,
        ]);
        Subscriber::factory()->count(5)->create([
            'partner_firebase_id' => 'other_partner',
            'group_label' => 'Paris', // Same label as Dupont's Paris — should still NOT match
            'status' => 'active',
        ]);

        // Branch manager of Dupont group, scoped to "Paris"
        $manager = $this->makeUser(User::ROLE_BRANCH_MANAGER, ['Paris']);
        $this->actingAs($manager);

        $subs = SubscriberResource::getEloquentQuery()->get();

        // Should still see only Dupont's 2 Paris subs (not other_partner's 5 Paris subs)
        $this->assertCount(2, $subs);
        $this->assertEquals(
            [$this->partnerId, $this->partnerId],
            $subs->pluck('partner_firebase_id')->toArray()
        );
    }
}
