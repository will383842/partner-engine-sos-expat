<h2>1. Partes</h2>
<table>
    <tr>
        <th style="width: 35%;">Prestador (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            Sociedade estoniana por quotas (Osaühing — OÜ)<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) Registo Comercial da Estónia (Äriregister): {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) Número de IVA UE: {{ $vars['provider_vat'] }}<br>@endif
            E-mail: {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>Parceiro</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            Identificador interno: {{ $vars['partner_firebase_id'] }}<br>
            E-mail de faturação: {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) Referência do acordo: {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. Período de vigência</h2>
<table>
    <tr><th>Data de entrada em vigor</th><td>{{ $vars['starts_at'] }}</td></tr>
    <tr><th>Data de expiração</th>
        <td>{{ $vars['expires_at'] ?: 'Duração indeterminada — resolúvel com pré-aviso de 30 dias' }}</td></tr>
</table>

<h2>3. Modelo económico</h2>
<p>A presente nota de encomenda adota o seguinte modelo económico:
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (assinatura mensal B2B)</strong> — o Parceiro paga uma assinatura mensal,
    os seus Assinantes ligam gratuitamente.
@elseif($vars['economic_model'] === 'commission')
    <strong>Comissão por chamada</strong> — o Parceiro recebe comissão por cada chamada de
    um dos seus utilizadores referenciados.
@elseif($vars['economic_model'] === 'hybrid')
    <strong>Híbrido</strong> — combinação de assinatura mensal e comissão.
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. Tarifário SOS-Call</h2>
    <table>
        <tr><th>Moeda de faturação</th><td><strong>{{ $vars['billing_currency'] }}</strong></td></tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr><th>Componente fixa mensal</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr><th>Por assinante ativo e por mês</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        <tr><th>Prazo de pagamento</th><td>{{ $vars['payment_terms_days'] }} dias fim de mês</td></tr>
        <tr><th>Tipos de chamada autorizados</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') Apenas Expat @break
                    @case('lawyer_only') Apenas Advogado @break
                    @default Expat e Advogado
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>Escalões tarifários (componente fixa por número de assinantes)</h3>
        <table>
            <tr><th>Assinantes ativos</th><th>Assinatura mensal</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'ilimitado' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', '.') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. Quotas e limites</h2>
<table>
    <tr><th>Número máximo de assinantes</th>
        <td>@if(($vars['max_subscribers'] ?? 0) > 0) {{ $vars['max_subscribers'] }} @else Ilimitado @endif</td></tr>
    <tr><th>Duração padrão do assinante</th>
        <td>@if(($vars['default_subscriber_duration_days'] ?? 0) > 0) {{ $vars['default_subscriber_duration_days'] }} dias @else Permanente (até expiração do contrato) @endif</td></tr>
    <tr><th>Duração máxima do assinante</th>
        <td>@if(($vars['max_subscriber_duration_days'] ?? 0) > 0) {{ $vars['max_subscriber_duration_days'] }} dias @else Sem limite @endif</td></tr>
</table>

<h2>6. Referência às condições gerais</h2>
<div class="important">
    A presente nota de encomenda rege-se pelas <strong>Condições Gerais de Venda B2B SOS-Call</strong>
    e pelo <strong>Acordo de Tratamento de Dados (DPA)</strong> anexos a este contrato e assinados
    em conjunto, segundo o <strong>direito estoniano</strong>. Em caso de contradição, as
    estipulações particulares da presente nota de encomenda prevalecem sobre as CGV gerais, salvo
    quanto às disposições imperativas relativas à proteção de dados pessoais (RGPD + IKS estoniano),
    que prevalecem em qualquer caso.
</div>

@if(!empty($customClauses))
    <h2>7. Condições particulares</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'Cláusula particular' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
