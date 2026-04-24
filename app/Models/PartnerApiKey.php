<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Server-to-server API key for partner automation.
 * See migration 2026_04_24_000006_create_partner_api_keys_table.php for design notes.
 */
class PartnerApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_firebase_id',
        'name',
        'prefix',
        'hashed_secret',
        'scopes',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['hashed_secret'];

    /**
     * Generate a new API key for a partner.
     * Returns ['key' => PartnerApiKey, 'plain' => string] — the plain value must
     * be shown ONCE to the user and never stored server-side.
     */
    public static function generate(
        string $partnerFirebaseId,
        string $name,
        string $environment = 'live',
        string $scopes = 'subscribers:write,subscribers:read,activity:read'
    ): array {
        $env = $environment === 'test' ? 'test' : 'live';
        // Plain token: pk_{env}_{28 random chars}
        $random = Str::random(28);
        $plain = "pk_{$env}_{$random}";
        $prefix = substr($plain, 0, 12); // pk_live_X7k2
        $key = static::create([
            'partner_firebase_id' => $partnerFirebaseId,
            'name' => $name,
            'prefix' => $prefix,
            'hashed_secret' => Hash::make($plain),
            'scopes' => $scopes,
        ]);

        return ['key' => $key, 'plain' => $plain];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = array_map('trim', explode(',', $this->scopes ?? ''));
        return in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }

    public function recordUsage(?string $ip = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }

    public function revoke(string $actorId): void
    {
        $this->update([
            'revoked_at' => now(),
            'revoked_by' => $actorId,
        ]);
    }

    /**
     * Find a valid, non-revoked API key matching the given plain token.
     * Returns null if no match, revoked, or hash mismatch.
     *
     * Optimization: we look up by prefix first (indexed), then verify the hash.
     */
    public static function findByPlainToken(string $plain): ?self
    {
        if (!preg_match('/^pk_(live|test)_[A-Za-z0-9]{20,}$/', $plain)) {
            return null;
        }
        $prefix = substr($plain, 0, 12);
        $candidates = static::where('prefix', $prefix)
            ->whereNull('revoked_at')
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($plain, $candidate->hashed_secret)) {
                return $candidate;
            }
        }
        return null;
    }
}
