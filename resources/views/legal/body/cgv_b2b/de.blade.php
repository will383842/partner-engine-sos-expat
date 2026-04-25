<h2>1. Vertragsgegenstand und Parteien</h2>
<p>Diese Allgemeinen Geschäftsbedingungen (die „<strong>AGB</strong>") regeln die Nutzung
durch den Partner <strong>{{ $vars['partner_name'] }}</strong> (den „<strong>Partner</strong>")
des SOS-Call-Dienstes, der von <strong>{{ $vars['provider_legal_name'] }}</strong>,
einer im estnischen Handelsregister (Äriregister)
@if($vars['provider_siret'] ?? null)
    unter der Registrierungsnummer <strong>{{ $vars['provider_siret'] }}</strong>
@endif
eingetragenen estnischen Gesellschaft
@if($vars['provider_address'] ?? null)
    mit Sitz in {{ $vars['provider_address'] }}
@endif
(nachstehend „<strong>SOS-Expat</strong>" oder der „<strong>Anbieter</strong>") bereitgestellt wird.</p>
<p>Der SOS-Call-Dienst ermöglicht es den vom Partner registrierten Abonnenten, kostenlos
Notrufdienst-Telefonate mit unabhängigen, auf der Plattform des Anbieters gelisteten Anwälten
und Sachverständigen zu führen, gegen Zahlung einer monatlichen Gebühr durch den Partner gemäß
Artikel 4.</p>

<h2>2. Definitionen</h2>
<ul>
    <li><strong>Abonnent</strong>: natürliche Person, die vom Partner registriert wurde und den
        SOS-Call-Dienst nutzt. Ein Abonnent wird durch einen eindeutigen SOS-Call-Code oder durch
        eine verifizierte Telefonnummer und E-Mail identifiziert.</li>
    <li><strong>SOS-Call-Anruf</strong>: Telefonanruf eines Abonnenten über die SOS-Expat-Plattform
        an einen gelisteten Anwalt oder Sachverständigen.</li>
    <li><strong>Monatsabonnement</strong>: vom Partner monatlich geschuldete Gebühr, berechnet
        gemäß den in Artikel 4 festgelegten Parametern.</li>
    <li><strong>Partner-Konsole</strong>: dem Partner unter <em>partner-engine.sos-expat.com</em>
        und <em>sos-expat.com/partner/*</em> bereitgestellte Web-Oberfläche.</li>
</ul>

<h2>3. Laufzeit und Kündigung</h2>
<p>Dieser Vertrag tritt am <strong>{{ $vars['starts_at'] }}</strong> in Kraft
@if($vars['expires_at'])
    und endet am <strong>{{ $vars['expires_at'] }}</strong>, sofern keine ausdrückliche Verlängerung
    durch die Parteien erfolgt.
@else
    und ist auf unbestimmte Zeit geschlossen.
@endif
</p>
<p>Jede Partei kann den Vertrag jederzeit unter Einhaltung einer schriftlichen Kündigungsfrist
von dreißig (30) Tagen, mitgeteilt per Einschreiben oder E-Mail an die Rechnungsadresse, kündigen.
Die Kündigung wird mit Ablauf der Kündigungsfrist wirksam. Laufende Monatsabonnements bleiben
bis zum Ende der Kündigungsfrist geschuldet.</p>
<p>Bei schwerwiegender Vertragsverletzung durch eine Partei kann die andere Partei den Vertrag
gemäß <strong>Estnischem Schuldrechtsgesetz (Võlaõigusseadus, „VÕS") §116</strong> nach erfolgloser
Mahnung mit fünfzehntägiger (15) Frist mit sofortiger Wirkung kündigen.</p>

<h2>4. Preise und Rechnungsstellung</h2>
@php
    $hasFlat = ($vars['monthly_base_fee'] ?? 0) > 0;
    $hasPerMember = ($vars['billing_rate'] ?? 0) > 0;
    $tiers = $vars['pricing_tiers'] ?? [];
@endphp
<p>Der Partner entrichtet ein Monatsabonnement, das wie folgt berechnet wird
(Währung: <strong>{{ $vars['billing_currency'] }}</strong>):</p>
<ul>
    @if($hasFlat)
        <li>Monatliche Grundgebühr: <strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></li>
    @endif
    @if($hasPerMember)
        <li>Variable Komponente: <strong>{{ number_format((float)$vars['billing_rate'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong> pro aktivem Abonnent und Monat.</li>
    @endif
    @if(!empty($tiers))
        <li>Preisstufen:
            <table>
                <tr><th>Abonnentenbereich</th><th>Abonnement</th></tr>
                @foreach($tiers as $tier)
                    <tr>
                        <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'unbegrenzt' }}</td>
                        <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', '.') }} {{ $vars['billing_currency'] }}</td>
                    </tr>
                @endforeach
            </table>
        </li>
    @endif
    <li>Zahlungsfrist: <strong>{{ $vars['payment_terms_days'] }} Tage</strong> ab Rechnungsstellung.</li>
</ul>
<p>Rechnungen werden automatisch am 1. jedes Monats für den vorhergehenden Monat erstellt und
an die Rechnungsadresse <strong>{{ $vars['billing_email'] }}</strong> versandt. Die Zahlung kann
per Kreditkarte (Stripe-Link in der Rechnung) oder per SEPA-Überweisung an die in der Rechnung
angegebene Bankverbindung erfolgen.</p>
<p>Jeder Zahlungsverzug zieht von Rechts wegen und ohne vorherige Mahnung Verzugszinsen zum
<strong>estnischen gesetzlichen Zinssatz gemäß VÕS §113</strong> nach sich (EZB-Referenzzinssatz
zuzüglich acht Prozentpunkte für B2B-Geschäfte gemäß EU-Richtlinie 2011/7) sowie eine pauschale
Beitreibungspauschale von vierzig (40) Euro gemäß VÕS §113<sup>1</sup>.</p>

<h2>5. Verpflichtungen des Anbieters</h2>
<p>Der Anbieter verpflichtet sich:</p>
<ul>
    <li>die Partner-Konsole und die zugehörige API mit einem Verfügbarkeitsziel von 99,5%
        monatlich bereitzustellen (ausgenommen geplante Wartung mit 48-stündiger Vorankündigung);</li>
    <li>jedem Abonnenten den kostenlosen Zugang innerhalb der vereinbarten Kontingente zu einem
        Notrufdienst mit einem gelisteten Anwalt oder Sachverständigen zu ermöglichen;</li>
    <li>die Qualität der Telefonverbindung zu gewährleisten (Twilio-Infrastruktur,
        Multi-Region-Redundanz);</li>
    <li>die Vertraulichkeit der Anrufe und die ordnungsgemäße Verarbeitung personenbezogener
        Daten gemäß dem beigefügten DPA sicherzustellen.</li>
</ul>

<h2>6. Verpflichtungen des Partners</h2>
<p>Der Partner verpflichtet sich:</p>
<ul>
    <li>die ausgestellten Rechnungen pünktlich gemäß Artikel 4 zu begleichen;</li>
    <li>als Abonnenten ausschließlich volljährige natürliche Personen zu registrieren, für die er
        über eine gültige DSGVO-Rechtsgrundlage verfügt (Vertragsbeziehung, berechtigtes Interesse,
        ausdrückliche Einwilligung);</li>
    <li>seine Abonnenten darüber zu informieren, dass ihre Anrufe über die SOS-Expat-Plattform
        erfolgen und ihre Daten gemäß dem beigefügten DPA verarbeitet werden;</li>
    <li>jede betrügerische oder missbräuchliche Nutzung des Dienstes zu unterlassen
        (z.B. Weiterverkauf des Zugangs, Bereitstellung von SOS-Call-Codes an nicht identifizierte
        Dritte, Umgehung der Kontingente).</li>
</ul>

<h2>7. Haftung</h2>
<p>Der Anbieter stellt eine Verbindungsinfrastruktur bereit. Er ist nicht Vertragspartei zwischen
dem Abonnenten und dem konsultierten Anwalt oder Sachverständigen. Die während eines Anrufs
erteilten Ratschläge und Stellungnahmen liegen in der alleinigen Verantwortung des betreffenden
Anwalts oder Sachverständigen.</p>
<p>Vorbehaltlich der zwingenden Beschränkungen des <strong>VÕS §106</strong> ist die Gesamthaftung
des Anbieters aus diesem Vertrag auf den Gesamtbetrag der vom Partner in den zwölf (12) Monaten
vor dem haftungsbegründenden Ereignis gezahlten Monatsabonnements begrenzt.</p>
<p>Der Anbieter haftet nicht für mittelbare Schäden, insbesondere Betriebsausfall,
Umsatzeinbußen, Imageschaden oder Datenverlust.</p>

<h2>8. Höhere Gewalt</h2>
<p>Keine Partei haftet für eine Pflichtverletzung infolge höherer Gewalt im Sinne von
<strong>VÕS §103</strong>: außergewöhnlicher Umstand außerhalb der Kontrolle der Partei, den
sie bei Vertragsschluss nicht vernünftigerweise vorhersehen konnte und nicht vermeiden oder
überwinden kann.</p>

<h2>9. Personenbezogene Daten</h2>
<p>Die Verarbeitung personenbezogener Daten der Abonnenten unterliegt dem als Anlage zu diesen
AGB geschlossenen <strong>Auftragsverarbeitungsvertrag (DPA)</strong> gemäß Artikel 28 der
Verordnung (EU) 2016/679 (DSGVO) und dem
<strong>Estnischen Datenschutzgesetz (Isikuandmete kaitse seadus, IKS)</strong>.</p>

<h2>10. Vertraulichkeit</h2>
<p>Jede Partei verpflichtet sich, alle im Rahmen der Vertragsausführung ausgetauschten
nicht-öffentlichen Informationen streng vertraulich zu behandeln und Dritten ohne vorherige
schriftliche Zustimmung nicht offenzulegen, und zwar während der gesamten Vertragslaufzeit
und drei (3) Jahre nach Vertragsende.</p>

<h2>11. Änderungen</h2>
<p>Der Anbieter kann diese AGB ändern, indem er dem Partner jede neue Fassung mindestens dreißig
(30) Tage vor Inkrafttreten mitteilt. Lehnt der Partner die neue Fassung ab, kann er kostenlos
kündigen. Erfolgt keine Kündigung innerhalb dieser Frist, gilt die neue Fassung als angenommen.</p>

<h2>12. Anwendbares Recht und Gerichtsstand</h2>
<p>Diese AGB unterliegen <strong>estnischem Recht</strong> unter Ausschluss der Kollisionsnormen.
Das <strong>UN-Kaufrechtsübereinkommen (CISG, Wien 1980)</strong> wird ausdrücklich ausgeschlossen.</p>
<p>Alle Streitigkeiten über die Begründung, Auslegung oder Erfüllung dieser AGB unterliegen
ausschließlich der Zuständigkeit des <strong>{{ $vars['provider_jurisdiction'] }}</strong>
(Bezirksgericht Harju, Tallinn), unbeschadet einer Mehrheit von Beklagten oder einer Streitverkündung.</p>
<p>Die Parteien stimmen dieser Gerichtsstandvereinbarung gemäß <strong>Artikel 25 der Verordnung
(EU) Nr. 1215/2012</strong> (Brüssel Ia) für in der Europäischen Union ansässige Partner
ausdrücklich zu.</p>

@if(!empty($customClauses))
    <h2>13. Sonderbestimmungen</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>13.{{ $i + 1 }} {{ $clause['title'] ?? 'Sonderbestimmung' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
