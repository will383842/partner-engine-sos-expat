<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Versioned global template for B2B legal documents (CGV, DPA, order-form boilerplate).
 *
 * Use latestPublished(kind, language) to retrieve the version that should be applied
 * when generating a new partner_legal_document.
 */
class LegalDocumentTemplate extends Model
{
    use HasFactory;

    public const KIND_CGV_B2B = 'cgv_b2b';
    public const KIND_DPA = 'dpa';
    public const KIND_ORDER_FORM = 'order_form';

    public const KINDS = [
        self::KIND_CGV_B2B,
        self::KIND_DPA,
        self::KIND_ORDER_FORM,
    ];

    protected $fillable = [
        'kind',
        'language',
        'version',
        'title',
        'body_html',
        'variables',
        'published_at',
        'is_published',
        'published_by',
        'change_notes',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeForKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopeForLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Get the latest published template for a given (kind, language).
     * Falls back to French if requested language has no published version.
     */
    public static function latestPublished(string $kind, string $language = 'fr'): ?self
    {
        $template = self::published()
            ->forKind($kind)
            ->forLanguage($language)
            ->orderByDesc('published_at')
            ->first();

        if ($template || $language === 'fr') {
            return $template;
        }

        return self::published()
            ->forKind($kind)
            ->forLanguage('fr')
            ->orderByDesc('published_at')
            ->first();
    }
}
