<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * eIDAS-recevable click-wrap signature record.
 *
 * Captures full evidence trail for one signature event:
 * who/when/where/what/how. Never mutated after creation — to revoke a
 * signature you create a new acceptance on a superseded document.
 */
class PartnerLegalAcceptance extends Model
{
    use HasFactory;

    public const METHOD_CLICK_WRAP = 'click_wrap';
    public const METHOD_YOUSIGN = 'yousign';
    public const METHOD_DOCUSIGN = 'docusign';
    public const METHOD_OFFLINE = 'offline_paper';

    protected $fillable = [
        'partner_legal_document_id',
        'agreement_id',
        'partner_firebase_id',
        'kind',
        'document_version',
        'pdf_hash',
        'pdf_path',
        'signed_pdf_path',
        'signed_pdf_hash',
        'accepted_at',
        'accepted_by_email',
        'accepted_by_name',
        'accepted_by_firebase_id',
        'acceptance_ip',
        'acceptance_user_agent',
        'signature_method',
        'external_signature_id',
        'external_signature_payload',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'external_signature_payload' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PartnerLegalDocument::class, 'partner_legal_document_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function scopeForPartner(Builder $q, string $firebaseId): Builder
    {
        return $q->where('partner_firebase_id', $firebaseId);
    }

    public function scopeForAgreement(Builder $q, int $agreementId): Builder
    {
        return $q->where('agreement_id', $agreementId);
    }

    public function scopeForKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }
}
