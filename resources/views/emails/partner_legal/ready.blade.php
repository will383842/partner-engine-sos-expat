@php
    $isEn = ($language ?? 'fr') === 'en';
@endphp
<!DOCTYPE html>
<html lang="{{ $language }}">
<head><meta charset="UTF-8"><title>{{ $isEn ? 'Sign your partnership documents' : 'Signature de vos documents partenariat' }}</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 24px auto;">

<h2 style="color: #1e40af;">
    {{ $isEn ? 'Hello' : 'Bonjour' }} {{ $partnerName }},
</h2>

<p>
    @if($isEn)
        Your B2B partnership with SOS-Expat is ready to be activated. Before we can switch on
        SOS-Call for your subscribers, please review and electronically sign the following
        {{ count($documents) }} legal documents:
    @else
        Votre partenariat B2B avec SOS-Expat est prêt à être activé. Avant que SOS-Call ne soit
        accessible à vos abonnés, merci de relire et signer électroniquement les
        {{ count($documents) }} documents légaux suivants :
    @endif
</p>

<ul style="background:#eff6ff; padding:14px 24px; border-radius:6px; border-left:4px solid #1e40af;">
    @foreach($documents as $doc)
        <li><strong>{{ $doc->title }}</strong> ({{ $isEn ? 'version' : 'version' }} {{ $doc->template_version ?: 'na' }})</li>
    @endforeach
</ul>

<p style="text-align:center; margin: 28px 0;">
    <a href="{{ $dashboardUrl }}"
       style="background:#1e40af; color:white; padding:14px 28px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;">
        @if($isEn) Sign documents @else Signer les documents @endif
    </a>
</p>

<p style="font-size:0.9em; color:#6b7280;">
    @if($isEn)
        The signature is electronic and recorded with full eIDAS-compliant evidence
        (timestamp, IP, document hash). You will receive a signed copy by email immediately
        after each signature.
    @else
        La signature est électronique et enregistrée avec une preuve complète conforme eIDAS
        (horodatage, IP, empreinte du document). Vous recevrez une copie signée par email
        immédiatement après chaque signature.
    @endif
</p>

<p style="font-size:0.85em; color:#9ca3af; margin-top:30px; border-top:1px solid #e5e7eb; padding-top:14px;">
    @if($isEn)
        Questions? Reply to this email or contact us at
    @else
        Une question ? Répondez à cet email ou contactez-nous à
    @endif
    <a href="mailto:{{ config('legal.provider_email') }}">{{ config('legal.provider_email') }}</a>
</p>

</body>
</html>
