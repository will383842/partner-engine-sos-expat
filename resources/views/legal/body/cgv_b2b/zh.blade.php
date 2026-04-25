<h2>1. 标的与各方</h2>
<p>本通用销售条款（"<strong>条款</strong>"）规范合作伙伴 <strong>{{ $vars['partner_name'] }}</strong>
（"<strong>合作伙伴</strong>"）对由 <strong>{{ $vars['provider_legal_name'] }}</strong>
（一家在爱沙尼亚商业登记处（Äriregister）注册的爱沙尼亚公司
@if($vars['provider_siret'] ?? null)
    ，登记编号 <strong>{{ $vars['provider_siret'] }}</strong>
@endif
@if($vars['provider_address'] ?? null)
    ，注册地址为 {{ $vars['provider_address'] }}
@endif
，以下简称"<strong>SOS-Expat</strong>"或"<strong>提供商</strong>"）提供的 SOS-Call 服务的使用。</p>
<p>SOS-Call 服务允许合作伙伴注册的订户免费访问与提供商平台上列出的独立律师和专家进行的紧急法律援助电话，
合作伙伴需根据第 4 条计算并支付月度订阅费用。</p>

<h2>2. 定义</h2>
<ul>
    <li><strong>订户</strong>：由合作伙伴注册并享受 SOS-Call 服务的自然人。订户通过唯一的 SOS-Call 代码或经验证的电话和电子邮件识别。</li>
    <li><strong>SOS-Call 通话</strong>：订户通过 SOS-Expat 平台向列出的律师或专家发起的电话。</li>
    <li><strong>月度订阅</strong>：合作伙伴每月应付的费用，根据第 4 条规定的参数计算。</li>
    <li><strong>合作伙伴控制台</strong>：在 <em>partner-engine.sos-expat.com</em> 和 <em>sos-expat.com/partner/*</em> 提供给合作伙伴的网页界面。</li>
</ul>

<h2>3. 期限与终止</h2>
<p>本合同自 <strong>{{ $vars['starts_at'] }}</strong> 起生效，
@if($vars['expires_at'])
    并于 <strong>{{ $vars['expires_at'] }}</strong> 到期，除非各方明示续签。
@else
    无固定期限。
@endif
</p>
<p>任何一方可随时终止合同，须以挂号信或电子邮件向计费地址发出三十（30）天的书面通知。
终止于通知期届满时生效。进行中的月度订阅在通知期结束前仍应付清。</p>
<p>如一方严重违反合同义务，另一方可依据
<strong>爱沙尼亚债务法（Võlaõigusseadus，"VÕS"）第 116 条</strong>，
经过十五（15）天催告未果后，依法终止合同。</p>

<h2>4. 定价与开票</h2>
@php
    $hasFlat = ($vars['monthly_base_fee'] ?? 0) > 0;
    $hasPerMember = ($vars['billing_rate'] ?? 0) > 0;
    $tiers = $vars['pricing_tiers'] ?? [];
@endphp
<p>合作伙伴按以下方式支付月度订阅费用（货币：<strong>{{ $vars['billing_currency'] }}</strong>）：</p>
<ul>
    @if($hasFlat)
        <li>固定月费：<strong>{{ number_format((float)$vars['monthly_base_fee'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></li>
    @endif
    @if($hasPerMember)
        <li>可变部分：每位活跃订户每月 <strong>{{ number_format((float)$vars['billing_rate'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong>。</li>
    @endif
    @if(!empty($tiers))
        <li>分级定价：
            <table>
                <tr><th>订户范围</th><th>订阅费</th></tr>
                @foreach($tiers as $tier)
                    <tr>
                        <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? '无限' }}</td>
                        <td>{{ number_format((float)($tier['amount'] ?? 0), 2, '.', ',') }} {{ $vars['billing_currency'] }}</td>
                    </tr>
                @endforeach
            </table>
        </li>
    @endif
    <li>付款期限：自发票开具日起 <strong>{{ $vars['payment_terms_days'] }} 天</strong>。</li>
</ul>
<p>发票于每月 1 日就上月自动开具，并发送至计费邮箱
<strong>{{ $vars['billing_email'] }}</strong>。可通过信用卡（发票中包含的 Stripe 链接）
或 SEPA 银行转账（按发票上指明的银行信息）付款。</p>
<p>任何逾期付款将依法且无需事先催告地按
<strong>爱沙尼亚 VÕS 第 113 条规定的法定利率</strong>
（欧洲中央银行参考利率加上 B2B 交易的 8 个百分点，依据欧盟指令 2011/7/EU）
计算逾期利息，并根据 VÕS 第 113<sup>1</sup> 条加收 40 欧元的固定追偿费用补偿。</p>

<h2>5. 提供商的承诺</h2>
<p>提供商承诺：</p>
<ul>
    <li>提供合作伙伴控制台及关联 API，月度可用性目标为 99.5%（不包括提前 48 小时通知的计划维护）；</li>
    <li>允许每位订户在约定配额内免费访问与列出律师或专家的紧急通话服务；</li>
    <li>保障电话连接服务质量（Twilio 基础设施，多区域冗余）；</li>
    <li>保证通话保密性，并按附件 DPA 合规处理个人数据。</li>
</ul>

<h2>6. 合作伙伴的承诺</h2>
<p>合作伙伴承诺：</p>
<ul>
    <li>按第 4 条规定按时支付开具的发票；</li>
    <li>仅注册其拥有有效 GDPR 处理法律依据（合同关系、合法利益、明示同意）的成年自然人为订户；</li>
    <li>告知其订户其通话通过 SOS-Expat 平台进行，其数据按附件 DPA 处理；</li>
    <li>不得欺诈或滥用服务（例如：转售访问权限、向未识别的第三方提供 SOS-Call 代码、规避配额）。</li>
</ul>

<h2>7. 责任</h2>
<p>提供商提供连接基础设施。其并非订户与所咨询律师或专家之间法律关系的当事人。
通话期间提供的建议和意见由所介入的律师或专家独自负责。</p>
<p>受 <strong>VÕS 第 106 条</strong> 强制性限制，提供商根据本条款承担的总责任，
就所有损害类别合计，不得超过合作伙伴在引起责任的事件之前十二（12）个月内
支付的月度订阅总额。</p>
<p>提供商不对任何间接损害承担责任，特别是经营损失、营业额损失、形象损害或数据丢失。</p>

<h2>8. 不可抗力</h2>
<p>任何一方均不对因 <strong>VÕS 第 103 条</strong> 定义的不可抗力造成的义务履行违反承担责任：
即超出该方控制的非常情况，订立合同时无法合理预见，亦无法避免或克服。</p>

<h2>9. 个人数据</h2>
<p>订户个人数据的处理方式由本条款附件签署的 <strong>数据处理协议（DPA）</strong> 规定，
依据《条例（欧盟）2016/679》（GDPR）第 28 条以及
<strong>爱沙尼亚个人数据保护法（Isikuandmete kaitse seadus，IKS）</strong>。</p>

<h2>10. 保密</h2>
<p>每方承诺在合同有效期内及合同终止后三（3）年内，对在合同执行过程中交换的所有非公开信息
严格保密，未经事先书面同意不得向第三方披露。</p>

<h2>11. 修改</h2>
<p>提供商可修改本条款，须在新版本生效至少三十（30）天前通知合作伙伴。
合作伙伴如拒绝新版本，则有权免费终止合同。如未在期限内终止，新版本视为接受。</p>

<h2>12. 适用法律与管辖</h2>
<p>本条款受 <strong>爱沙尼亚法律</strong> 管辖，排除其法律冲突规则。
明确排除 <strong>《联合国国际货物销售合同公约》（CISG，维也纳 1980）</strong>。</p>
<p>就本条款的订立、解释或履行产生的任何争议，将由 <strong>{{ $vars['provider_jurisdiction'] }}</strong>
（哈尔尤县法院，塔林）专属管辖，不论被告人数或第三方追索。</p>
<p>对于在欧盟成立的合作伙伴，各方依据 <strong>《条例（欧盟）第 1215/2012 号》第 25 条</strong>
（布鲁塞尔 I bis）明确同意此管辖选择。</p>

@if(!empty($customClauses))
    <h2>13. 特殊条款</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>13.{{ $i + 1 }} {{ $clause['title'] ?? '特殊条款' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
