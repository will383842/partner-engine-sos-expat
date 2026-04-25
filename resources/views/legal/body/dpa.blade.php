<h2>Préambule</h2>
<p>Le présent Accord de Traitement de Données (« <strong>DPA</strong> ») est conclu en application de
l'<strong>article 28 du Règlement (UE) 2016/679</strong> du 27 avril 2016 (« <strong>RGPD</strong> ») entre :</p>
<ul>
    <li>
        <strong>{{ $vars['partner_name'] }}</strong>, ci-après « <strong>le Responsable de traitement</strong> »
        (le Partenaire), qui détermine les finalités et les moyens du traitement des données
        personnelles de ses Abonnés ;
    </li>
    <li>
        <strong>{{ $vars['provider_legal_name'] }}</strong>, ci-après « <strong>le Sous-traitant</strong> »
        (SOS-Expat), qui traite ces données pour le compte du Responsable de traitement
        dans le cadre du service SOS-Call.
    </li>
</ul>
<p>Délégué à la protection des données du Sous-traitant : <strong>{{ $vars['provider_dpo_email'] }}</strong></p>

<h2>1. Objet et durée du traitement</h2>
<p>Le Sous-traitant traite les données personnelles des Abonnés pour permettre la mise en relation
téléphonique avec un avocat ou expert référencé, ainsi que pour la facturation, l'audit
et la lutte contre la fraude. Le traitement est effectué pendant toute la durée d'exécution
du contrat principal entre les Parties.</p>

<h2>2. Catégories de données traitées</h2>
<table>
    <tr><th>Catégorie</th><th>Exemples</th><th>Finalité</th></tr>
    <tr>
        <td>Données d'identification</td>
        <td>Nom, prénom, email, identifiant CRM externe, code SOS-Call</td>
        <td>Authentification de l'Abonné, mise en relation</td>
    </tr>
    <tr>
        <td>Données de contact</td>
        <td>Numéro de téléphone, langue, pays de résidence</td>
        <td>Établissement de l'appel téléphonique</td>
    </tr>
    <tr>
        <td>Données d'usage</td>
        <td>Date, heure, durée des appels, type d'expert sollicité</td>
        <td>Facturation, statistiques agrégées, lutte contre la fraude</td>
    </tr>
    <tr>
        <td>Données techniques</td>
        <td>Adresse IP, user-agent (uniquement lors d'opérations sensibles)</td>
        <td>Sécurité, audit, conformité eIDAS</td>
    </tr>
</table>

<h2>3. Catégories de personnes concernées</h2>
<p>Les Abonnés enregistrés par le Partenaire (personnes physiques majeures bénéficiaires
du service SOS-Call), ainsi que les contacts de facturation et administrateurs
désignés par le Partenaire.</p>

<h2>4. Obligations du Sous-traitant</h2>
<p>Le Sous-traitant s'engage à :</p>
<ol>
    <li>Ne traiter les données que sur instruction documentée du Responsable de traitement,
        sauf obligation légale.</li>
    <li>Garantir la confidentialité des données et n'autoriser leur accès qu'aux personnels
        soumis à un engagement de confidentialité.</li>
    <li>Mettre en œuvre des mesures techniques et organisationnelles appropriées
        (chiffrement TLS 1.3 en transit, chiffrement at-rest, contrôle d'accès par rôle,
        journalisation des accès, sauvegardes chiffrées géo-redondantes,
        revue annuelle des accès).</li>
    <li>Assister le Responsable de traitement, dans la mesure du raisonnable,
        dans la conduite des analyses d'impact (AIPD), la gestion des demandes
        des personnes concernées, et la notification des violations de données.</li>
    <li>Notifier toute violation de données dans un délai maximal de
        <strong>72 heures</strong> à compter de sa découverte, par email à l'adresse
        de facturation et au DPO du Partenaire si désigné.</li>
    <li>Tenir un registre des activités de traitement effectuées pour le compte
        du Responsable de traitement.</li>
</ol>

<h2>5. Sous-traitance ultérieure</h2>
<p>Le Sous-traitant a recours, pour l'exécution du service, aux sous-traitants ultérieurs suivants :</p>
<table>
    <tr><th>Sous-traitant</th><th>Service</th><th>Localisation</th></tr>
    <tr><td>Google Cloud (Firebase)</td><td>Hébergement applicatif, Firestore</td><td>UE / US (clauses contractuelles types)</td></tr>
    <tr><td>Twilio</td><td>Mise en relation téléphonique</td><td>UE / US (clauses contractuelles types)</td></tr>
    <tr><td>Stripe Payments Europe</td><td>Encaissement des paiements</td><td>UE</td></tr>
    <tr><td>Hetzner Online GmbH</td><td>Hébergement Partner Engine</td><td>Allemagne (UE)</td></tr>
</table>
<p>Toute modification de cette liste sera notifiée au Responsable de traitement
au moins trente (30) jours à l'avance, période durant laquelle il pourra s'y opposer
et résilier le contrat principal sans frais en cas de désaccord.</p>

<h2>6. Transferts hors UE</h2>
<p>Tout transfert de données hors de l'Union européenne est encadré par
les Clauses Contractuelles Types (CCT) adoptées par la Commission européenne
le 4 juin 2021 (Décision (UE) 2021/914), ou par toute mesure équivalente
prévue par le RGPD.</p>

<h2>7. Droits des personnes concernées</h2>
<p>Le Responsable de traitement reste le point de contact unique des personnes concernées
pour l'exercice de leurs droits (accès, rectification, effacement, limitation, opposition,
portabilité). Le Sous-traitant fournit dans un délai raisonnable, sur demande motivée
du Responsable de traitement, les éléments nécessaires à la satisfaction
des droits des personnes.</p>

<h2>8. Durée de conservation</h2>
<ul>
    <li>Données d'identification et de contact : durée de la relation contractuelle
        + 3 ans (prescription commerciale).</li>
    <li>Données d'usage et de facturation : 10 ans (obligations comptables et fiscales).</li>
    <li>Logs techniques d'authentification : 12 mois.</li>
    <li>Empreintes SHA-256 des documents signés et preuves d'acceptation : 10 ans
        à compter de la signature, conformément aux exigences eIDAS.</li>
</ul>

<h2>9. Suppression et restitution des données</h2>
<p>À l'expiration du contrat principal, le Sous-traitant procède, au choix
du Responsable de traitement notifié dans les trente (30) jours :</p>
<ul>
    <li>soit à la suppression définitive des données, sauf obligation légale de conservation,
        dans un délai de 90 jours ;</li>
    <li>soit à leur restitution dans un format structuré et couramment utilisé.</li>
</ul>

<h2>10. Audit</h2>
<p>Le Responsable de traitement peut, après notification écrite préalable de quinze (15) jours
ouvrés, conduire (ou faire conduire par un tiers indépendant soumis à confidentialité)
un audit des mesures techniques et organisationnelles mises en œuvre par le Sous-traitant,
dans la limite d'un audit par an et aux frais du Responsable de traitement,
sauf découverte d'un manquement substantiel.</p>

<h2>11. Responsabilité</h2>
<p>Conformément à l'article 82 du RGPD, chaque Partie est responsable des dommages causés
par un traitement violant le RGPD, à proportion de sa contribution au manquement constaté.</p>

@if(!empty($customClauses))
    <h2>12. Stipulations particulières</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>12.{{ $i + 1 }} {{ $clause['title'] ?? 'Clause particulière' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
