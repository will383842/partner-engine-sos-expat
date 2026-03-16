<?php

namespace App\Services;

use Kreait\Firebase\Contract\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Firebase service using kreait/firebase-php for Auth
 * and Firestore REST API for document operations (no gRPC dependency).
 */
class FirebaseService
{
    protected Auth $auth;
    protected string $projectId;
    protected ?string $accessToken = null;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        $this->projectId = config('firebase.projects.app.project_id', 'sos-urgently-ac307');
    }

    /**
     * Verify a Firebase ID token and return the decoded claims.
     */
    public function verifyIdToken(string $idToken): array
    {
        $verifiedToken = $this->auth->verifyIdToken($idToken);
        return [
            'uid' => $verifiedToken->claims()->get('sub'),
            'email' => $verifiedToken->claims()->get('email'),
        ];
    }

    /**
     * Get a Firestore document via REST API.
     */
    public function getDocument(string $collection, string $documentId): ?array
    {
        $url = $this->firestoreUrl("{$collection}/{$documentId}");

        $response = Http::withHeaders($this->authHeaders())
            ->get($url);

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->ok()) {
            Log::error('Firestore getDocument failed', [
                'collection' => $collection,
                'document' => $documentId,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $this->decodeFirestoreDocument($response->json());
    }

    /**
     * Set/merge a Firestore document via REST API.
     */
    public function setDocument(string $collection, string $documentId, array $data): void
    {
        $url = $this->firestoreUrl("{$collection}/{$documentId}");

        // Build update mask for merge behavior
        $fieldPaths = array_keys($data);
        $queryParams = array_map(fn($f) => "updateMask.fieldPaths={$f}", $fieldPaths);
        $url .= '?' . implode('&', $queryParams);

        $response = Http::withHeaders($this->authHeaders())
            ->patch($url, [
                'fields' => $this->encodeFirestoreFields($data),
            ]);

        if (!$response->ok()) {
            Log::error('Firestore setDocument failed', [
                'collection' => $collection,
                'document' => $documentId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception("Firestore write failed: {$response->status()}");
        }
    }

    /**
     * Delete a Firestore document via REST API.
     */
    public function deleteDocument(string $collection, string $documentId): void
    {
        $url = $this->firestoreUrl("{$collection}/{$documentId}");

        $response = Http::withHeaders($this->authHeaders())
            ->delete($url);

        if (!$response->ok() && $response->status() !== 404) {
            Log::error('Firestore deleteDocument failed', [
                'collection' => $collection,
                'document' => $documentId,
                'status' => $response->status(),
            ]);
        }
    }

    /**
     * Increment a numeric field atomically via Firestore commit API.
     */
    public function incrementField(string $collection, string $documentId, string $field, int $value): void
    {
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:commit";

        $docPath = "projects/{$this->projectId}/databases/(default)/documents/{$collection}/{$documentId}";

        $response = Http::withHeaders($this->authHeaders())
            ->post($url, [
                'writes' => [
                    [
                        'transform' => [
                            'document' => $docPath,
                            'fieldTransforms' => [
                                [
                                    'fieldPath' => $field,
                                    'increment' => ['integerValue' => (string) $value],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if (!$response->ok()) {
            Log::error('Firestore incrementField failed', [
                'collection' => $collection,
                'document' => $documentId,
                'field' => $field,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception("Firestore increment failed: {$response->status()}");
        }
    }

    /**
     * Build Firestore REST API URL.
     */
    protected function firestoreUrl(string $path): string
    {
        return "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$path}";
    }

    /**
     * Get auth headers with cached access token from service account.
     */
    protected function authHeaders(): array
    {
        $token = Cache::remember('firebase_access_token', 3500, function () {
            $credPath = config('firebase.projects.app.credentials');

            if (!$credPath || !file_exists($credPath)) {
                Log::warning('Firebase credentials not found, using empty token');
                return '';
            }

            $creds = json_decode(file_get_contents($credPath), true);

            // Build JWT for service account
            $now = time();
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = base64_encode(json_encode([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/firebase',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            openssl_sign("{$header}.{$payload}", $signature, $creds['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "{$header}.{$payload}." . base64_encode($signature);

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->ok()) {
                Log::error('Failed to get Firebase access token', ['status' => $response->status()]);
                return '';
            }

            return $response->json('access_token');
        });

        return [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Encode PHP values to Firestore REST API field format.
     */
    protected function encodeFirestoreFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->encodeValue($value);
        }
        return $fields;
    }

    protected function encodeValue(mixed $value): array
    {
        if (is_null($value)) {
            return ['nullValue' => null];
        }
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }
        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_string($value)) {
            return ['stringValue' => $value];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return ['arrayValue' => ['values' => array_map([$this, 'encodeValue'], $value)]];
            }
            return ['mapValue' => ['fields' => $this->encodeFirestoreFields($value)]];
        }
        return ['stringValue' => (string) $value];
    }

    /**
     * Decode a Firestore REST API document response to a PHP array.
     */
    protected function decodeFirestoreDocument(array $doc): array
    {
        if (!isset($doc['fields'])) {
            return [];
        }

        $result = [];
        foreach ($doc['fields'] as $key => $value) {
            $result[$key] = $this->decodeValue($value);
        }
        return $result;
    }

    protected function decodeValue(array $value): mixed
    {
        if (isset($value['stringValue'])) return $value['stringValue'];
        if (isset($value['integerValue'])) return (int) $value['integerValue'];
        if (isset($value['doubleValue'])) return (float) $value['doubleValue'];
        if (isset($value['booleanValue'])) return $value['booleanValue'];
        if (isset($value['nullValue'])) return null;
        if (isset($value['arrayValue']['values'])) {
            return array_map([$this, 'decodeValue'], $value['arrayValue']['values']);
        }
        if (isset($value['mapValue']['fields'])) {
            return $this->decodeFirestoreDocument(['fields' => $value['mapValue']['fields']]);
        }
        if (isset($value['timestampValue'])) return $value['timestampValue'];
        return null;
    }
}
