@php $__lang = $vars['partner_legal_language'] ?? 'fr'; $__rtl = $__lang === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $__lang }}"@if($__rtl) dir="rtl"@endif>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 50px 45px 70px 45px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #1f2937; line-height: 1.55; }
        h1 { font-size: 20pt; color: #1e40af; margin: 0 0 6px 0; }
        h2 { font-size: 13pt; color: #1e3a8a; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 5px; margin-top: 22px; }
        h3 { font-size: 11.5pt; color: #1f2937; margin-top: 16px; margin-bottom: 4px; }
        p { margin: 6px 0; text-align: justify; }
        ul, ol { margin: 6px 0; padding-left: 22px; }
        li { margin: 3px 0; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 18px; }
        .meta { background: #eff6ff; padding: 10px 12px; border-radius: 4px; font-size: 9.5pt; color: #1e3a8a; margin: 14px 0; }
        .meta div { margin: 2px 0; }
        .clause { margin: 10px 0; }
        .footer-fixed { position: fixed; bottom: -50px; left: 0; right: 0; text-align: center; font-size: 8pt; color: #6b7280; }
        .important { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px 12px; margin: 12px 0; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10pt; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .signature-block { margin-top: 40px; border: 2px solid #1e40af; border-radius: 6px; padding: 16px; background: #f0f9ff; page-break-inside: avoid; }
        .signature-block h3 { color: #1e3a8a; margin-top: 0; }
        .signature-block table { margin: 8px 0; }
        .signature-block .hash { font-family: 'Courier New', monospace; font-size: 8.5pt; word-break: break-all; color: #6b7280; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $title }}</h1>
    <div style="font-size: 10pt; color: #6b7280;">
        {{ $vars['provider_legal_name'] }}
        @if($vars['provider_siret'] ?? null)
            &mdash; SIRET {{ $vars['provider_siret'] }}
        @endif
    </div>
</div>

<div class="meta">
    <div><strong>Partenaire :</strong> {{ $vars['partner_name'] ?? '—' }}</div>
    <div><strong>Identifiant accord :</strong> #{{ $vars['agreement_id'] ?? '—' }}</div>
    <div><strong>Document généré le :</strong> {{ $vars['generated_at_human'] ?? '—' }}</div>
    <div><strong>UUID document :</strong> {{ $vars['document_uuid'] ?? '—' }}</div>
</div>

{!! $body !!}

<div class="footer-fixed">
    {{ $title }} &mdash; {{ $vars['provider_legal_name'] }} &mdash; Page <span class="pagenum"></span>
</div>

</body>
</html>
