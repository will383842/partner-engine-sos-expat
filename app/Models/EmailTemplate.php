<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Email template with multilingual support.
 *
 * Lookup priority:
 * 1. Partner-specific + language match
 * 2. Partner-specific + fallback to 'fr'
 * 3. Global default (partner_firebase_id=NULL) + language match
 * 4. Global default + fallback to 'fr'
 * 5. Hard-coded Blade fallback
 */
class EmailTemplate extends Model
{
    use HasFactory;

    public const TYPE_INVITATION = 'invitation';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_EXPIRATION = 'expiration';
    public const TYPE_SOS_CALL_ACTIVATION = 'sos_call_activation';
    public const TYPE_MONTHLY_INVOICE = 'monthly_invoice';
    public const TYPE_INVOICE_OVERDUE = 'invoice_overdue';
    public const TYPE_SUBSCRIBER_MAGIC_LINK = 'subscriber_magic_link';

    public const SUPPORTED_LANGUAGES = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'zh', 'ru', 'hi'];

    protected $fillable = [
        'partner_firebase_id',
        'type',
        'language',
        'subject',
        'body_html',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeForPartner(Builder $query, ?string $partnerFirebaseId): Builder
    {
        return $partnerFirebaseId
            ? $query->where('partner_firebase_id', $partnerFirebaseId)
            : $query->whereNull('partner_firebase_id');
    }

    /**
     * Resolve the best matching template for a partner+type+language.
     * Implements fallback chain: partner+lang → partner+fr → global+lang → global+fr.
     */
    public static function resolve(
        string $type,
        string $language = 'fr',
        ?string $partnerFirebaseId = null
    ): ?self {
        if (!in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            $language = 'fr';
        }

        // 1. Partner-specific + language match
        if ($partnerFirebaseId) {
            $template = static::active()
                ->forType($type)
                ->forPartner($partnerFirebaseId)
                ->forLanguage($language)
                ->first();
            if ($template) {
                return $template;
            }

            // 2. Partner-specific + French fallback
            if ($language !== 'fr') {
                $template = static::active()
                    ->forType($type)
                    ->forPartner($partnerFirebaseId)
                    ->forLanguage('fr')
                    ->first();
                if ($template) {
                    return $template;
                }
            }
        }

        // 3. Global default + language match
        $template = static::active()
            ->forType($type)
            ->forPartner(null)
            ->forLanguage($language)
            ->first();
        if ($template) {
            return $template;
        }

        // 4. Global default + French fallback
        if ($language !== 'fr') {
            return static::active()
                ->forType($type)
                ->forPartner(null)
                ->forLanguage('fr')
                ->first();
        }

        return null;
    }
}
