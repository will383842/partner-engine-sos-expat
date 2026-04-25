<h2>Präambel</h2>
<p>Diese Vereinbarung zur Auftragsverarbeitung (das „<strong>DPA</strong>") wird gemäß
<strong>Artikel 28 der Verordnung (EU) 2016/679</strong> vom 27. April 2016 (die „<strong>DSGVO</strong>")
und dem <strong>Estnischen Datenschutzgesetz (Isikuandmete kaitse seadus, „IKS")</strong> geschlossen, zwischen:</p>
<ul>
    <li>
        <strong>{{ $vars['partner_name'] }}</strong>, nachstehend der „<strong>Verantwortliche</strong>"
        (der Partner), der die Zwecke und Mittel der Verarbeitung personenbezogener Daten seiner Abonnenten bestimmt;
    </li>
    <li>
        <strong>{{ $vars['provider_legal_name'] }}</strong>, eine estnische Gesellschaft, nachstehend der
        „<strong>Auftragsverarbeiter</strong>" (SOS-Expat), der diese Daten im Rahmen des SOS-Call-Dienstes
        im Auftrag des Verantwortlichen verarbeitet.
    </li>
</ul>
<p>Datenschutzbeauftragter des Auftragsverarbeiters: <strong>{{ $vars['provider_dpo_email'] }}</strong>.
Zuständige Aufsichtsbehörde: <strong>Andmekaitse Inspektsioon (AKI)</strong>, estnische Datenschutzbehörde — <em>www.aki.ee</em>.</p>

<h2>1. Gegenstand und Dauer der Verarbeitung</h2>
<p>Der Auftragsverarbeiter verarbeitet die personenbezogenen Daten der Abonnenten zur Herstellung
der Telefonverbindung mit einem gelisteten Anwalt oder Sachverständigen sowie zur Rechnungsstellung,
Prüfung und Betrugsbekämpfung. Die Verarbeitung erfolgt während der gesamten Laufzeit des
Hauptvertrags zwischen den Parteien.</p>

<h2>2. Kategorien verarbeiteter Daten</h2>
<table>
    <tr><th>Kategorie</th><th>Beispiele</th><th>Zweck</th></tr>
    <tr>
        <td>Identifikationsdaten</td>
        <td>Name, Vorname, E-Mail, externe CRM-ID, SOS-Call-Code</td>
        <td>Authentifizierung, Verbindung</td>
    </tr>
    <tr>
        <td>Kontaktdaten</td>
        <td>Telefonnummer, Sprache, Wohnsitzland</td>
        <td>Aufbau des Telefonanrufs</td>
    </tr>
    <tr>
        <td>Nutzungsdaten</td>
        <td>Datum, Uhrzeit, Anrufdauer, angefragter Sachverständigentyp</td>
        <td>Rechnungsstellung, aggregierte Statistiken, Betrugsbekämpfung</td>
    </tr>
    <tr>
        <td>Technische Daten</td>
        <td>IP-Adresse, User-Agent (nur bei sensiblen Operationen)</td>
        <td>Sicherheit, Audit, eIDAS-Konformität</td>
    </tr>
</table>

<h2>3. Kategorien betroffener Personen</h2>
<p>Die vom Partner registrierten Abonnenten (volljährige natürliche Personen, die den
SOS-Call-Dienst nutzen) sowie die vom Partner benannten Rechnungs- und Verwaltungskontakte.</p>

<h2>4. Pflichten des Auftragsverarbeiters</h2>
<p>Der Auftragsverarbeiter verpflichtet sich:</p>
<ol>
    <li>die Daten nur auf dokumentierte Weisung des Verantwortlichen zu verarbeiten, sofern
        keine gesetzliche Verpflichtung besteht;</li>
    <li>die Vertraulichkeit der Daten zu gewährleisten und den Zugriff nur Personal zu erlauben,
        das zur Vertraulichkeit verpflichtet ist;</li>
    <li>geeignete technische und organisatorische Maßnahmen umzusetzen (TLS 1.3 in Übertragung,
        Verschlüsselung im Ruhezustand, rollenbasierte Zugriffskontrolle, Zugriffsprotokollierung,
        georedundante verschlüsselte Backups, jährliche Zugriffsüberprüfung);</li>
    <li>den Verantwortlichen in angemessenem Umfang bei Datenschutz-Folgenabschätzungen,
        der Bearbeitung von Betroffenenanfragen und der Meldung von Datenschutzverletzungen zu
        unterstützen;</li>
    <li>jede Datenschutzverletzung innerhalb von <strong>72 Stunden</strong> nach ihrer Entdeckung
        per E-Mail an die Rechnungsadresse und an den DSB des Partners (sofern benannt) zu melden,
        gemäß den Artikeln 33-34 DSGVO;</li>
    <li>ein Verzeichnis der für den Verantwortlichen durchgeführten Verarbeitungstätigkeiten
        zu führen (Artikel 30 DSGVO).</li>
</ol>

<h2>5. Unterauftragsverarbeitung</h2>
<p>Der Auftragsverarbeiter setzt zur Erbringung des Dienstes folgende Unterauftragsverarbeiter ein:</p>
<table>
    <tr><th>Unterauftragsverarbeiter</th><th>Dienst</th><th>Standort</th></tr>
    <tr><td>Google Cloud (Firebase)</td><td>Anwendungs-Hosting, Firestore</td><td>EU / USA (Standardvertragsklauseln)</td></tr>
    <tr><td>Twilio</td><td>Telefonverbindung</td><td>EU / USA (Standardvertragsklauseln)</td></tr>
    <tr><td>Stripe Payments Europe</td><td>Zahlungsabwicklung</td><td>EU</td></tr>
    <tr><td>Hetzner Online GmbH</td><td>Partner-Engine-Hosting</td><td>Deutschland (EU)</td></tr>
</table>
<p>Jede Änderung dieser Liste wird dem Verantwortlichen mindestens dreißig (30) Tage im Voraus
mitgeteilt; in dieser Zeit kann er Widerspruch einlegen und im Falle der Uneinigkeit den
Hauptvertrag kostenfrei kündigen.</p>

<h2>6. Übermittlungen außerhalb der EU</h2>
<p>Jede Datenübermittlung außerhalb des Europäischen Wirtschaftsraums erfolgt auf Grundlage der
<strong>Standardvertragsklauseln (SCC)</strong>, die von der Europäischen Kommission am
4. Juni 2021 erlassen wurden (Beschluss (EU) 2021/914), oder einer gleichwertigen, von der DSGVO
vorgesehenen und von der Andmekaitse Inspektsioon anerkannten Maßnahme.</p>

<h2>7. Rechte der betroffenen Personen</h2>
<p>Der Verantwortliche bleibt einziger Ansprechpartner der betroffenen Personen für die Ausübung
ihrer Rechte (Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch, Übertragbarkeit).
Der Auftragsverarbeiter stellt auf begründete Anfrage des Verantwortlichen innerhalb angemessener
Frist die zur Erfüllung der Betroffenenrechte erforderlichen Elemente bereit.</p>

<h2>8. Aufbewahrungsdauer</h2>
<ul>
    <li>Identifikations- und Kontaktdaten: Dauer der Vertragsbeziehung + 3 Jahre
        (estnische Handelsverjährung, VÕS §146);</li>
    <li>Nutzungs- und Rechnungsdaten: 7 Jahre (estnische Buchhaltungspflichten,
        Raamatupidamise seadus §12);</li>
    <li>technische Authentifizierungsprotokolle: 12 Monate;</li>
    <li>SHA-256-Fingerabdrücke signierter Dokumente und Annahmenachweise: 10 Jahre ab
        Unterzeichnung gemäß eIDAS-Anforderungen.</li>
</ul>

<h2>9. Löschung und Rückgabe der Daten</h2>
<p>Bei Beendigung des Hauptvertrags wird der Auftragsverarbeiter nach Wahl des Verantwortlichen,
mitgeteilt innerhalb von dreißig (30) Tagen:</p>
<ul>
    <li>die Daten innerhalb von 90 Tagen endgültig löschen, vorbehaltlich gesetzlicher
        Aufbewahrungspflichten; oder</li>
    <li>die Daten in einem strukturierten, gängigen Format zurückgeben.</li>
</ul>

<h2>10. Audit</h2>
<p>Der Verantwortliche kann nach schriftlicher Vorankündigung von fünfzehn (15) Werktagen
(oder durch einen unabhängigen, zur Vertraulichkeit verpflichteten Dritten) ein Audit der vom
Auftragsverarbeiter umgesetzten technischen und organisatorischen Maßnahmen durchführen,
beschränkt auf ein Audit pro Jahr und auf Kosten des Verantwortlichen, sofern kein wesentlicher
Verstoß festgestellt wird.</p>

<h2>11. Haftung</h2>
<p>Gemäß <strong>Artikel 82 DSGVO</strong> haftet jede Partei für Schäden, die durch eine gegen
die DSGVO verstoßende Verarbeitung verursacht werden, im Verhältnis zu ihrem Beitrag zum Verstoß.
Dieses DPA unterliegt estnischem Recht.</p>

@if(!empty($customClauses))
    <h2>12. Sonderbestimmungen</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>12.{{ $i + 1 }} {{ $clause['title'] ?? 'Sonderbestimmung' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
