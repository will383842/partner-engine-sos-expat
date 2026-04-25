<div class="signature-block">
    <h3>Signature électronique &mdash; eIDAS click-wrap</h3>
    <p style="margin: 6px 0 10px 0;">
        Ce document a été signé électroniquement par acceptation explicite (click-wrap)
        conformément au règlement eIDAS (UE) n°910/2014.
        Les éléments ci-dessous constituent la preuve d'identité, d'horodatage
        et d'intégrité du document signé.
    </p>
    <table>
        <tr>
            <th>Signataire</th>
            <td>{{ $acceptance->accepted_by_name ?? '—' }} &lt;{{ $acceptance->accepted_by_email }}&gt;</td>
        </tr>
        <tr>
            <th>Date et heure (UTC)</th>
            <td>{{ $acceptance->accepted_at->format('Y-m-d H:i:s') }} UTC</td>
        </tr>
        <tr>
            <th>Adresse IP</th>
            <td>{{ $acceptance->acceptance_ip ?? '—' }}</td>
        </tr>
        <tr>
            <th>User-Agent</th>
            <td style="font-size: 9pt;">{{ $acceptance->acceptance_user_agent ?? '—' }}</td>
        </tr>
        <tr>
            <th>Méthode</th>
            <td>{{ $acceptance->signature_method }}</td>
        </tr>
        <tr>
            <th>Identifiant Firebase</th>
            <td>{{ $acceptance->accepted_by_firebase_id ?? '—' }}</td>
        </tr>
        <tr>
            <th>Document : version</th>
            <td>{{ $acceptance->document_version }}</td>
        </tr>
        <tr>
            <th>Document : empreinte SHA-256 (avant signature)</th>
            <td class="hash">{{ $acceptance->pdf_hash }}</td>
        </tr>
        <tr>
            <th>Identifiant d'acceptation</th>
            <td>#{{ $acceptance->id }}</td>
        </tr>
    </table>
    <p style="font-size: 8.5pt; color: #6b7280; margin-top: 10px;">
        Toute modification de ce document après signature est détectable par recalcul
        de l'empreinte SHA-256. Le partenaire reconnaît avoir lu et accepté l'intégralité
        du présent document avant de cliquer sur "J'accepte".
    </p>
</div>
