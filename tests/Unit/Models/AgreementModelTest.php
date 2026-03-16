<?php

namespace Tests\Unit\Models;

use App\Models\Agreement;
use App\Models\Subscriber;
use Tests\TestCase;

class AgreementModelTest extends TestCase
{
    public function test_is_active(): void
    {
        $agreement = Agreement::factory()->create(['status' => 'active']);
        $this->assertTrue($agreement->isActive());
        $this->assertFalse($agreement->isPaused());
        $this->assertFalse($agreement->isExpired());
    }

    public function test_is_paused(): void
    {
        $agreement = Agreement::factory()->paused()->create();
        $this->assertFalse($agreement->isActive());
        $this->assertTrue($agreement->isPaused());
    }

    public function test_is_expired(): void
    {
        $agreement = Agreement::factory()->expired()->create();
        $this->assertTrue($agreement->isExpired());
    }

    public function test_has_many_subscribers(): void
    {
        $agreement = Agreement::factory()->create();
        Subscriber::factory()->forAgreement($agreement)->count(3)->create();

        $this->assertCount(3, $agreement->subscribers);
    }

    public function test_soft_delete(): void
    {
        $agreement = Agreement::factory()->create();
        $agreement->delete();

        $this->assertSoftDeleted('agreements', ['id' => $agreement->id]);
        $this->assertNull(Agreement::find($agreement->id));
        $this->assertNotNull(Agreement::withTrashed()->find($agreement->id));
    }

    public function test_casts_dates(): void
    {
        $agreement = Agreement::factory()->create([
            'starts_at' => '2026-01-01',
            'expires_at' => '2027-01-01',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $agreement->starts_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $agreement->expires_at);
    }

    public function test_casts_integers(): void
    {
        $agreement = Agreement::factory()->create([
            'commission_per_call_lawyer' => '500',
            'commission_per_call_expat' => '300',
            'discount_value' => '300',
        ]);

        $this->assertIsInt($agreement->commission_per_call_lawyer);
        $this->assertIsInt($agreement->commission_per_call_expat);
        $this->assertIsInt($agreement->discount_value);
    }
}
