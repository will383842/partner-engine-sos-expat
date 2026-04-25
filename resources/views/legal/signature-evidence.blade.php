@props(['doc', 'acceptance'])
<div style="font-size: 0.95rem; color: #111827;">
    @if(!$acceptance)
        <p style="color: #b91c1c;">Aucune signature enregistrée pour ce document.</p>
    @else
        <table style="width: 100%; border-collapse: collapse;">
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">Document</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $doc->title }}</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">Type</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $doc->kind }}</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">Version template</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->document_version }}</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">Signataire</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">
                    {{ $acceptance->accepted_by_name ?? '—' }}
                    &lt;{{ $acceptance->accepted_by_email }}&gt;
                </td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">Date signature (UTC)</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->accepted_at->format('Y-m-d H:i:s') }} UTC</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">IP signataire</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->acceptance_ip ?? '—' }}</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">User-Agent</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb; font-size:0.85rem; color:#6b7280;">{{ $acceptance->acceptance_user_agent ?? '—' }}</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">Méthode</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">{{ $acceptance->signature_method }}</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">Hash document signé (SHA-256)</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb; font-family: monospace; font-size:0.8rem; word-break:break-all;">{{ $acceptance->signed_pdf_hash ?? $acceptance->pdf_hash }}</td></tr>
            <tr><th style="text-align:left; padding:6px 8px; background:#f3f4f6; border:1px solid #e5e7eb;">UUID acceptation</th>
                <td style="padding:6px 8px; border:1px solid #e5e7eb;">#{{ $acceptance->id }}</td></tr>
        </table>
    @endif
</div>
