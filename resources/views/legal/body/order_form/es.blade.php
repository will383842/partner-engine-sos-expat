<h2>1. Partes</h2>
<table>
    <tr>
        <th style="width: 35%;">Prestador (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            Sociedad estonia de responsabilidad limitada (Osaühing — OÜ)<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) Registro Mercantil de Estonia (Äriregister): {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) Número de IVA UE: {{ $vars['provider_vat'] }}<br>@endif
            Email: {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>Socio</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            Identificador interno: {{ $vars['partner_firebase_id'] }}<br>
            Email de facturación: {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) Referencia del acuerdo: {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. Periodo de vigencia</h2>
<table>
    <tr>
        <th>Fecha de entrada en vigor</th>
        <td>{{ $vars['starts_at'] }}</td>
    </tr>
    <tr>
        <th>Fecha de expiración</th>
        <td>{{ $vars['expires_at'] ?: 'Duración indefinida — resoluble con preaviso de 30 días' }}</td>
    </tr>
</table>

<h2>3. Modelo económico</h2>
<p>El presente bono de pedido adopta el siguiente modelo económico:
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (abono mensual B2B)</strong> — el Socio paga un abono mensual,
    sus Abonados llaman gratuitamente.
@elseif($vars['economic_model'] === 'commission')
    <strong>Comisión por llamada</strong> — el Socio percibe una comisión por cada llamada
    de uno de sus usuarios referidos.
@elseif($vars['economic_model'] === 'hybrid')
    <strong>Híbrido</strong> — combinación de abono mensual y comisión.
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. Tarificación SOS-Call</h2>
    <table>
        <tr>
            <th>Divisa de facturación</th>
            <td><strong>{{ $vars['billing_currency'] }}</strong></td>
        </tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr>
                <th>Cuota fija mensual</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr>
                <th>Tarifa por abonado activo y mes</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        <tr>
            <th>Plazo de pago</th>
            <td>{{ $vars['payment_terms_days'] }} días fin de mes</td>
        </tr>
        <tr>
            <th>Tipos de llamadas autorizados</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') Sólo Expat @break
                    @case('lawyer_only') Sólo Abogado @break
                    @default Expat y Abogado
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>Tramos tarifarios (cuota fija según número de abonados)</h3>
        <table>
            <tr><th>Número de abonados activos</th><th>Cuota mensual</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'ilimitado' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', '.') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. Cuotas y límites</h2>
<table>
    <tr>
        <th>Número máximo de abonados</th>
        <td>
            @if(($vars['max_subscribers'] ?? 0) > 0)
                {{ $vars['max_subscribers'] }}
            @else
                Ilimitado
            @endif
        </td>
    </tr>
    <tr>
        <th>Duración por defecto del abono</th>
        <td>
            @if(($vars['default_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['default_subscriber_duration_days'] }} días
            @else
                Permanente (hasta expiración del contrato)
            @endif
        </td>
    </tr>
    <tr>
        <th>Duración máxima del abono</th>
        <td>
            @if(($vars['max_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['max_subscriber_duration_days'] }} días
            @else
                Sin límite
            @endif
        </td>
    </tr>
</table>

<h2>6. Referencia a las condiciones generales</h2>
<div class="important">
    El presente bono de pedido se rige por las <strong>Condiciones Generales de Venta B2B SOS-Call</strong>
    y el <strong>Acuerdo de Tratamiento de Datos (DPA)</strong> anexos al presente contrato y firmados
    conjuntamente, según el <strong>derecho estonio</strong>. En caso de contradicción, las
    estipulaciones particulares del presente bono de pedido prevalecen sobre las CGV generales,
    salvo para las disposiciones imperativas relativas a la protección de datos personales
    (RGPD + IKS estonio), que prevalecen en cualquier caso.
</div>

@if(!empty($customClauses))
    <h2>7. Condiciones particulares</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'Cláusula particular' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
