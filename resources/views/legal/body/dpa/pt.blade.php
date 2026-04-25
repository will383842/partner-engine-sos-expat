<h2>Preâmbulo</h2>
<p>O presente Acordo de Tratamento de Dados («<strong>DPA</strong>») é celebrado em aplicação do
<strong>artigo 28.º do Regulamento (UE) 2016/679</strong> de 27 de abril de 2016 («<strong>RGPD</strong>»)
e da <strong>Lei estoniana de proteção de dados pessoais (Isikuandmete kaitse seadus, «IKS»)</strong>, entre:</p>
<ul>
    <li>
        <strong>{{ $vars['partner_name'] }}</strong>, doravante o «<strong>Responsável pelo tratamento</strong>»
        (o Parceiro), que determina as finalidades e os meios do tratamento dos dados pessoais dos seus Assinantes;
    </li>
    <li>
        <strong>{{ $vars['provider_legal_name'] }}</strong>, sociedade estoniana, doravante o
        «<strong>Subcontratante</strong>» (SOS-Expat), que trata estes dados por conta do Responsável
        pelo tratamento no âmbito do serviço SOS-Call.
    </li>
</ul>
<p>Encarregado de Proteção de Dados do Subcontratante: <strong>{{ $vars['provider_dpo_email'] }}</strong>.
Autoridade de controlo competente: <strong>Andmekaitse Inspektsioon (AKI)</strong>, autoridade
estoniana de proteção de dados — <em>www.aki.ee</em>.</p>

<h2>1. Objeto e duração do tratamento</h2>
<p>O Subcontratante trata os dados pessoais dos Assinantes para permitir a ligação telefónica
com um advogado ou perito referenciado, bem como para faturação, auditoria e prevenção de fraude.
O tratamento é efetuado durante toda a duração de execução do contrato principal entre as Partes.</p>

<h2>2. Categorias de dados tratados</h2>
<table>
    <tr><th>Categoria</th><th>Exemplos</th><th>Finalidade</th></tr>
    <tr>
        <td>Dados de identificação</td>
        <td>Nome, apelido, e-mail, ID CRM externo, código SOS-Call</td>
        <td>Autenticação, ligação</td>
    </tr>
    <tr>
        <td>Dados de contacto</td>
        <td>Número de telefone, idioma, país de residência</td>
        <td>Estabelecimento da chamada telefónica</td>
    </tr>
    <tr>
        <td>Dados de utilização</td>
        <td>Data, hora, duração das chamadas, tipo de perito solicitado</td>
        <td>Faturação, estatísticas agregadas, prevenção de fraude</td>
    </tr>
    <tr>
        <td>Dados técnicos</td>
        <td>Endereço IP, user-agent (apenas em operações sensíveis)</td>
        <td>Segurança, auditoria, conformidade eIDAS</td>
    </tr>
</table>

<h2>3. Categorias de titulares</h2>
<p>Os Assinantes registados pelo Parceiro (pessoas singulares maiores beneficiárias do serviço
SOS-Call), bem como os contactos de faturação e administradores designados pelo Parceiro.</p>

<h2>4. Obrigações do Subcontratante</h2>
<p>O Subcontratante compromete-se a:</p>
<ol>
    <li>Tratar os dados apenas mediante instrução documentada do Responsável, salvo obrigação legal;</li>
    <li>Garantir a confidencialidade dos dados e autorizar o seu acesso apenas ao pessoal
        sujeito a compromisso de confidencialidade;</li>
    <li>Implementar medidas técnicas e organizativas adequadas (cifragem TLS 1.3 em trânsito,
        cifragem em repouso, controlo de acesso por papéis, registo de acessos, cópias de
        segurança cifradas geo-redundantes, revisão anual de acessos);</li>
    <li>Assistir o Responsável, na medida razoável, na realização de avaliações de impacto,
        gestão de pedidos dos titulares e notificação de violações de dados;</li>
    <li>Notificar qualquer violação de dados no prazo máximo de <strong>72 horas</strong> a contar
        da sua descoberta, por e-mail para o endereço de faturação e para o EPD do Parceiro
        se designado, conforme os artigos 33.º-34.º do RGPD;</li>
    <li>Manter um registo das atividades de tratamento efetuadas por conta do Responsável
        (artigo 30.º do RGPD).</li>
</ol>

<h2>5. Subcontratantes ulteriores</h2>
<p>O Subcontratante recorre, para a execução do serviço, aos seguintes subcontratantes ulteriores:</p>
<table>
    <tr><th>Subcontratante</th><th>Serviço</th><th>Localização</th></tr>
    <tr><td>Google Cloud (Firebase)</td><td>Hospedagem aplicacional, Firestore</td><td>UE / EUA (Cláusulas Contratuais-Tipo)</td></tr>
    <tr><td>Twilio</td><td>Ligação telefónica</td><td>UE / EUA (Cláusulas Contratuais-Tipo)</td></tr>
    <tr><td>Stripe Payments Europe</td><td>Cobrança dos pagamentos</td><td>UE</td></tr>
    <tr><td>Hetzner Online GmbH</td><td>Hospedagem Partner Engine</td><td>Alemanha (UE)</td></tr>
</table>
<p>Qualquer alteração desta lista será notificada ao Responsável com pelo menos trinta (30) dias
de antecedência, período durante o qual poderá opor-se e resolver o contrato principal sem
custos em caso de desacordo.</p>

<h2>6. Transferências fora da UE</h2>
<p>Qualquer transferência de dados fora do Espaço Económico Europeu rege-se pelas
<strong>Cláusulas Contratuais-Tipo (CCT)</strong> adotadas pela Comissão Europeia em
4 de junho de 2021 (Decisão (UE) 2021/914), ou por qualquer medida equivalente prevista pelo
RGPD e reconhecida pela Andmekaitse Inspektsioon.</p>

<h2>7. Direitos dos titulares</h2>
<p>O Responsável continua a ser o ponto de contacto único dos titulares para o exercício dos
seus direitos (acesso, retificação, apagamento, limitação, oposição, portabilidade). O
Subcontratante fornece num prazo razoável, mediante pedido motivado do Responsável, os elementos
necessários à satisfação dos direitos dos titulares.</p>

<h2>8. Prazo de conservação</h2>
<ul>
    <li>Dados de identificação e contacto: duração da relação contratual + 3 anos
        (prescrição comercial estoniana, VÕS §146);</li>
    <li>Dados de utilização e faturação: 7 anos (obrigações contabilísticas estonianas,
        Raamatupidamise seadus §12);</li>
    <li>Logs técnicos de autenticação: 12 meses;</li>
    <li>Impressões SHA-256 de documentos assinados e provas de aceitação: 10 anos a contar
        da assinatura, conforme as exigências eIDAS.</li>
</ul>

<h2>9. Eliminação e restituição dos dados</h2>
<p>No termo do contrato principal, o Subcontratante procederá, à escolha do Responsável notificada
no prazo de trinta (30) dias:</p>
<ul>
    <li>quer à eliminação definitiva dos dados, salvo obrigação legal de conservação,
        no prazo de 90 dias;</li>
    <li>quer à sua restituição num formato estruturado e de uso corrente.</li>
</ul>

<h2>10. Auditoria</h2>
<p>O Responsável pode, mediante aviso escrito prévio de quinze (15) dias úteis, conduzir
(ou fazer conduzir por terceiro independente sujeito a confidencialidade) uma auditoria das
medidas técnicas e organizativas implementadas pelo Subcontratante, no limite de uma auditoria
por ano e a expensas do Responsável, salvo descoberta de incumprimento substancial.</p>

<h2>11. Responsabilidade</h2>
<p>Conforme o <strong>artigo 82.º do RGPD</strong>, cada Parte é responsável pelos danos causados
por tratamento que viole o RGPD, na proporção da sua contribuição para o incumprimento. O
presente DPA rege-se pelo direito estoniano.</p>

@if(!empty($customClauses))
    <h2>12. Estipulações particulares</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>12.{{ $i + 1 }} {{ $clause['title'] ?? 'Cláusula particular' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
