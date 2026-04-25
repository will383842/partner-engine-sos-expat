<h2>1. Subject matter and parties</h2>
<p>These Terms and Conditions of Sale (the "<strong>Terms</strong>") govern the use, by the partner
<strong>{{ $vars['partner_name'] }}</strong> (the "<strong>Partner</strong>"), of the SOS-Call service
provided by <strong>{{ $vars['provider_legal_name'] }}</strong>, an Estonian company registered
with the Estonian Business Register (Äriregister)
@if($vars['provider_siret'] ?? null)
    under registry code <strong>{{ $vars['provider_siret'] }}</strong>,
@endif
@if($vars['provider_address'] ?? null)
    with registered office at {{ $vars['provider_address'] }},
@endif
hereinafter "<strong>SOS-Expat</strong>" or the "<strong>Provider</strong>".</p>
<p>The SOS-Call service allows subscribers enrolled by the Partner to access free emergency legal
assistance phone calls with independent lawyers and experts listed on the Provider's platform,
in exchange for the Partner paying a monthly subscription calculated according to clause 4.</p>

<h2>2. Definitions</h2>
<ul>
    <li><strong>Subscriber</strong>: a natural person enrolled by the Partner and benefiting from
        the SOS-Call service. A subscriber is identified by a unique SOS-Call code or by a
        verified phone number and email.</li>
    <li><strong>SOS-Call call</strong>: a phone call placed by a Subscriber via the SOS-Expat
        platform to a listed lawyer or expert.</li>
    <li><strong>Monthly subscription</strong>: the fee owed by the Partner each month, calculated
        according to the parameters defined in clause 4.</li>
    <li><strong>Partner console</strong>: the web interface made available to the Partner at
        <em>partner-engine.sos-expat.com</em> and <em>sos-expat.com/partner/*</em>.</li>
</ul>

<h2>3. Term and termination</h2>
<p>This contract takes effect on <strong>{{ $vars['starts_at'] }}</strong>
@if($vars['expires_at'])
    and expires on <strong>{{ $vars['expires_at'] }}</strong>, unless expressly renewed by the Parties.
@else
    and is concluded for an indefinite term.
@endif
</p>
<p>Either Party may terminate the contract at any time, subject to thirty (30) days' written notice
sent by registered letter or email to the billing address. Termination takes effect at the end
of the notice period. Monthly subscriptions in progress remain payable until the end of that period.</p>
<p>In the event of a serious breach by either Party of its contractual obligations, the other Party
may terminate the contract by operation of law, in accordance with the
<strong>Estonian Law of Obligations Act (Võlaõigusseadus, "VÕS") §116</strong>, after a
formal notice has remained without effect for fifteen (15) days.</p>

<h2>4. Pricing and invoicing</h2>
@php
    $hasFlat = ($vars['monthly_base_fee'] ?? 0) > 0;
    $hasPerMember = ($vars['billing_rate'] ?? 0) > 0;
    $tiers = $vars['pricing_tiers'] ?? [];
@endphp
<p>The Partner pays a monthly subscription calculated as follows
(currency: <strong>{{ $vars['billing_currency'] }}</strong>):</p>
<ul>
    @if($hasFlat)
        <li>Fixed monthly fee: <strong>{{ number_format((float)$vars['monthly_base_fee'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></li>
    @endif
    @if($hasPerMember)
        <li>Variable component: <strong>{{ number_format((float)$vars['billing_rate'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong> per active subscriber per month.</li>
    @endif
    @if(!empty($tiers))
        <li>Pricing tiers:
            <table>
                <tr><th>Subscriber range</th><th>Subscription</th></tr>
                @foreach($tiers as $tier)
                    <tr>
                        <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'unlimited' }}</td>
                        <td>{{ number_format((float)($tier['amount'] ?? 0), 2, '.', ',') }} {{ $vars['billing_currency'] }}</td>
                    </tr>
                @endforeach
            </table>
        </li>
    @endif
    <li>Payment term: <strong>{{ $vars['payment_terms_days'] }} days</strong> from the invoice issue date.</li>
</ul>
<p>Invoices are issued automatically on the 1st of each month for the previous month, and sent
to billing email <strong>{{ $vars['billing_email'] }}</strong>. Payment can be made by credit
card (Stripe link included in the invoice) or by SEPA bank transfer to the details indicated
on the invoice.</p>
<p>Any late payment automatically triggers, without prior notice, the application of late-payment
interest at the <strong>Estonian statutory rate defined in VÕS §113</strong> (ECB reference rate
plus eight percentage points for B2B transactions, in accordance with EU Directive 2011/7), plus
a flat-rate compensation of forty (40) euros for collection costs, in accordance with VÕS §113<sup>1</sup>.</p>

<h2>5. Provider's commitments</h2>
<p>The Provider undertakes to:</p>
<ul>
    <li>Make the partner console and associated API available with a 99.5% monthly uptime target
        (excluding scheduled maintenance notified 48 hours in advance).</li>
    <li>Allow each Subscriber to access, free of charge and within agreed quotas, the emergency
        call service with a listed lawyer or expert.</li>
    <li>Guarantee the quality of the phone connection (Twilio infrastructure, multi-region redundancy).</li>
    <li>Maintain confidentiality of calls and process personal data in accordance with the annexed DPA.</li>
</ul>

<h2>6. Partner's commitments</h2>
<p>The Partner undertakes to:</p>
<ul>
    <li>Pay invoices on time according to clause 4.</li>
    <li>Only enroll as Subscribers adult natural persons for whom it has a valid GDPR legal basis
        (contractual relationship, legitimate interest, explicit consent).</li>
    <li>Inform its Subscribers that their calls are routed through the SOS-Expat platform and
        that their data is processed in accordance with the annexed DPA.</li>
    <li>Refrain from any fraudulent or abusive use of the service (e.g. reselling access,
        sharing SOS-Call codes with unidentified third parties, circumventing quotas).</li>
</ul>

<h2>7. Liability</h2>
<p>The Provider supplies a connection infrastructure. It is not a party to the legal relationship
between the Subscriber and the consulted lawyer or expert. The advice and opinions given during
a call are the sole responsibility of the lawyer or expert involved.</p>
<p>Subject to the mandatory limitations of <strong>VÕS §106</strong>, the Provider's aggregate
liability under this contract shall not exceed the total amount of monthly subscriptions paid
by the Partner during the twelve (12) months preceding the event giving rise to liability.</p>
<p>The Provider shall not be liable for any indirect damages, including loss of profits, loss of
revenue, reputational harm or loss of data.</p>

<h2>8. Force majeure</h2>
<p>Neither Party shall be liable for any failure to perform its obligations resulting from a
force majeure event as defined in <strong>VÕS §103</strong>: an extraordinary circumstance beyond
the Party's control, which it could not reasonably foresee at the time of contracting nor avoid
or overcome.</p>

<h2>9. Personal data</h2>
<p>The processing of Subscribers' personal data is governed by the
<strong>Data Processing Agreement (DPA)</strong> annexed to and signed together with these Terms,
in accordance with Article 28 of Regulation (EU) 2016/679 (GDPR) and the
<strong>Estonian Personal Data Protection Act (Isikuandmete kaitse seadus, IKS)</strong>.</p>

<h2>10. Confidentiality</h2>
<p>Each Party undertakes to keep strictly confidential all non-public information exchanged in
the performance of this contract, and not to disclose it to third parties without prior written
consent, throughout the term of the contract and for three (3) years thereafter.</p>

<h2>11. Modifications</h2>
<p>The Provider may modify these Terms by notifying the Partner of any new version at least
thirty (30) days before its entry into force. The Partner may terminate without charge if it
refuses the new version. Failing termination within that period, the new version is deemed accepted.</p>

<h2>12. Governing law and jurisdiction</h2>
<p>These Terms are governed by <strong>Estonian law</strong>, excluding its conflict-of-laws rules.
The <strong>United Nations Convention on Contracts for the International Sale of Goods (CISG,
Vienna 1980)</strong> is expressly excluded.</p>
<p>Any dispute relating to the formation, interpretation or performance of these Terms shall be
submitted to the exclusive jurisdiction of <strong>{{ $vars['provider_jurisdiction'] }}</strong>
(Harju County Court, Tallinn), notwithstanding multiple defendants or third-party claims.</p>
<p>The Parties expressly consent to this choice of forum in accordance with
<strong>Article 25 of Regulation (EU) No 1215/2012</strong> (Brussels I bis) for partners
established in the European Union.</p>

@if(!empty($customClauses))
    <h2>13. Special clauses</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>13.{{ $i + 1 }} {{ $clause['title'] ?? 'Special clause' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
