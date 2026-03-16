<?php

namespace Tests\Unit\Services;

use App\Services\FirebaseService;
use Tests\TestCase;

/**
 * Tests for Firestore REST API encoding/decoding.
 * Uses reflection to test protected methods.
 */
class FirebaseEncodingTest extends TestCase
{
    private FirebaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a real instance (we're testing encoding, not API calls)
        $authMock = \Mockery::mock(\Kreait\Firebase\Contract\Auth::class);
        $this->service = new FirebaseService($authMock);
    }

    private function callProtected(string $method, ...$args): mixed
    {
        $ref = new \ReflectionMethod(FirebaseService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    public function test_encode_string(): void
    {
        $result = $this->callProtected('encodeValue', 'hello');
        $this->assertEquals(['stringValue' => 'hello'], $result);
    }

    public function test_encode_integer(): void
    {
        $result = $this->callProtected('encodeValue', 42);
        $this->assertEquals(['integerValue' => '42'], $result);
    }

    public function test_encode_float(): void
    {
        $result = $this->callProtected('encodeValue', 3.14);
        $this->assertEquals(['doubleValue' => 3.14], $result);
    }

    public function test_encode_boolean(): void
    {
        $result = $this->callProtected('encodeValue', true);
        $this->assertEquals(['booleanValue' => true], $result);
    }

    public function test_encode_null(): void
    {
        $result = $this->callProtected('encodeValue', null);
        $this->assertEquals(['nullValue' => null], $result);
    }

    public function test_encode_array_list(): void
    {
        $result = $this->callProtected('encodeValue', ['a', 'b', 'c']);
        $this->assertArrayHasKey('arrayValue', $result);
        $this->assertCount(3, $result['arrayValue']['values']);
    }

    public function test_encode_associative_array_as_map(): void
    {
        $result = $this->callProtected('encodeValue', ['name' => 'test', 'count' => 5]);
        $this->assertArrayHasKey('mapValue', $result);
        $this->assertArrayHasKey('fields', $result['mapValue']);
    }

    public function test_encode_fields(): void
    {
        $result = $this->callProtected('encodeFirestoreFields', [
            'name' => 'Partner A',
            'balance' => 5000,
            'active' => true,
        ]);

        $this->assertEquals(['stringValue' => 'Partner A'], $result['name']);
        $this->assertEquals(['integerValue' => '5000'], $result['balance']);
        $this->assertEquals(['booleanValue' => true], $result['active']);
    }

    public function test_decode_document(): void
    {
        $firestoreDoc = [
            'fields' => [
                'name' => ['stringValue' => 'Test'],
                'count' => ['integerValue' => '42'],
                'rate' => ['doubleValue' => 3.14],
                'active' => ['booleanValue' => true],
                'nothing' => ['nullValue' => null],
            ],
        ];

        $result = $this->callProtected('decodeFirestoreDocument', $firestoreDoc);

        $this->assertEquals('Test', $result['name']);
        $this->assertEquals(42, $result['count']);
        $this->assertEquals(3.14, $result['rate']);
        $this->assertTrue($result['active']);
        $this->assertNull($result['nothing']);
    }

    public function test_decode_empty_document(): void
    {
        $result = $this->callProtected('decodeFirestoreDocument', []);
        $this->assertEquals([], $result);
    }

    public function test_decode_array_value(): void
    {
        $firestoreDoc = [
            'fields' => [
                'tags' => ['arrayValue' => ['values' => [
                    ['stringValue' => 'vip'],
                    ['stringValue' => 'europe'],
                ]]],
            ],
        ];

        $result = $this->callProtected('decodeFirestoreDocument', $firestoreDoc);
        $this->assertEquals(['vip', 'europe'], $result['tags']);
    }

    public function test_decode_map_value(): void
    {
        $firestoreDoc = [
            'fields' => [
                'config' => ['mapValue' => ['fields' => [
                    'enabled' => ['booleanValue' => true],
                    'rate' => ['integerValue' => '500'],
                ]]],
            ],
        ];

        $result = $this->callProtected('decodeFirestoreDocument', $firestoreDoc);
        $this->assertEquals(['enabled' => true, 'rate' => 500], $result['config']);
    }

    public function test_decode_timestamp_value(): void
    {
        $firestoreDoc = [
            'fields' => [
                'createdAt' => ['timestampValue' => '2026-03-16T12:00:00Z'],
            ],
        ];

        $result = $this->callProtected('decodeFirestoreDocument', $firestoreDoc);
        $this->assertEquals('2026-03-16T12:00:00Z', $result['createdAt']);
    }

    public function test_roundtrip_encode_decode(): void
    {
        $original = [
            'name' => 'Test Partner',
            'balance' => 15000,
            'rate' => 10.5,
            'active' => true,
            'tags' => ['vip', 'premium'],
        ];

        $encoded = $this->callProtected('encodeFirestoreFields', $original);
        $decoded = $this->callProtected('decodeFirestoreDocument', ['fields' => $encoded]);

        $this->assertEquals($original['name'], $decoded['name']);
        $this->assertEquals($original['balance'], $decoded['balance']);
        $this->assertEquals($original['rate'], $decoded['rate']);
        $this->assertEquals($original['active'], $decoded['active']);
        $this->assertEquals($original['tags'], $decoded['tags']);
    }
}
