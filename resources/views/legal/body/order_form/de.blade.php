<h2>1. Parteien</h2>
<table>
    <tr>
        <th style="width: 35%;">Anbieter (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            Estnische Gesellschaft mit beschränkter Haftung (Osaühing — OÜ)<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) Estnisches Handelsregister (Äriregister): {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) EU-USt-IdNr.: {{ $vars['provider_vat'] }}<br>@endif
            E-Mail: {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>Partner</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            Interne Kennung: {{ $vars['partner_firebase_id'] }}<br>
            Rechnungs-E-Mail: {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) Vertragsreferenz: {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. Vertragslaufzeit</h2>
<table>
    <tr>
        <th>Vertragsbeginn</th>
        <td>{{ $vars['starts_at'] }}</td>
    </tr>
    <tr>
        <th>Vertragsende</th>
        <td>{{ $vars['expires_at'] ?: 'Unbestimmte Dauer — kündbar mit 30 Tagen Frist' }}</td>
    </tr>
</table>

<h2>3. Geschäftsmodell</h2>
<p>Diese Bestellung wählt das folgende Geschäftsmodell:
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (B2B-Monatsabonnement)</strong> — der Partner zahlt eine Monatsgebühr,
    seine Abonnenten rufen kostenfrei an.
@elseif($vars['economic_model'] === 'commission')
    <strong>Provision pro Anruf</strong> — der Partner erhält eine Provision für jeden Anruf
    eines seiner geworbenen Nutzer.
@elseif($vars['economic_model'] === 'hybrid')
    <strong>Hybrid</strong> — Kombination aus Monatsabonnement und Provision.
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. SOS-Call-Preise</h2>
    <table>
        <tr>
            <th>Abrechnungswährung</th>
            <td><strong>{{ $vars['billing_currency'] }}</strong></td>
        </tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr>
                <th>Monatliche Grundgebühr</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr>
                <th>Pro aktivem Abonnent und Monat</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        <tr>
            <th>Zahlungsfrist</th>
            <td>{{ $vars['payment_terms_days'] }} Tage zum Monatsende</td>
        </tr>
        <tr>
            <th>Erlaubte Anrufarten</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') Nur Expat @break
                    @case('lawyer_only') Nur Anwalt @break
                    @default Expat und Anwalt
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>Preisstufen (Pauschale nach Abonnentenanzahl)</h3>
        <table>
            <tr><th>Aktive Abonnenten</th><th>Monatsabonnement</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'unbegrenzt' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', '.') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. Kontingente und Limits</h2>
<table>
    <tr>
        <th>Maximale Abonnentenzahl</th>
        <td>
            @if(($vars['max_subscribers'] ?? 0) > 0)
                {{ $vars['max_subscribers'] }}
            @else
                Unbegrenzt
            @endif
        </td>
    </tr>
    <tr>
        <th>Standard-Abonnement-Dauer</th>
        <td>
            @if(($vars['default_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['default_subscriber_duration_days'] }} Tage
            @else
                Dauerhaft (bis Vertragsende)
            @endif
        </td>
    </tr>
    <tr>
        <th>Maximale Abonnement-Dauer</th>
        <td>
            @if(($vars['max_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['max_subscriber_duration_days'] }} Tage
            @else
                Keine Begrenzung
            @endif
        </td>
    </tr>
</table>

<h2>6. Bezugnahme auf die AGB</h2>
<div class="important">
    Diese Bestellung unterliegt den <strong>SOS-Call B2B Allgemeinen Geschäftsbedingungen</strong>
    und der <strong>Auftragsverarbeitungsvereinbarung (DPA)</strong>, die als Anlage diesem Vertrag
    beigefügt und gemeinsam unterzeichnet sind, nach <strong>estnischem Recht</strong>. Im Falle
    eines Widerspruchs haben die Sonderbestimmungen dieser Bestellung Vorrang vor den allgemeinen
    AGB, mit Ausnahme der zwingenden Bestimmungen zum Schutz personenbezogener Daten
    (DSGVO + estnisches IKS), die in jedem Fall Vorrang haben.
</div>

@if(!empty($customClauses))
    <h2>7. Sonderbedingungen</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'Sonderbedingung' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
