<h2>Preámbulo</h2>
<p>El presente Acuerdo de Tratamiento de Datos («<strong>DPA</strong>») se celebra en aplicación
del <strong>artículo 28 del Reglamento (UE) 2016/679</strong> de 27 de abril de 2016 («<strong>RGPD</strong>»)
y de la <strong>Ley estonia de protección de datos personales (Isikuandmete kaitse seadus, «IKS»)</strong>, entre:</p>
<ul>
    <li>
        <strong>{{ $vars['partner_name'] }}</strong>, en adelante el «<strong>Responsable del tratamiento</strong>»
        (el Socio), que determina las finalidades y los medios del tratamiento de los datos personales de sus Abonados;
    </li>
    <li>
        <strong>{{ $vars['provider_legal_name'] }}</strong>, sociedad estonia, en adelante el
        «<strong>Encargado del tratamiento</strong>» (SOS-Expat), que trata dichos datos por cuenta
        del Responsable del tratamiento en el marco del servicio SOS-Call.
    </li>
</ul>
<p>Delegado de Protección de Datos del Encargado: <strong>{{ $vars['provider_dpo_email'] }}</strong>.
Autoridad de control competente: <strong>Andmekaitse Inspektsioon (AKI)</strong>, Inspección
estonia de protección de datos — <em>www.aki.ee</em>.</p>

<h2>1. Objeto y duración del tratamiento</h2>
<p>El Encargado trata los datos personales de los Abonados para permitir la conexión telefónica
con un abogado o experto referenciado, así como para la facturación, la auditoría y la lucha
contra el fraude. El tratamiento se efectúa durante toda la duración de ejecución del contrato
principal entre las Partes.</p>

<h2>2. Categorías de datos tratados</h2>
<table>
    <tr><th>Categoría</th><th>Ejemplos</th><th>Finalidad</th></tr>
    <tr>
        <td>Datos de identificación</td>
        <td>Nombre, apellido, email, ID CRM externo, código SOS-Call</td>
        <td>Autenticación del Abonado, conexión</td>
    </tr>
    <tr>
        <td>Datos de contacto</td>
        <td>Número de teléfono, idioma, país de residencia</td>
        <td>Establecimiento de la llamada telefónica</td>
    </tr>
    <tr>
        <td>Datos de uso</td>
        <td>Fecha, hora, duración de las llamadas, tipo de experto solicitado</td>
        <td>Facturación, estadísticas agregadas, lucha contra el fraude</td>
    </tr>
    <tr>
        <td>Datos técnicos</td>
        <td>Dirección IP, user-agent (sólo en operaciones sensibles)</td>
        <td>Seguridad, auditoría, conformidad eIDAS</td>
    </tr>
</table>

<h2>3. Categorías de interesados</h2>
<p>Los Abonados registrados por el Socio (personas físicas mayores de edad beneficiarias del
servicio SOS-Call), así como los contactos de facturación y administradores designados por el Socio.</p>

<h2>4. Obligaciones del Encargado</h2>
<p>El Encargado se compromete a:</p>
<ol>
    <li>Tratar los datos únicamente bajo instrucción documentada del Responsable, salvo
        obligación legal.</li>
    <li>Garantizar la confidencialidad de los datos y permitir su acceso únicamente al personal
        sometido a un compromiso de confidencialidad.</li>
    <li>Implementar medidas técnicas y organizativas apropiadas (cifrado TLS 1.3 en tránsito,
        cifrado en reposo, control de acceso por roles, registro de accesos, copias de seguridad
        cifradas georredundantes, revisión anual de accesos).</li>
    <li>Asistir al Responsable, en la medida razonable, en la realización de evaluaciones de
        impacto (EIPD), la gestión de solicitudes de los interesados y la notificación de
        violaciones de datos.</li>
    <li>Notificar cualquier violación de datos en un plazo máximo de <strong>72 horas</strong>
        desde su descubrimiento, por correo electrónico a la dirección de facturación y al DPD
        del Socio si está designado, conforme a los artículos 33-34 del RGPD.</li>
    <li>Mantener un registro de las actividades de tratamiento efectuadas por cuenta del
        Responsable (artículo 30 del RGPD).</li>
</ol>

<h2>5. Subencargados</h2>
<p>El Encargado recurre, para la ejecución del servicio, a los siguientes subencargados:</p>
<table>
    <tr><th>Subencargado</th><th>Servicio</th><th>Localización</th></tr>
    <tr><td>Google Cloud (Firebase)</td><td>Hospedaje aplicativo, Firestore</td><td>UE / EE.UU. (Cláusulas Contractuales Tipo)</td></tr>
    <tr><td>Twilio</td><td>Conexión telefónica</td><td>UE / EE.UU. (Cláusulas Contractuales Tipo)</td></tr>
    <tr><td>Stripe Payments Europe</td><td>Cobro de los pagos</td><td>UE</td></tr>
    <tr><td>Hetzner Online GmbH</td><td>Hospedaje Partner Engine</td><td>Alemania (UE)</td></tr>
</table>
<p>Cualquier modificación de esta lista será notificada al Responsable con al menos treinta (30)
días de antelación, periodo durante el cual podrá oponerse y resolver el contrato principal sin
gastos en caso de desacuerdo.</p>

<h2>6. Transferencias fuera de la UE</h2>
<p>Toda transferencia de datos fuera del Espacio Económico Europeo se rige por las
<strong>Cláusulas Contractuales Tipo (CCT)</strong> adoptadas por la Comisión Europea
el 4 de junio de 2021 (Decisión (UE) 2021/914), o por cualquier medida equivalente prevista
por el RGPD y reconocida por la Andmekaitse Inspektsioon.</p>

<h2>7. Derechos de los interesados</h2>
<p>El Responsable del tratamiento sigue siendo el punto de contacto único de los interesados
para el ejercicio de sus derechos (acceso, rectificación, supresión, limitación, oposición,
portabilidad). El Encargado proporciona, en un plazo razonable y previa solicitud motivada del
Responsable, los elementos necesarios para satisfacer los derechos de los interesados.</p>

<h2>8. Plazo de conservación</h2>
<ul>
    <li>Datos de identificación y contacto: duración de la relación contractual + 3 años
        (prescripción comercial estonia, VÕS §146).</li>
    <li>Datos de uso y facturación: 7 años (obligaciones contables estonias,
        Raamatupidamise seadus §12).</li>
    <li>Logs técnicos de autenticación: 12 meses.</li>
    <li>Huellas SHA-256 de documentos firmados y pruebas de aceptación: 10 años desde la firma,
        conforme a las exigencias eIDAS.</li>
</ul>

<h2>9. Supresión y restitución de los datos</h2>
<p>A la expiración del contrato principal, el Encargado procederá, a elección del Responsable
notificada en el plazo de treinta (30) días:</p>
<ul>
    <li>bien a la supresión definitiva de los datos, salvo obligación legal de conservación,
        en un plazo de 90 días;</li>
    <li>bien a su restitución en un formato estructurado y de uso común.</li>
</ul>

<h2>10. Auditoría</h2>
<p>El Responsable podrá, previo aviso escrito de quince (15) días hábiles, llevar a cabo
(o hacer realizar por un tercero independiente sometido a confidencialidad) una auditoría de
las medidas técnicas y organizativas implementadas por el Encargado, dentro del límite de una
auditoría por año y a expensas del Responsable, salvo descubrimiento de un incumplimiento sustancial.</p>

<h2>11. Responsabilidad</h2>
<p>Conforme al <strong>artículo 82 del RGPD</strong>, cada Parte es responsable de los daños
causados por un tratamiento que infrinja el RGPD, en proporción a su contribución al
incumplimiento. El presente DPA se rige por el derecho estonio.</p>

@if(!empty($customClauses))
    <h2>12. Estipulaciones particulares</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>12.{{ $i + 1 }} {{ $clause['title'] ?? 'Cláusula particular' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
