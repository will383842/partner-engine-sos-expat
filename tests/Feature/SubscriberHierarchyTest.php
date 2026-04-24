<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\PartnerApiKey;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_can_have_hierarchy_fields(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'bigcorp']);
        $subscriber = Subscriber::create([
            'partner_firebase_id' => 'bigcorp',
            'agreement_id' => $agreement->id,
            'email' => 'alice@bigcorp.com',
            'status' => 'active',
            'invite_token' => str_repeat('a', 64),
            'group_label' => 'BigCorp Paris',
            'region' => 'Île-de-France',
            'department' => 'IT',
            'external_id' => 'CRM-12345',
        ]);

        $fresh = $subscriber->fresh();
        $this->assertEquals('BigCorp Paris', $fresh->group_label);
        $this->assertEquals('Île-de-France', $fresh->region);
        $this->assertEquals('IT', $fresh->department);
        $this->assertEquals('CRM-12345', $fresh->external_id);
    }

    public function test_api_creates_subscriber_with_hierarchy(): void
    {
        Agreement::factory()->create([
            'partner_firebase_id' => 'hier_api',
            'sos_call_active' => true,
            'partner_name' => 'HierCorp',
        ]);
        $gen = PartnerApiKey::generate('hier_api', 'test');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->postJson('/api/v1/partner/subscribers', [
                'email' => 'jane@hier.com',
                'group_label' => 'HierCorp Lyon',
                'region' => 'Rhône-Alpes',
                'department' => 'Sales',
                'external_id' => 'HR-789',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.group_label', 'HierCorp Lyon');
        $response->assertJsonPath('data.region', 'Rhône-Alpes');
        $response->assertJsonPath('data.department', 'Sales');
        $response->assertJsonPath('data.external_id', 'HR-789');
    }

    public function test_api_filters_by_group_label(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'filter_test']);
        Subscriber::factory()->create([
            'partner_firebase_id' => 'filter_test',
            'agreement_id' => $agreement->id,
            'email' => 'paris1@test.com',
            'group_label' => 'Paris',
        ]);
        Subscriber::factory()->create([
            'partner_firebase_id' => 'filter_test',
            'agreement_id' => $agreement->id,
            'email' => 'paris2@test.com',
            'group_label' => 'Paris',
        ]);
        Subscriber::factory()->create([
            'partner_firebase_id' => 'filter_test',
            'agreement_id' => $agreement->id,
            'email' => 'lyon@test.com',
            'group_label' => 'Lyon',
        ]);

        $gen = PartnerApiKey::generate('filter_test', 'filter');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->getJson('/api/v1/partner/subscribers?group_label=Paris');

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_hierarchy_endpoint_groups_by_dimension(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'hier_dash',
            'sos_call_active' => true,
        ]);

        $cabinets = ['Paris', 'Paris', 'Paris', 'Lyon', 'Lyon', 'Marseille'];
        foreach ($cabinets as $i => $cab) {
            Subscriber::factory()->create([
                'partner_firebase_id' => 'hier_dash',
                'agreement_id' => $agreement->id,
                'email' => "sub{$i}@hier.com",
                'sos_call_code' => 'HIE-2026-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'group_label' => $cab,
                'status' => 'active',
            ]);
        }

        // Auth via firebase.auth middleware - simulate with testing helper
        $this->app['request']->attributes->set('partner_firebase_id', 'hier_dash');

        // Directly call controller for simplicity (auth bypass via attributes)
        $req = \Illuminate\Http\Request::create('/api/partner/sos-call/activity/hierarchy', 'GET');
        $req->attributes->set('partner_firebase_id', 'hier_dash');
        $req->query->set('dimension', 'group_label');

        $controller = new \App\Http\Controllers\Partner\PartnerSosCallController();
        $response = $controller->hierarchy($req);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('group_label', $data['dimension']);
        $this->assertEquals(6, $data['total_subscribers']);

        $rows = collect($data['rows'])->keyBy('label');
        $this->assertEquals(3, $rows['Paris']['subscribers_total']);
        $this->assertEquals(2, $rows['Lyon']['subscribers_total']);
        $this->assertEquals(1, $rows['Marseille']['subscribers_total']);
    }

    public function test_hierarchy_endpoint_rejects_invalid_dimension(): void
    {
        $req = \Illuminate\Http\Request::create('/api/partner/sos-call/activity/hierarchy', 'GET');
        $req->attributes->set('partner_firebase_id', 'hier_x');
        $req->query->set('dimension', 'hackable_field');

        $controller = new \App\Http\Controllers\Partner\PartnerSosCallController();
        $response = $controller->hierarchy($req);
        $this->assertEquals(422, $response->getStatusCode());
    }
}
