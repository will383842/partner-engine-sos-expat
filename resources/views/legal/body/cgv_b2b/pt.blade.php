<h2>1. Objeto e partes</h2>
<p>As presentes Condições Gerais de Venda (as «<strong>CGV</strong>») regem a utilização,
pelo parceiro <strong>{{ $vars['partner_name'] }}</strong> (o «<strong>Parceiro</strong>»),
do serviço SOS-Call disponibilizado por <strong>{{ $vars['provider_legal_name'] }}</strong>,
sociedade estoniana inscrita no Registo Comercial da Estónia (Äriregister)
@if($vars['provider_siret'] ?? null)
    sob o código de registo <strong>{{ $vars['provider_siret'] }}</strong>,
@endif
@if($vars['provider_address'] ?? null)
    com sede em {{ $vars['provider_address'] }},
@endif
doravante «<strong>SOS-Expat</strong>» ou o «<strong>Prestador</strong>».</p>
<p>O serviço SOS-Call permite aos assinantes registados pelo Parceiro aceder gratuitamente
a chamadas telefónicas de assistência jurídica de urgência com advogados e peritos independentes
referenciados na plataforma do Prestador, mediante o pagamento pelo Parceiro de uma assinatura
mensal calculada conforme a cláusula 4.</p>

<h2>2. Definições</h2>
<ul>
    <li><strong>Assinante</strong>: pessoa singular registada pelo Parceiro e beneficiária do
        serviço SOS-Call. Identificado por um código SOS-Call único ou por número de telefone
        e e-mail verificados.</li>
    <li><strong>Chamada SOS-Call</strong>: chamada telefónica realizada por um Assinante através
        da plataforma SOS-Expat para um advogado ou perito referenciado.</li>
    <li><strong>Assinatura mensal</strong>: valor devido pelo Parceiro mensalmente, calculado
        de acordo com os parâmetros definidos na cláusula 4.</li>
    <li><strong>Consola do parceiro</strong>: interface web disponibilizada ao Parceiro em
        <em>partner-engine.sos-expat.com</em> e <em>sos-expat.com/partner/*</em>.</li>
</ul>

<h2>3. Duração e resolução</h2>
<p>O presente contrato produz efeitos a partir de <strong>{{ $vars['starts_at'] }}</strong>
@if($vars['expires_at'])
    e expira em <strong>{{ $vars['expires_at'] }}</strong>, salvo renovação expressa pelas Partes.
@else
    e é celebrado por tempo indeterminado.
@endif
</p>
<p>Qualquer das Partes pode resolver o contrato a qualquer momento, mediante pré-aviso escrito
de trinta (30) dias notificado por carta registada ou e-mail para o endereço de faturação.
A resolução produz efeitos no termo do pré-aviso. As assinaturas mensais em curso permanecem
devidas até ao final do pré-aviso.</p>
<p>Em caso de incumprimento grave por uma das Partes das suas obrigações contratuais, a outra Parte
pode resolver de pleno direito, conforme a <strong>Lei estoniana das obrigações
(Võlaõigusseadus, «VÕS») §116</strong>, após interpelação sem efeito durante quinze (15) dias.</p>

<h2>4. Tarifário e faturação</h2>
@php
    $hasFlat = ($vars['monthly_base_fee'] ?? 0) > 0;
    $hasPerMember = ($vars['billing_rate'] ?? 0) > 0;
    $tiers = $vars['pricing_tiers'] ?? [];
@endphp
<p>O Parceiro paga uma assinatura mensal calculada conforme as seguintes modalidades
(moeda: <strong>{{ $vars['billing_currency'] }}</strong>):</p>
<ul>
    @if($hasFlat)
        <li>Componente fixa mensal: <strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong></li>
    @endif
    @if($hasPerMember)
        <li>Componente variável: <strong>{{ number_format((float)$vars['billing_rate'], 2, ',', '.') }} {{ $vars['billing_currency'] }}</strong> por assinante ativo e por mês.</li>
    @endif
    @if(!empty($tiers))
        <li>Escalões tarifários:
            <table>
                <tr><th>Intervalo de assinantes</th><th>Assinatura</th></tr>
                @foreach($tiers as $tier)
                    <tr>
                        <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'ilimitado' }}</td>
                        <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', '.') }} {{ $vars['billing_currency'] }}</td>
                    </tr>
                @endforeach
            </table>
        </li>
    @endif
    <li>Prazo de pagamento: <strong>{{ $vars['payment_terms_days'] }} dias</strong> a contar da
        data de emissão da fatura.</li>
</ul>
<p>As faturas são emitidas automaticamente no dia 1 de cada mês relativamente ao mês anterior
e enviadas para o endereço de faturação <strong>{{ $vars['billing_email'] }}</strong>. O pagamento
pode ser efetuado por cartão bancário (link Stripe incluído na fatura) ou por transferência SEPA
para os dados indicados na fatura.</p>
<p>Qualquer atraso no pagamento implica, de pleno direito e sem interpelação prévia, a aplicação
de juros de mora à <strong>taxa legal estoniana definida pelo VÕS §113</strong> (taxa de
referência do BCE acrescida de oito pontos percentuais para transações B2B, conforme a Diretiva
2011/7/UE), bem como uma indemnização fixa de quarenta (40) euros pelos custos de cobrança,
conforme o VÕS §113<sup>1</sup>.</p>

<h2>5. Compromissos do Prestador</h2>
<p>O Prestador compromete-se a:</p>
<ul>
    <li>Disponibilizar a consola do parceiro e a API associada com um objetivo de disponibilidade
        de 99,5% mensal (excluindo manutenções planeadas notificadas com 48 horas de antecedência);</li>
    <li>Permitir a cada Assinante o acesso gratuito, no limite das quotas acordadas, ao serviço
        de chamada de urgência com um advogado ou perito referenciado;</li>
    <li>Garantir a qualidade do serviço de ligação telefónica (infraestrutura Twilio,
        redundância multirregional);</li>
    <li>Assegurar a confidencialidade das chamadas e o tratamento conforme dos dados pessoais
        nos termos do DPA anexo.</li>
</ul>

<h2>6. Compromissos do Parceiro</h2>
<p>O Parceiro compromete-se a:</p>
<ul>
    <li>Pagar atempadamente as faturas emitidas conforme as modalidades da cláusula 4;</li>
    <li>Registar como Assinantes apenas pessoas singulares maiores para as quais disponha de uma
        base jurídica de tratamento RGPD válida (relação contratual, interesse legítimo,
        consentimento explícito);</li>
    <li>Informar os seus Assinantes de que as suas chamadas são efetuadas através da plataforma
        SOS-Expat e que os seus dados são tratados conforme o DPA anexo;</li>
    <li>Não fazer uso fraudulento ou abusivo do serviço (por exemplo: revender o acesso, fornecer
        códigos SOS-Call a terceiros não identificados, contornar as quotas).</li>
</ul>

<h2>7. Responsabilidade</h2>
<p>O Prestador disponibiliza uma infraestrutura de ligação. Não é parte na relação jurídica
entre o Assinante e o advogado ou perito consultado. Os conselhos e pareceres prestados durante
uma chamada são da exclusiva responsabilidade do advogado ou perito interveniente.</p>
<p>Sob reserva das limitações imperativas do <strong>VÕS §106</strong>, a responsabilidade do
Prestador ao abrigo das presentes não pode exceder, considerados todos os tipos de prejuízo, o
montante total das assinaturas mensais pagas pelo Parceiro nos doze (12) meses anteriores ao
facto gerador de responsabilidade.</p>
<p>O Prestador não pode ser responsabilizado por qualquer dano indireto, nomeadamente perda de
exploração, perda de volume de negócios, dano à imagem ou perda de dados.</p>

<h2>8. Força maior</h2>
<p>Nenhuma das Partes será responsável por incumprimento de obrigações decorrente de caso de
força maior nos termos do <strong>VÕS §103</strong>: circunstância extraordinária fora do
controlo da Parte, que não podia razoavelmente prever na celebração do contrato nem evitar ou
ultrapassar.</p>

<h2>9. Dados pessoais</h2>
<p>As modalidades de tratamento dos dados pessoais dos Assinantes regem-se pelo
<strong>Acordo de Tratamento de Dados (DPA)</strong> celebrado em anexo às presentes, conforme
o artigo 28 do Regulamento (UE) 2016/679 (RGPD) e a
<strong>Lei estoniana de proteção de dados pessoais (Isikuandmete kaitse seadus, IKS)</strong>.</p>

<h2>10. Confidencialidade</h2>
<p>Cada Parte compromete-se a manter estritamente confidenciais todas as informações não públicas
trocadas no âmbito da execução do contrato, e a não as divulgar a terceiros sem acordo escrito
prévio, durante toda a duração do contrato e três (3) anos após o seu termo.</p>

<h2>11. Modificações</h2>
<p>O Prestador pode alterar as presentes CGV notificando o Parceiro de qualquer nova versão pelo
menos trinta (30) dias antes da sua entrada em vigor. O Parceiro tem então direito de resolução
sem custos se recusar a nova versão. Na falta de resolução no prazo previsto, a nova versão
considera-se aceite.</p>

<h2>12. Lei aplicável e jurisdição</h2>
<p>As presentes CGV regem-se pelo <strong>direito estoniano</strong>, com exclusão das suas regras
de conflito de leis. A <strong>Convenção das Nações Unidas sobre os Contratos de Compra e Venda
Internacional de Mercadorias (CISG, Viena 1980)</strong> é expressamente excluída.</p>
<p>Qualquer litígio relativo à formação, interpretação ou execução das presentes será submetido
à competência exclusiva do <strong>{{ $vars['provider_jurisdiction'] }}</strong>
(Tribunal do Condado de Harju, Tallinn), não obstante a pluralidade de demandados ou chamamento
em garantia.</p>
<p>As Partes consentem expressamente nesta eleição de foro nos termos do
<strong>artigo 25.º do Regulamento (UE) n.º 1215/2012</strong> (Bruxelas I-A) para os parceiros
estabelecidos na União Europeia.</p>

@if(!empty($customClauses))
    <h2>13. Cláusulas particulares</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>13.{{ $i + 1 }} {{ $clause['title'] ?? 'Cláusula particular' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
