<h2>1. पक्ष</h2>
<table>
    <tr>
        <th style="width: 35%;">प्रदाता (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            एस्टोनियाई सीमित दायित्व कंपनी (Osaühing — OÜ)<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) एस्टोनियाई व्यवसाय रजिस्टर (Äriregister): {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) EU VAT संख्या: {{ $vars['provider_vat'] }}<br>@endif
            ईमेल: {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>साझेदार</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            आंतरिक ID: {{ $vars['partner_firebase_id'] }}<br>
            बिलिंग ईमेल: {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) समझौता संदर्भ: {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. प्रतिबद्धता अवधि</h2>
<table>
    <tr><th>प्रभावी तिथि</th><td>{{ $vars['starts_at'] }}</td></tr>
    <tr><th>समाप्ति तिथि</th>
        <td>{{ $vars['expires_at'] ?: 'अनिश्चित अवधि — 30 दिनों की सूचना के साथ समाप्त किया जा सकता है' }}</td></tr>
</table>

<h2>3. आर्थिक मॉडल</h2>
<p>यह ऑर्डर निम्नलिखित आर्थिक मॉडल का चयन करता है:
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (B2B मासिक सदस्यता)</strong> — साझेदार मासिक सदस्यता का भुगतान करता है,
    उसके ग्राहक निःशुल्क कॉल करते हैं।
@elseif($vars['economic_model'] === 'commission')
    <strong>प्रति कॉल कमीशन</strong> — साझेदार अपने रेफर किए गए उपयोगकर्ताओं की प्रत्येक कॉल पर कमीशन प्राप्त करता है।
@elseif($vars['economic_model'] === 'hybrid')
    <strong>हाइब्रिड</strong> — मासिक सदस्यता और कमीशन का संयोजन।
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. SOS-Call मूल्य निर्धारण</h2>
    <table>
        <tr><th>बिलिंग मुद्रा</th><td><strong>{{ $vars['billing_currency'] }}</strong></td></tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr><th>निश्चित मासिक शुल्क</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr><th>प्रति सक्रिय ग्राहक प्रति माह</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        <tr><th>भुगतान अवधि</th><td>माह के अंत के बाद {{ $vars['payment_terms_days'] }} दिन</td></tr>
        <tr><th>अनुमत कॉल प्रकार</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') केवल Expat @break
                    @case('lawyer_only') केवल वकील @break
                    @default Expat और वकील
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>मूल्य स्तर (ग्राहक संख्या के आधार पर निश्चित शुल्क)</h3>
        <table>
            <tr><th>सक्रिय ग्राहक</th><th>मासिक सदस्यता</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'असीमित' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, '.', ',') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. कोटा और सीमाएँ</h2>
<table>
    <tr><th>अधिकतम ग्राहक संख्या</th>
        <td>@if(($vars['max_subscribers'] ?? 0) > 0) {{ $vars['max_subscribers'] }} @else असीमित @endif</td></tr>
    <tr><th>डिफ़ॉल्ट सदस्यता अवधि</th>
        <td>@if(($vars['default_subscriber_duration_days'] ?? 0) > 0) {{ $vars['default_subscriber_duration_days'] }} दिन @else स्थायी (अनुबंध समाप्ति तक) @endif</td></tr>
    <tr><th>अधिकतम सदस्यता अवधि</th>
        <td>@if(($vars['max_subscriber_duration_days'] ?? 0) > 0) {{ $vars['max_subscriber_duration_days'] }} दिन @else कोई सीमा नहीं @endif</td></tr>
</table>

<h2>6. सामान्य शर्तों का संदर्भ</h2>
<div class="important">
    यह ऑर्डर इस अनुबंध से संलग्न और संयुक्त रूप से हस्ताक्षरित
    <strong>SOS-Call B2B सामान्य बिक्री शर्तों</strong> और <strong>डेटा प्रसंस्करण समझौता (DPA)</strong>
    द्वारा शासित है, <strong>एस्टोनियाई कानून</strong> के अनुसार। विरोधाभास के मामले में,
    इस ऑर्डर के विशेष प्रावधान सामान्य शर्तों पर प्राथमिकता रखते हैं, सिवाय व्यक्तिगत डेटा सुरक्षा से
    संबंधित अनिवार्य प्रावधानों के (GDPR + एस्टोनियाई IKS), जो किसी भी मामले में प्राथमिकता रखते हैं।
</div>

@if(!empty($customClauses))
    <h2>7. विशेष शर्तें</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'विशेष शर्त' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
