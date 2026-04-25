<h2>1. Parties</h2>
<table>
    <tr>
        <th style="width: 35%;">Prestataire (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) SIRET : {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) TVA intracommunautaire : {{ $vars['provider_vat'] }}<br>@endif
            Email : {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>Partenaire</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            Identifiant interne : {{ $vars['partner_firebase_id'] }}<br>
            Email de facturation : {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) Référence accord : {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. Période d'engagement</h2>
<table>
    <tr>
        <th>Date de prise d'effet</th>
        <td>{{ $vars['starts_at'] }}</td>
    </tr>
    <tr>
        <th>Date d'expiration</th>
        <td>{{ $vars['expires_at'] ?: 'Durée indéterminée — résiliable avec préavis de 30 jours' }}</td>
    </tr>
</table>

<h2>3. Modèle économique</h2>
<p>Le présent bon de commande retient le modèle économique suivant :
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (forfait mensuel B2B)</strong> — le Partenaire paie un forfait mensuel,
    ses Abonnés appellent gratuitement.
@elseif($vars['economic_model'] === 'commission')
    <strong>Commission par appel</strong> — le Partenaire perçoit une commission
    pour chaque appel d'un de ses utilisateurs référés.
@elseif($vars['economic_model'] === 'hybrid')
    <strong>Hybride</strong> — combinaison d'un forfait mensuel et d'un système de commission.
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. Tarification SOS-Call</h2>
    <table>
        <tr>
            <th>Devise de facturation</th>
            <td><strong>{{ $vars['billing_currency'] }}</strong></td>
        </tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr>
                <th>Forfait fixe mensuel</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', ' ') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr>
                <th>Tarif par abonné actif et par mois</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, ',', ' ') }} {{ $vars['billing_currency'] }}</strong></td>
            </tr>
        @endif
        <tr>
            <th>Délai de paiement</th>
            <td>{{ $vars['payment_terms_days'] }} jours fin de mois</td>
        </tr>
        <tr>
            <th>Types d'appels autorisés</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') Expat uniquement @break
                    @case('lawyer_only') Avocat uniquement @break
                    @default Expat et Avocat
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>Paliers tarifaires (forfait fixe selon nombre d'abonnés)</h3>
        <table>
            <tr><th>Nombre d'abonnés actifs</th><th>Forfait mensuel</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'illimité' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', ' ') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. Quotas et limites</h2>
<table>
    <tr>
        <th>Nombre maximum d'abonnés</th>
        <td>
            @if(($vars['max_subscribers'] ?? 0) > 0)
                {{ $vars['max_subscribers'] }}
            @else
                Illimité
            @endif
        </td>
    </tr>
    <tr>
        <th>Durée d'abonnement par défaut</th>
        <td>
            @if(($vars['default_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['default_subscriber_duration_days'] }} jours
            @else
                Permanent (jusqu'à expiration du contrat)
            @endif
        </td>
    </tr>
    <tr>
        <th>Durée maximale d'abonnement</th>
        <td>
            @if(($vars['max_subscriber_duration_days'] ?? 0) > 0)
                {{ $vars['max_subscriber_duration_days'] }} jours
            @else
                Non plafonnée
            @endif
        </td>
    </tr>
</table>

<h2>6. Référence aux conditions générales</h2>
<div class="important">
    Le présent bon de commande est régi par les <strong>Conditions Générales de Vente B2B SOS-Call</strong>
    et l'<strong>Accord de Traitement de Données (DPA)</strong> annexés au présent contrat
    et signés conjointement. En cas de contradiction, les stipulations particulières
    du présent bon de commande prévalent sur les CGV générales, sauf pour les dispositions
    impératives liées à la protection des données personnelles, qui prévalent en toute hypothèse.
</div>

@if(!empty($customClauses))
    <h2>7. Conditions particulières</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'Clause particulière' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
