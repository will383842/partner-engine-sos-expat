<?php

namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\CsvImport;
use App\Models\EmailTemplate;
use App\Models\PartnerMonthlyStat;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->actingAsAdmin();
    }

    // ── Partners List ────────────────────────────────────

    public function test_admin_list_partners(): void
    {
        Agreement::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/partners', $this->authHeaders());

        $response->assertStatus(200);
    }

    public function test_admin_partner_detail(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p1']);
        Subscriber::factory()->forAgreement($agreement)->count(5)->create();

        $response = $this->getJson('/api/admin/partners/p1', $this->authHeaders());

        $response->assertStatus(200);
    }

    // ── Global Stats ─────────────────────────────────────

    public function test_admin_global_stats(): void
    {
        Agreement::factory()->create(['status' => 'active']);
        Agreement::factory()->expired()->create();

        $response = $this->getJson('/api/admin/stats', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'active_partners',
                'total_subscribers',
                'calls_this_month',
                'revenue_this_month_cents',
            ]);
    }

    // ── Subscriber Admin ─────────────────────────────────

    public function test_admin_list_partner_subscribers(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_admin']);
        Subscriber::factory()->forAgreement($agreement)->count(3)->create();

        $response = $this->getJson('/api/admin/partners/p_admin/subscribers', $this->authHeaders());

        $response->assertStatus(200);
    }

    public function test_admin_suspend_subscriber(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_sus']);
        $subscriber = Subscriber::factory()->forAgreement($agreement)->active()->create();

        $response = $this->postJson(
            "/api/admin/partners/p_sus/subscribers/{$subscriber->id}/suspend",
            [],
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $subscriber->refresh();
        $this->assertEquals('suspended', $subscriber->status);
    }

    public function test_admin_reactivate_subscriber(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_react']);
        $subscriber = Subscriber::factory()->forAgreement($agreement)->suspended()->create();

        $response = $this->postJson(
            "/api/admin/partners/p_react/subscribers/{$subscriber->id}/reactivate",
            [],
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $subscriber->refresh();
        $this->assertEquals('active', $subscriber->status);
    }

    // ── CSV Imports ──────────────────────────────────────

    public function test_admin_list_csv_imports(): void
    {
        CsvImport::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/csv-imports', $this->authHeaders());

        $response->assertStatus(200);
    }

    public function test_admin_csv_import_detail(): void
    {
        $import = CsvImport::factory()->completed(imported: 10, errors: 2)->create();

        $response = $this->getJson("/api/admin/csv-imports/{$import->id}", $this->authHeaders());

        $response->assertStatus(200);
    }

    // ── Email Templates ──────────────────────────────────

    public function test_admin_list_email_templates(): void
    {
        EmailTemplate::factory()->create(['partner_firebase_id' => 'p_tpl']);

        $response = $this->getJson('/api/admin/partners/p_tpl/email-templates', $this->authHeaders());

        $response->assertStatus(200);
    }

    public function test_admin_update_email_template(): void
    {
        $response = $this->putJson('/api/admin/partners/p_tpl2/email-templates/invitation', [
            'subject' => 'Custom Subject {partnerName}',
            'body_html' => '<p>Custom body {firstName}</p>',
        ], $this->authHeaders());

        $response->assertStatus(200);

        $this->assertDatabaseHas('email_templates', [
            'partner_firebase_id' => 'p_tpl2',
            'type' => 'invitation',
            'subject' => 'Custom Subject {partnerName}',
        ]);
    }

    public function test_admin_delete_email_template(): void
    {
        EmailTemplate::factory()->create([
            'partner_firebase_id' => 'p_del',
            'type' => 'invitation',
        ]);

        $response = $this->deleteJson('/api/admin/partners/p_del/email-templates/invitation', [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('email_templates', [
            'partner_firebase_id' => 'p_del',
            'type' => 'invitation',
        ]);
    }

    // ── Audit Log ────────────────────────────────────────

    public function test_admin_audit_log(): void
    {
        AuditLog::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/audit-log', $this->authHeaders());

        $response->assertStatus(200);
    }

    public function test_admin_partner_audit_log(): void
    {
        AuditLog::factory()->count(3)->create([
            'actor_firebase_id' => 'p_audit',
        ]);

        $response = $this->getJson('/api/admin/partners/p_audit/audit-log', $this->authHeaders());

        $response->assertStatus(200);
    }

    // ── Bulk Delete ──────────────────────────────────────

    public function test_admin_bulk_delete_subscribers(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_bulk']);
        $subs = Subscriber::factory()->forAgreement($agreement)->count(3)->create();
        $ids = $subs->pluck('id')->toArray();

        $response = $this->deleteJson("/api/admin/partners/p_bulk/subscribers/bulk", [
            'subscriber_ids' => $ids,
        ], $this->authHeaders());

        $response->assertStatus(200);

        foreach ($ids as $id) {
            $this->assertSoftDeleted('subscribers', ['id' => $id]);
        }
    }
}
