<?php

namespace App\Services;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Firestore;

class FirebaseService
{
    protected Auth $auth;
    protected Firestore $firestore;

    public function __construct(Auth $auth, Firestore $firestore)
    {
        $this->auth = $auth;
        $this->firestore = $firestore;
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
     * Get a Firestore document.
     */
    public function getDocument(string $collection, string $documentId): ?array
    {
        $doc = $this->firestore->database()->collection($collection)->document($documentId)->snapshot();
        return $doc->exists() ? $doc->data() : null;
    }

    /**
     * Set a Firestore document (create or overwrite).
     */
    public function setDocument(string $collection, string $documentId, array $data): void
    {
        $this->firestore->database()->collection($collection)->document($documentId)->set($data, ['merge' => true]);
    }

    /**
     * Delete a Firestore document.
     */
    public function deleteDocument(string $collection, string $documentId): void
    {
        $this->firestore->database()->collection($collection)->document($documentId)->delete();
    }

    /**
     * Increment a numeric field atomically on a Firestore document.
     */
    public function incrementField(string $collection, string $documentId, string $field, int $value): void
    {
        $docRef = $this->firestore->database()->collection($collection)->document($documentId);
        $docRef->update([
            ['path' => $field, 'value' => \Google\Cloud\Firestore\FieldValue::increment($value)],
        ]);
    }
}
