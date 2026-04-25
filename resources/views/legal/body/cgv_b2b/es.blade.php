<h2>1. Objeto y partes</h2>
<p>Las presentes Condiciones Generales de Venta (las «<strong>CGV</strong>») rigen el uso, por
parte del socio <strong>{{ $vars['partner_name'] }}</strong> (el «<strong>Socio</strong>»),
del servicio SOS-Call prestado por <strong>{{ $vars['provider_legal_name'] }}</strong>,
sociedad estonia inscrita en el Registro Mercantil de Estonia (Äriregister)
@if($vars['provider_siret'] ?? null)
    con código de registro <strong>{{ $vars['provider_siret'] }}</strong>,
@endif
@if($vars['provider_address'] ?? null)
    con domicilio social en {{ $vars['provider_address'] }},
@endif
en adelante «<strong>SOS-Expat</strong>» o el «<strong>Prestador</strong>».</p>
<p>El servicio SOS-Call permite a los abonados registrados por el Socio acceder de forma gratuita
a llamadas telefónicas de asistencia jurídica de urgencia con abogados y expertos independientes
referenciados en la plataforma del Prestador, a cambio del pago por el Socio de un abono mensual
calculado conforme a la cláusula 4.</p>

<h2>2. Definiciones</h2>
<ul>
    <li><strong>Abonado</strong>: persona física registrada por el Socio y beneficiaria del servicio
        SOS-Call. Un abonado se identifica mediante un código SOS-Call único o por su número de
        teléfono y correo electrónico verificados.</li>
    <li><strong>Llamada SOS-Call</strong>: llamada telefónica realizada por un Abonado a través de
        la plataforma SOS-Expat a un abogado o experto referenciado.</li>
    <li><strong>Abono mensual</strong>: importe debido por el Socio cada mes, calculado según los
        parámetros definidos en la cláusula 4.</li>
    <li><strong>Consola del socio</strong>: interfaz web puesta a disposición del Socio en
        <em>partner-engine.sos-expat.com</em> y <em>sos-expat.com/partner/*</em>.</li>
</ul>

<h2>3. Duración y resolución</h2>
<p>El presente contrato entra en vigor el <strong>{{ $vars['starts_at'] }}</strong>
@if($vars['expires_at'])
    y expira el <strong>{{ $vars['expires_at'] }}</strong>, salvo renovación expresa de las Partes.
@else
    y se concluye por tiempo indefinido.
@endif
</p>
<p>Cualquiera de las Partes podrá resolver el contrato en cualquier momento, mediante preaviso
escrito de treinta (30) días notificado por carta certificada o correo electrónico a la
dirección de facturación. La resolución surte efecto al término del preaviso. Los abonos
mensuales en curso permanecen exigibles hasta el final del preaviso.</p>
<p>En caso de incumplimiento grave por una de las Partes de sus obligaciones contractuales,
la otra Parte podrá resolver de pleno derecho, conforme a la <strong>Ley estonia de obligaciones
(Võlaõigusseadus, «VÕS») §116</strong>, tras requerimiento sin efecto durante quince (15) días.</p>

<h2>4. Tarificación y facturación</h2>
@php
    $hasFlat = ($vars['monthly_base_fee'] ?? 0) > 0;
    $hasPerMember = ($vars['billing_rate'] ?? 0) > 0;
    $tiers = $vars['pricing_tiers'] ?? [];
@endphp
<p>El Socio paga un abono mensual calculado según las siguientes modalidades
(divisa: <strong>{{ $vars['billing_currency'] }}</strong>):</p>
<ul>
    @if($hasFlat)
        <li>Cuota fija mensual: <strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></li>
    @endif
    @if($hasPerMember)
        <li>Componente variable: <strong>{{ number_format((float)$vars['billing_rate'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong> por abonado activo y mes.</li>
    @endif
    @if(!empty($tiers))
        <li>Tramos tarifarios:
            <table>
                <tr><th>Rango de abonados</th><th>Cuota</th></tr>
                @foreach($tiers as $tier)
                    <tr>
                        <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'ilimitado' }}</td>
                        <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', '.') }} {{ $vars['billing_currency'] }}</td>
                    </tr>
                @endforeach
            </table>
        </li>
    @endif
    <li>Plazo de pago: <strong>{{ $vars['payment_terms_days'] }} días</strong> desde la fecha de
        emisión de la factura.</li>
</ul>
<p>Las facturas se emiten automáticamente el día 1 de cada mes por el mes anterior, y se envían
a la dirección de facturación <strong>{{ $vars['billing_email'] }}</strong>. El pago puede
realizarse mediante tarjeta bancaria (enlace Stripe incluido en la factura) o transferencia
SEPA a las coordenadas indicadas en la factura.</p>
<p>Cualquier retraso en el pago conlleva, de pleno derecho y sin requerimiento previo, la aplicación
de intereses de demora al <strong>tipo legal estonio definido por VÕS §113</strong> (tipo de
referencia del BCE incrementado en ocho puntos porcentuales para transacciones B2B, conforme a
la Directiva 2011/7/UE), así como una indemnización a tanto alzado de cuarenta (40) euros por
costes de cobro, conforme a VÕS §113<sup>1</sup>.</p>

<h2>5. Compromisos del Prestador</h2>
<p>El Prestador se compromete a:</p>
<ul>
    <li>Poner a disposición la consola del socio y la API asociada con un objetivo de
        disponibilidad del 99,5% mensual (excluyendo mantenimientos planificados notificados
        con 48 horas de antelación).</li>
    <li>Permitir a cada Abonado acceder gratuitamente, dentro de las cuotas acordadas, al
        servicio de llamada de urgencia con un abogado o experto referenciado.</li>
    <li>Garantizar la calidad del servicio de conexión telefónica (infraestructura Twilio,
        redundancia multirregión).</li>
    <li>Asegurar la confidencialidad de las llamadas y el tratamiento conforme de los datos
        personales según el DPA anexo.</li>
</ul>

<h2>6. Compromisos del Socio</h2>
<p>El Socio se compromete a:</p>
<ul>
    <li>Pagar puntualmente las facturas emitidas según las modalidades de la cláusula 4.</li>
    <li>Registrar como Abonados únicamente personas físicas mayores de edad para las que disponga
        de una base jurídica de tratamiento RGPD válida (relación contractual, interés legítimo,
        consentimiento explícito).</li>
    <li>Informar a sus Abonados de que sus llamadas se realizan a través de la plataforma
        SOS-Expat y que sus datos se tratan conforme al DPA anexo.</li>
    <li>No hacer un uso fraudulento o abusivo del servicio (por ejemplo: revender el acceso,
        proporcionar códigos SOS-Call a terceros no identificados, eludir las cuotas).</li>
</ul>

<h2>7. Responsabilidad</h2>
<p>El Prestador proporciona una infraestructura de conexión. No es parte en la relación jurídica
entre el Abonado y el abogado o experto consultado. Los consejos y opiniones dados durante una
llamada son responsabilidad exclusiva del abogado o experto interviniente.</p>
<p>Sin perjuicio de las limitaciones imperativas del <strong>VÕS §106</strong>, la responsabilidad
del Prestador en virtud del presente no podrá exceder, considerados todos los conceptos de
perjuicio, el importe total de los abonos mensuales pagados por el Socio durante los doce (12)
meses anteriores al hecho generador de responsabilidad.</p>
<p>El Prestador no será responsable de ningún daño indirecto, en particular pérdida de explotación,
pérdida de cifra de negocio, daño a la imagen o pérdida de datos.</p>

<h2>8. Fuerza mayor</h2>
<p>Ninguna de las Partes será responsable de un incumplimiento de sus obligaciones derivado de
un caso de fuerza mayor según se define en <strong>VÕS §103</strong>: circunstancia extraordinaria
fuera del control de la Parte, que no podía prever razonablemente al celebrar el contrato ni
evitar o superar.</p>

<h2>9. Datos personales</h2>
<p>Las modalidades de tratamiento de los datos personales de los Abonados se rigen por el
<strong>Acuerdo de Tratamiento de Datos (DPA)</strong> celebrado en anexo de las presentes,
conforme al artículo 28 del Reglamento (UE) 2016/679 (RGPD) y a la
<strong>Ley estonia de protección de datos personales (Isikuandmete kaitse seadus, IKS)</strong>.</p>

<h2>10. Confidencialidad</h2>
<p>Cada Parte se compromete a mantener estrictamente confidencial toda información no pública
intercambiada en el marco de la ejecución del contrato, y a no divulgarla a terceros sin acuerdo
escrito previo, durante toda la duración del contrato y tres (3) años después de su término.</p>

<h2>11. Modificaciones</h2>
<p>El Prestador podrá modificar las presentes CGV notificando al Socio cualquier nueva versión
al menos treinta (30) días antes de su entrada en vigor. El Socio dispone entonces de un derecho
de resolución sin gastos si rechaza la nueva versión. A falta de resolución en el plazo, la
nueva versión se considera aceptada.</p>

<h2>12. Ley aplicable y jurisdicción</h2>
<p>Las presentes CGV se rigen por <strong>el derecho estonio</strong>, excluyendo sus normas de
conflicto de leyes. La <strong>Convención de las Naciones Unidas sobre los Contratos de
Compraventa Internacional de Mercaderías (CISG, Viena 1980)</strong> queda expresamente excluida.</p>
<p>Cualquier litigio relativo a la formación, interpretación o ejecución de las presentes se
someterá a la competencia exclusiva del <strong>{{ $vars['provider_jurisdiction'] }}</strong>
(Tribunal del Condado de Harju, Tallin), no obstante pluralidad de demandados o llamada en garantía.</p>
<p>Las Partes consienten expresamente esta elección de fuero conforme al
<strong>artículo 25 del Reglamento (UE) n.º 1215/2012</strong> (Bruselas I bis) para los socios
establecidos en la Unión Europea.</p>

@if(!empty($customClauses))
    <h2>13. Cláusulas particulares</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>13.{{ $i + 1 }} {{ $clause['title'] ?? 'Cláusula particular' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
