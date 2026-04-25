<h2>Recitals</h2>
<p>This Data Processing Agreement (the "<strong>DPA</strong>") is concluded pursuant to
<strong>Article 28 of Regulation (EU) 2016/679</strong> of 27 April 2016 (the "<strong>GDPR</strong>")
and the <strong>Estonian Personal Data Protection Act (Isikuandmete kaitse seadus, "IKS")</strong>,
between:</p>
<ul>
    <li>
        <strong>{{ $vars['partner_name'] }}</strong>, the "<strong>Controller</strong>" (the Partner),
        which determines the purposes and means of the processing of its Subscribers' personal data;
    </li>
    <li>
        <strong>{{ $vars['provider_legal_name'] }}</strong>, an Estonian company, the
        "<strong>Processor</strong>" (SOS-Expat), which processes such data on behalf of the
        Controller as part of the SOS-Call service.
    </li>
</ul>
<p>Processor's Data Protection Officer: <strong>{{ $vars['provider_dpo_email'] }}</strong>.
Competent supervisory authority: <strong>Andmekaitse Inspektsioon (AKI)</strong>, the Estonian
Data Protection Inspectorate — <em>www.aki.ee</em>.</p>

<h2>1. Subject matter and duration</h2>
<p>The Processor processes Subscribers' personal data to enable phone connection with a listed
lawyer or expert, as well as for billing, audit and fraud prevention. Processing takes place
throughout the term of the main contract between the Parties.</p>

<h2>2. Categories of data processed</h2>
<table>
    <tr><th>Category</th><th>Examples</th><th>Purpose</th></tr>
    <tr>
        <td>Identification data</td>
        <td>Name, surname, email, external CRM ID, SOS-Call code</td>
        <td>Subscriber authentication, call routing</td>
    </tr>
    <tr>
        <td>Contact data</td>
        <td>Phone number, language, country of residence</td>
        <td>Establishment of the phone call</td>
    </tr>
    <tr>
        <td>Usage data</td>
        <td>Date, time, call duration, type of expert consulted</td>
        <td>Billing, aggregated statistics, fraud prevention</td>
    </tr>
    <tr>
        <td>Technical data</td>
        <td>IP address, user-agent (only on sensitive operations)</td>
        <td>Security, audit, eIDAS compliance</td>
    </tr>
</table>

<h2>3. Categories of data subjects</h2>
<p>Subscribers enrolled by the Partner (adult natural persons benefiting from the SOS-Call
service), together with billing contacts and administrators designated by the Partner.</p>

<h2>4. Processor's obligations</h2>
<p>The Processor undertakes to:</p>
<ol>
    <li>Process the data only on documented instructions from the Controller, save where
        required by law.</li>
    <li>Ensure data confidentiality and grant access only to staff bound by confidentiality
        obligations.</li>
    <li>Implement appropriate technical and organisational measures (TLS 1.3 in transit,
        at-rest encryption, role-based access control, access logging, georeplicated encrypted
        backups, annual access review).</li>
    <li>Reasonably assist the Controller in carrying out data protection impact assessments,
        handling data subject requests, and notifying personal data breaches.</li>
    <li>Notify any data breach within <strong>72 hours</strong> of discovery, by email to the
        billing address and to the Partner's DPO if designated, in accordance with GDPR
        Articles 33-34.</li>
    <li>Maintain a record of processing activities carried out on behalf of the Controller
        (GDPR Article 30).</li>
</ol>

<h2>5. Sub-processors</h2>
<p>The Processor uses the following sub-processors to deliver the service:</p>
<table>
    <tr><th>Sub-processor</th><th>Service</th><th>Location</th></tr>
    <tr><td>Google Cloud (Firebase)</td><td>Application hosting, Firestore</td><td>EU / US (Standard Contractual Clauses)</td></tr>
    <tr><td>Twilio</td><td>Phone connection</td><td>EU / US (Standard Contractual Clauses)</td></tr>
    <tr><td>Stripe Payments Europe</td><td>Payment processing</td><td>EU</td></tr>
    <tr><td>Hetzner Online GmbH</td><td>Partner Engine hosting</td><td>Germany (EU)</td></tr>
</table>
<p>Any change to this list will be notified to the Controller at least thirty (30) days in
advance, during which time the Controller may object and terminate the main contract free of
charge in case of disagreement.</p>

<h2>6. Transfers outside the EU</h2>
<p>Any transfer of data outside the European Economic Area is governed by the
<strong>Standard Contractual Clauses (SCC)</strong> adopted by the European Commission on
4 June 2021 (Commission Decision (EU) 2021/914), or any equivalent measure permitted by GDPR
and recognised by the Andmekaitse Inspektsioon.</p>

<h2>7. Data subjects' rights</h2>
<p>The Controller remains the sole point of contact for data subjects exercising their rights
(access, rectification, erasure, restriction, objection, portability). The Processor shall,
within a reasonable time and upon reasoned request, provide the elements necessary to satisfy
data subjects' rights.</p>

<h2>8. Retention period</h2>
<ul>
    <li>Identification and contact data: term of the contractual relationship + 3 years
        (Estonian commercial limitation period, VÕS §146).</li>
    <li>Usage and billing data: 7 years (Estonian accounting obligations,
        Raamatupidamise seadus §12).</li>
    <li>Technical authentication logs: 12 months.</li>
    <li>SHA-256 hashes of signed documents and acceptance evidence: 10 years from signature,
        in accordance with eIDAS requirements.</li>
</ul>

<h2>9. Deletion or return of data</h2>
<p>Upon expiry of the main contract, the Processor will, at the Controller's choice notified
within thirty (30) days:</p>
<ul>
    <li>permanently delete the data, save where required to retain by law, within 90 days; or</li>
    <li>return the data in a structured, commonly used format.</li>
</ul>

<h2>10. Audit</h2>
<p>The Controller may, after fifteen (15) business days' prior written notice, conduct (or have
an independent third party bound by confidentiality conduct) an audit of the technical and
organisational measures implemented by the Processor, limited to one audit per year and at the
Controller's expense, save where a substantial breach is uncovered.</p>

<h2>11. Liability</h2>
<p>In accordance with <strong>GDPR Article 82</strong>, each Party is liable for damage caused
by processing in breach of GDPR, in proportion to its contribution to the breach. This DPA is
governed by Estonian law.</p>

@if(!empty($customClauses))
    <h2>12. Special provisions</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>12.{{ $i + 1 }} {{ $clause['title'] ?? 'Special clause' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
