<?php

namespace Tests\Unit\Models;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Tests\TestCase;

class SubscriberModelTest extends TestCase
{
    public function test_full_name_attribute(): void
    {
        $subscriber = Subscriber::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);

        $this->assertEquals('Jean Dupont', $subscriber->full_name);
    }

    public function test_full_name_with_missing_parts(): void
    {
        $subscriber = Subscriber::factory()->create([
            'first_name' => null,
            'last_name' => 'Dupont',
        ]);

        $this->assertEquals('Dupont', $subscriber->full_name);
    }

    public function test_belongs_to_agreement(): void
    {
        $agreement = Agreement::factory()->create();
        $subscriber = Subscriber::factory()->forAgreement($agreement)->create();

        $this->assertEquals($agreement->id, $subscriber->agreement->id);
    }

    public function test_has_many_activities(): void
    {
        $subscriber = Subscriber::factory()->create();
        SubscriberActivity::factory()->count(3)->create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $subscriber->partner_firebase_id,
        ]);

        $this->assertCount(3, $subscriber->activities);
    }

    public function test_soft_delete(): void
    {
        $subscriber = Subscriber::factory()->create();
        $subscriber->delete();

        $this->assertSoftDeleted('subscribers', ['id' => $subscriber->id]);
    }

    public function test_tags_cast_as_array(): void
    {
        $subscriber = Subscriber::factory()->create([
            'tags' => ['vip', 'europe'],
        ]);

        $subscriber->refresh();
        $this->assertIsArray($subscriber->tags);
        $this->assertContains('vip', $subscriber->tags);
    }

    public function test_custom_fields_cast_as_array(): void
    {
        $subscriber = Subscriber::factory()->create([
            'custom_fields' => ['company' => 'ACME'],
        ]);

        $subscriber->refresh();
        $this->assertIsArray($subscriber->custom_fields);
        $this->assertEquals('ACME', $subscriber->custom_fields['company']);
    }
}
