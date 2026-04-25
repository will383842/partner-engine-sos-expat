<h2>1. Parties</h2>
<table>
    <tr>
        <th style="width: 35%;">Provider (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            Estonian private limited company (Osaühing — OÜ)<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) Estonian Business Register (Äriregister) code: {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) EU VAT number: {{ $vars['provider_vat'] }}<br>@endif
            Email: {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>Partner</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            Internal ID: {{ $vars['partner_firebase_id'] }}<br>
            Billing email: {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) Agreement reference: {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. Term</h2>
<table>
    <tr>
        <th>Effective date</th>
        <td>{{ $vars['starts_at'] }}</td>
    </tr>
    <tr>
        <th>Expiration date</th>
        <td>{{ $vars['expires_at'] ?: 'Indefinite term — terminable with 30 days notice' }}</td>
    </tr>
</table>

<h2>3. Economic model</h2>
<p>This order form selects the following economic model:
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (B2B monthly subscription)</strong> — the Partner pays a monthly
    subscription, its Subscribers call free of charge.
@elseif($vars['economic_model'] === 'commission')
    <strong>Per-call commission</strong> — the Partner receives a commission for each call
    made by one of its referred users.
@elseif($vars['economic_model'] === 'hybrid')
    <strong>Hybrid</strong> — combination of monthly subscription and commission.
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. SOS-Call pricing</h2>
    <table>
        <tr>
            <th>Billing currency</th>
            <td><strong>{{ $vars['billing_currency'] }}</strong></td>
        </tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr>
                <th>Fixed monthly fee</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr>
                <th>Per active subscriber per month</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        <tr>
            <th>Payment term</th>
            <td>{{ $vars['payment_terms_days'] }} days end of month</td>
        </tr>
        <tr>
            <th>Allowed call types</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') Expat only @break
                    @case('lawyer_only') Lawyer only @break
                    @default Expat and Lawyer
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>Pricing tiers (fixed fee by subscriber count)</h3>
        <table>
            <tr><th>Active subscribers</th><th>Monthly subscription</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'unlimited' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, '.', ',') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. Quotas and limits</h2>
<table>
    <tr>
        <th>Maximum number of subscribers</th>
        <td>
            @if(($vars['max_subscribers'] ?? 0) > 0)
                {{ $vars['max_subscribers'] }}
            @else
                Unlimited
            @endif
        </td>
    </tr>
    <tr>
        <th>Default subscription duration</th>
        <td>
            @if(($vars['default_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['default_subscriber_duration_days'] }} days
            @else
                Permanent (until contract expiration)
            @endif
        </td>
    </tr>
    <tr>
        <th>Maximum subscription duration</th>
        <td>
            @if(($vars['max_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['max_subscriber_duration_days'] }} days
            @else
                No cap
            @endif
        </td>
    </tr>
</table>

<h2>6. Reference to general terms</h2>
<div class="important">
    This order form is governed by the <strong>SOS-Call B2B Terms and Conditions of Sale</strong>
    and the <strong>Data Processing Agreement (DPA)</strong> annexed to and signed together with
    this contract, under <strong>Estonian law</strong>. In case of conflict, the special provisions
    of this order form prevail over the general Terms, except for mandatory provisions on personal
    data protection (GDPR + Estonian IKS), which prevail in any event.
</div>

@if(!empty($customClauses))
    <h2>7. Special conditions</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'Special clause' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
