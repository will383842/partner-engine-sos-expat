<h2>1. Objet et parties</h2>
<p>Les présentes Conditions Générales de Vente (« <strong>CGV</strong> ») régissent l'utilisation,
par le partenaire <strong>{{ $vars['partner_name'] }}</strong> (« <strong>le Partenaire</strong> »),
du service SOS-Call mis à disposition par <strong>{{ $vars['provider_legal_name'] }}</strong>,
société estonienne immatriculée au registre commercial estonien (Äriregister)
@if($vars['provider_siret'] ?? null)
    sous le numéro <strong>{{ $vars['provider_siret'] }}</strong>,
@endif
@if($vars['provider_address'] ?? null)
    dont le siège est situé {{ $vars['provider_address'] }},
@endif
ci-après « <strong>SOS-Expat</strong> » ou « <strong>le Prestataire</strong> ».</p>
<p>Le service SOS-Call permet aux abonnés enregistrés par le Partenaire d'accéder gratuitement
à des appels téléphoniques d'assistance juridique d'urgence avec des avocats et experts indépendants
référencés sur la plateforme du Prestataire, en échange du paiement par le Partenaire d'un forfait
mensuel calculé conformément à l'article 4.</p>

<h2>2. Définitions</h2>
<ul>
    <li><strong>Abonné</strong> : personne physique enregistrée par le Partenaire et bénéficiaire
        du service SOS-Call. Un abonné est identifié par un code SOS-Call unique ou par
        son numéro de téléphone et email vérifiés.</li>
    <li><strong>Appel SOS-Call</strong> : appel téléphonique passé par un Abonné via la plateforme
        SOS-Expat à un avocat ou expert référencé.</li>
    <li><strong>Forfait mensuel</strong> : redevance due par le Partenaire chaque mois,
        calculée selon les paramètres définis à l'article 4.</li>
    <li><strong>Console partenaire</strong> : interface web mise à disposition du Partenaire
        à l'adresse <em>partner-engine.sos-expat.com</em> et <em>sos-expat.com/partner/*</em>.</li>
</ul>

<h2>3. Durée et résiliation</h2>
<p>Le présent contrat prend effet à la date du <strong>{{ $vars['starts_at'] }}</strong>
@if($vars['expires_at'])
    et expire le <strong>{{ $vars['expires_at'] }}</strong>, sauf renouvellement exprès des Parties.
@else
    et est conclu pour une durée indéterminée.
@endif
</p>
<p>Chacune des Parties peut résilier le contrat à tout moment, sous réserve d'un préavis écrit
de trente (30) jours notifié par lettre recommandée ou par email à l'adresse de facturation
indiquée. La résiliation prend effet à l'issue du préavis. Les forfaits mensuels en cours
restent dus jusqu'au terme du préavis.</p>
<p>En cas de manquement grave de l'une des Parties à ses obligations contractuelles, l'autre Partie
peut résilier de plein droit, conformément à la <strong>Loi estonienne sur le droit des
obligations (Võlaõigusseadus, ci-après « VÕS ») §116</strong>, après mise en demeure restée
infructueuse pendant quinze (15) jours.</p>

<h2>4. Tarification et facturation</h2>
@php
    $hasFlat = ($vars['monthly_base_fee'] ?? 0) > 0;
    $hasPerMember = ($vars['billing_rate'] ?? 0) > 0;
    $tiers = $vars['pricing_tiers'] ?? [];
@endphp
<p>Le Partenaire s'acquitte d'un forfait mensuel calculé selon les modalités suivantes
(devise : <strong>{{ $vars['billing_currency'] }}</strong>) :</p>
<ul>
    @if($hasFlat)
        <li>Part fixe mensuelle : <strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', ' ') }} {{ $vars['billing_currency'] }}</strong></li>
    @endif
    @if($hasPerMember)
        <li>Part variable : <strong>{{ number_format((float)$vars['billing_rate'], 2, ',', ' ') }} {{ $vars['billing_currency'] }}</strong> par abonné actif et par mois.</li>
    @endif
    @if(!empty($tiers))
        <li>Paliers tarifaires :
            <table>
                <tr><th>Plage d'abonnés</th><th>Forfait</th></tr>
                @foreach($tiers as $tier)
                    <tr>
                        <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'illimité' }}</td>
                        <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', ' ') }} {{ $vars['billing_currency'] }}</td>
                    </tr>
                @endforeach
            </table>
        </li>
    @endif
    <li>Délai de paiement : <strong>{{ $vars['payment_terms_days'] }} jours</strong> à compter
        de la date d'émission de la facture.</li>
</ul>
<p>Les factures sont émises automatiquement le 1<sup>er</sup> de chaque mois pour le mois écoulé,
et envoyées à l'adresse de facturation <strong>{{ $vars['billing_email'] }}</strong>.
Le paiement peut être effectué par carte bancaire (lien Stripe inclus dans la facture)
ou par virement SEPA aux coordonnées indiquées sur la facture.</p>
<p>Tout retard de paiement entraîne, de plein droit et sans mise en demeure préalable,
l'application d'un intérêt de retard au <strong>taux légal estonien défini par VÕS §113</strong>
(taux de référence BCE majoré de huit points de pourcentage pour les transactions B2B
conformément à la directive 2011/7/UE), ainsi qu'une indemnité forfaitaire de quarante (40)
euros pour frais de recouvrement, conformément à VÕS §113<sup>1</sup>.</p>

<h2>5. Engagements du Prestataire</h2>
<p>Le Prestataire s'engage à :</p>
<ul>
    <li>Mettre à disposition la console partenaire et l'API associée avec un objectif
        de disponibilité de 99,5 % mensuel (hors maintenance planifiée notifiée 48 h à l'avance).</li>
    <li>Permettre à chaque Abonné d'accéder gratuitement, dans la limite des quotas convenus,
        au service d'appel d'urgence avec un avocat ou expert référencé.</li>
    <li>Garantir la qualité de service de la mise en relation téléphonique
        (infrastructure Twilio, redondance multi-région).</li>
    <li>Assurer la confidentialité des appels et le traitement conforme des données personnelles
        selon les modalités du DPA annexe.</li>
</ul>

<h2>6. Engagements du Partenaire</h2>
<p>Le Partenaire s'engage à :</p>
<ul>
    <li>Régler ponctuellement les factures émises selon les modalités de l'article 4.</li>
    <li>N'enregistrer comme Abonnés que des personnes physiques majeures pour lesquelles
        il dispose d'une base juridique de traitement RGPD valide
        (relation contractuelle, intérêt légitime, consentement explicite).</li>
    <li>Informer ses Abonnés que leurs appels sont effectués via la plateforme SOS-Expat
        et que leurs données sont traitées conformément au DPA annexe.</li>
    <li>Ne pas faire un usage frauduleux ou abusif du service (par exemple : revendre l'accès,
        fournir des codes SOS-Call à des tiers non identifiés, contourner les quotas).</li>
</ul>

<h2>7. Responsabilité</h2>
<p>Le Prestataire fournit une infrastructure de mise en relation. Il n'est pas
partie au contrat juridique entre l'Abonné et l'avocat ou expert consulté.
Les conseils et avis donnés au cours d'un appel relèvent de la seule responsabilité
de l'avocat ou expert intervenant.</p>
<p>Sous réserve des limitations impératives prévues par <strong>VÕS §106</strong>, la responsabilité
du Prestataire au titre des présentes ne peut excéder, tous chefs de préjudice confondus, le montant
total des forfaits mensuels payés par le Partenaire au cours des douze (12) mois précédant
l'événement générateur de responsabilité.</p>
<p>Le Prestataire ne saurait être tenu responsable de tout dommage indirect,
notamment perte d'exploitation, perte de chiffre d'affaires, atteinte à l'image,
ou perte de données.</p>

<h2>8. Force majeure</h2>
<p>Aucune des Parties ne pourra être tenue responsable d'un manquement à ses obligations résultant
d'un cas de force majeure tel que défini par <strong>VÕS §103</strong> (Loi estonienne sur le droit
des obligations) : circonstance extraordinaire échappant au contrôle de la Partie, qu'elle ne pouvait
raisonnablement prévoir lors de la conclusion du contrat ni éviter ou surmonter.</p>

<h2>9. Données personnelles</h2>
<p>Les modalités de traitement des données personnelles des Abonnés sont régies
par l'<strong>Accord de traitement de données (DPA)</strong> conclu en annexe des présentes,
conformément à l'article 28 du Règlement (UE) 2016/679 (RGPD) et à la
<strong>Loi estonienne sur la protection des données personnelles (Isikuandmete kaitse seadus, IKS)</strong>.</p>

<h2>10. Confidentialité</h2>
<p>Chacune des Parties s'engage à conserver strictement confidentielles toutes informations
non publiques échangées dans le cadre de l'exécution du contrat, et à ne pas les divulguer
à des tiers sans accord écrit préalable, pendant toute la durée du contrat et trois (3) ans
après son terme.</p>

<h2>11. Modifications</h2>
<p>Le Prestataire peut modifier les présentes CGV en notifiant au Partenaire toute nouvelle version
au moins trente (30) jours avant son entrée en vigueur. Le Partenaire dispose alors d'un droit
de résiliation sans frais s'il refuse la nouvelle version. À défaut de résiliation dans le délai
imparti, la nouvelle version est réputée acceptée.</p>

<h2>12. Loi applicable et juridiction</h2>
<p>Les présentes CGV sont régies par <strong>le droit estonien</strong>, à l'exclusion de ses
règles de conflit de lois. La <strong>Convention des Nations Unies sur les contrats de vente
internationale de marchandises (CISG, Vienne 1980)</strong> est expressément exclue.</p>
<p>Tout litige relatif à la formation, l'interprétation ou l'exécution des présentes
sera soumis à la compétence exclusive du <strong>{{ $vars['provider_jurisdiction'] }}</strong>
(Tribunal de comté de Harju, Tallinn), nonobstant pluralité de défendeurs ou appel en garantie.</p>
<p>Les Parties consentent expressément à cette élection de juridiction conformément
à l'<strong>article 25 du Règlement (UE) n°1215/2012</strong> (Bruxelles I bis) pour les
partenaires établis dans l'Union européenne.</p>

@if(!empty($customClauses))
    <h2>13. Clauses particulières</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>13.{{ $i + 1 }} {{ $clause['title'] ?? 'Clause particulière' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
