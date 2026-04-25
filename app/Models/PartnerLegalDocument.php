<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Concrete legal document instance generated for a specific partner.
 *
 * Status flow:
 *   draft → pending_admin_validation → ready_for_signature → signed
 *
 * 'superseded' is a final state set when a newer version replaces this one.
 *
 * The rendered_html column stores the FROZEN content at generation time;
 * later edits to the global template do NOT mutate this row — instead a new
 * partner_legal_document row is created and the old one is superseded.
 */
class PartnerLegalDocument extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_VALIDATION = 'pending_admin_validation';
    public const STATUS_READY_FOR_SIGNATURE = 'ready_for_signature';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_VALIDATION,
        self::STATUS_READY_FOR_SIGNATURE,
        self::STATUS_SIGNED,
        self::STATUS_SUPERSEDED,
    ];

    protected $fillable = [
        'agreement_id',
        'partner_firebase_id',
        'kind',
        'language',
        'template_id',
        'template_version',
        'title',
        'rendered_html',
        'custom_clauses',
        'rendered_variables',
        'status',
        'pdf_path',
        'pdf_hash',
        'generated_at',
        'admin_validated_at',
        'admin_validated_by',
        'sent_for_signature_at',
        'superseded_by_id',
    ];

    protected $casts = [
        'custom_clauses' => 'array',
        'rendered_variables' => 'array',
        'generated_at' => 'datetime',
        'admin_validated_at' => 'datetime',
        'sent_for_signature_at' => 'datetime',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentTemplate::class, 'template_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(PartnerLegalAcceptance::class);
    }

    public function latestAcceptance(): HasOne
    {
        return $this->hasOne(PartnerLegalAcceptance::class)->latestOfMany('accepted_at');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    // --- Scopes ---

    public function scopeForAgreement(Builder $q, int $agreementId): Builder
    {
        return $q->where('agreement_id', $agreementId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_SUPERSEDED);
    }

    public function scopeReadyForSignature(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_READY_FOR_SIGNATURE);
    }

    public function scopeSigned(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_SIGNED);
    }

    public function scopeForPartner(Builder $q, string $firebaseId): Builder
    {
        return $q->where('partner_firebase_id', $firebaseId);
    }

    // --- Helpers ---

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    public function isReadyForSignature(): bool
    {
        return $this->status === self::STATUS_READY_FOR_SIGNATURE;
    }

    public function canBeSignedByPartner(): bool
    {
        return $this->status === self::STATUS_READY_FOR_SIGNATURE;
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_VALIDATION,
        ], true);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            LegalDocumentTemplate::KIND_CGV_B2B => __('admin.legal.kind_cgv_b2b'),
            LegalDocumentTemplate::KIND_DPA => __('admin.legal.kind_dpa'),
            LegalDocumentTemplate::KIND_ORDER_FORM => __('admin.legal.kind_order_form'),
            default => $this->kind,
        };
    }
}
