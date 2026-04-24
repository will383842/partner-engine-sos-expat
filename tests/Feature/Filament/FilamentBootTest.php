<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Basic smoke tests for the Filament admin console.
 *
 * These tests verify:
 *   - Filament routes are registered
 *   - Login page is accessible
 *   - Authenticated admin users can access the panel
 *   - Non-admin users are denied
 */
class FilamentBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_login_page_is_accessible(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    public function test_filament_admin_is_protected_by_auth(): void
    {
        $response = $this->get('/admin');
        // Should redirect to login (302) or return 401/403
        $this->assertContains($response->status(), [302, 401, 403]);
    }

    public function test_super_admin_can_access_dashboard(): void
    {
        $user = User::create([
            'name' => 'Super Admin Test',
            'email' => 'super@test.com',
            'password' => bcrypt('password123456'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin');
        // Should NOT redirect to login
        $this->assertNotEquals(302, $response->status());
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@test.com',
            'password' => bcrypt('password123456'),
            'role' => User::ROLE_ADMIN,
            'is_active' => false,
        ]);

        $this->assertFalse($user->canAccessFilament());
    }

    public function test_user_roles_work_correctly(): void
    {
        $superAdmin = User::create([
            'name' => 'Super', 'email' => 's@t.com', 'password' => bcrypt('test'),
            'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'a@t.com', 'password' => bcrypt('test'),
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
        $accountant = User::create([
            'name' => 'Acct', 'email' => 'c@t.com', 'password' => bcrypt('test'),
            'role' => User::ROLE_ACCOUNTANT, 'is_active' => true,
        ]);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->isAdmin()); // super_admin is also admin
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($accountant->isAccountant());
        $this->assertTrue($accountant->canMarkInvoicesPaid());
        $this->assertFalse($accountant->canManageUsers());
    }

    public function test_filament_resources_are_discoverable(): void
    {
        // Verify our resources are registered by checking routes
        $routes = app('router')->getRoutes();
        $routeUris = collect($routes)->map(fn($r) => $r->uri())->toArray();

        $this->assertContains('admin/partners', $routeUris);
        $this->assertContains('admin/subscribers', $routeUris);
        $this->assertContains('admin/partner-invoices', $routeUris);
        $this->assertContains('admin/email-templates', $routeUris);
        $this->assertContains('admin/audit-logs', $routeUris);
        $this->assertContains('admin/users', $routeUris);
    }
}
