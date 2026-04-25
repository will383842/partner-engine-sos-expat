@php
    $isEn = ($language ?? 'fr') === 'en';
@endphp
<!DOCTYPE html>
<html lang="{{ $language }}">
<head><meta charset="UTF-8"><title>{{ $isEn ? 'Signed copy' : 'Copie signée' }} — {{ $document->title }}</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 24px auto;">

<h2 style="color: #047857;">
    @if($isEn) Document signed successfully @else Document signé avec succès @endif
</h2>

<p>
    @if($isEn)
        Hello {{ $partnerName ?: $acceptance->accepted_by_name ?: $acceptance->accepted_by_email }},<br>
        Your signature has been securely recorded for the following document:
    @else
        Bonjour {{ $partnerName ?: $acceptance->accepted_by_name ?: $acceptance->accepted_by_email }},<br>
        Votre signature a été enregistrée de façon sécurisée pour le document suivant :
    @endif
</p>

<div style="background:#f0f9ff; padding:14px 18px; border-left:4px solid #047857; border-radius:4px; margin: 16px 0;">
    <strong>{{ $document->title }}</strong><br>
    {{ $isEn ? 'Version' : 'Version' }} {{ $acceptance->document_version }} —
    {{ $isEn ? 'signed on' : 'signé le' }}
    {{ $acceptance->accepted_at->format('d/m/Y H:i:s') }} UTC
</div>

<h3 style="color:#1e40af; margin-top:24px;">
    @if($isEn) Electronic signature evidence (eIDAS) @else Preuve de signature électronique (eIDAS) @endif
</h3>
<table style="width:100%; border-collapse:collapse; font-size:0.92em;">
    <tr><td style="padding:6px 8px; background:#f9fafb; border:1px solid #e5e7eb;">{{ $isEn ? 'Signer' : 'Signataire' }}</td>
        <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->accepted_by_name ?? '—' }} &lt;{{ $acceptance->accepted_by_email }}&gt;</td></tr>
    <tr><td style="padding:6px 8px; background:#f9fafb; border:1px solid #e5e7eb;">{{ $isEn ? 'Date (UTC)' : 'Date (UTC)' }}</td>
        <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->accepted_at->format('Y-m-d H:i:s') }} UTC</td></tr>
    <tr><td style="padding:6px 8px; background:#f9fafb; border:1px solid #e5e7eb;">IP</td>
        <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->acceptance_ip }}</td></tr>
    <tr><td style="padding:6px 8px; background:#f9fafb; border:1px solid #e5e7eb;">{{ $isEn ? 'Method' : 'Méthode' }}</td>
        <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->signature_method }}</td></tr>
    <tr><td style="padding:6px 8px; background:#f9fafb; border:1px solid #e5e7eb;">{{ $isEn ? 'Document SHA-256' : 'Empreinte SHA-256' }}</td>
        <td style="padding:6px 8px; border:1px solid #e5e7eb; font-family:monospace; font-size:0.78em; word-break:break-all;">
            {{ $acceptance->signed_pdf_hash ?? $acceptance->pdf_hash }}
        </td></tr>
    <tr><td style="padding:6px 8px; background:#f9fafb; border:1px solid #e5e7eb;">{{ $isEn ? 'Acceptance ID' : 'ID acceptation' }}</td>
        <td style="padding:6px 8px; border:1px solid #e5e7eb;">#{{ $acceptance->id }}</td></tr>
</table>

<p style="margin-top:20px;">
    @if($isEn)
        The signed PDF is attached to this email. You can also re-download it at any time from your dashboard:
    @else
        Le PDF signé est joint à cet email. Vous pouvez également le retélécharger à tout moment depuis votre espace partenaire :
    @endif
</p>

<p style="text-align:center; margin: 20px 0;">
    <a href="{{ $dashboardUrl }}"
       style="background:#1e40af; color:white; padding:12px 24px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;">
        @if($isEn) Open my dashboard @else Ouvrir mon espace partenaire @endif
    </a>
</p>

<p style="font-size:0.85em; color:#9ca3af; margin-top:30px; border-top:1px solid #e5e7eb; padding-top:14px;">
    @if($isEn)
        Keep this email and the attached PDF for your records. The SHA-256 hash above
        proves the document has not been altered after signing.
    @else
        Conservez cet email et le PDF joint pour votre dossier. L'empreinte SHA-256 ci-dessus
        prouve que le document n'a pas été modifié après signature.
    @endif
</p>

</body>
</html>
