<h2>序言</h2>
<p>本数据处理协议（"<strong>DPA</strong>"）依据 <strong>《条例（欧盟）2016/679》</strong>
（2016 年 4 月 27 日，"<strong>GDPR</strong>"）第 28 条以及
<strong>爱沙尼亚个人数据保护法（Isikuandmete kaitse seadus，"IKS"）</strong> 缔结，
当事方为：</p>
<ul>
    <li><strong>{{ $vars['partner_name'] }}</strong>，以下简称"<strong>数据控制者</strong>"
        （合作伙伴），其确定其订户个人数据的处理目的与方式；</li>
    <li><strong>{{ $vars['provider_legal_name'] }}</strong>，爱沙尼亚公司，以下简称
        "<strong>数据处理者</strong>"（SOS-Expat），其在 SOS-Call 服务范围内代表数据控制者
        处理上述数据。</li>
</ul>
<p>处理者的数据保护官：<strong>{{ $vars['provider_dpo_email'] }}</strong>。
主管监督机构：<strong>Andmekaitse Inspektsioon（AKI）</strong>，爱沙尼亚数据保护监察局
— <em>www.aki.ee</em>。</p>

<h2>1. 处理标的与期限</h2>
<p>处理者处理订户的个人数据，以建立与列出律师或专家的电话连接，并用于开票、审计和反欺诈。
处理在双方主合同执行的全部期限内进行。</p>

<h2>2. 处理的数据类别</h2>
<table>
    <tr><th>类别</th><th>示例</th><th>目的</th></tr>
    <tr>
        <td>身份识别数据</td>
        <td>姓、名、电子邮件、外部 CRM 标识符、SOS-Call 代码</td>
        <td>订户认证、连接</td>
    </tr>
    <tr>
        <td>联系数据</td>
        <td>电话号码、语言、居住国家</td>
        <td>建立电话连接</td>
    </tr>
    <tr>
        <td>使用数据</td>
        <td>日期、时间、通话时长、所求专家类型</td>
        <td>开票、汇总统计、反欺诈</td>
    </tr>
    <tr>
        <td>技术数据</td>
        <td>IP 地址、user-agent（仅在敏感操作时）</td>
        <td>安全、审计、eIDAS 合规</td>
    </tr>
</table>

<h2>3. 数据主体类别</h2>
<p>由合作伙伴注册的订户（享受 SOS-Call 服务的成年自然人），以及合作伙伴指定的开票联系人和管理员。</p>

<h2>4. 处理者的义务</h2>
<p>处理者承诺：</p>
<ol>
    <li>仅依据数据控制者书面记录的指示处理数据，法律规定的义务除外；</li>
    <li>保障数据保密性，仅授权受保密义务约束的工作人员访问；</li>
    <li>实施适当的技术和组织措施（传输中的 TLS 1.3 加密、静态加密、基于角色的访问控制、
        访问日志、地理冗余加密备份、年度访问审查）；</li>
    <li>合理协助数据控制者进行影响评估、处理数据主体请求和数据违规通知；</li>
    <li>在发现任何数据违规事件后 <strong>72 小时</strong> 内通知，
        通过电子邮件发送至开票地址，以及合作伙伴指定的 DPO（如有），
        遵循 GDPR 第 33-34 条；</li>
    <li>保留代表数据控制者执行的处理活动记录（GDPR 第 30 条）。</li>
</ol>

<h2>5. 后续分包处理</h2>
<p>处理者为执行服务使用以下后续分包处理者：</p>
<table>
    <tr><th>分包处理者</th><th>服务</th><th>地点</th></tr>
    <tr><td>Google Cloud (Firebase)</td><td>应用托管、Firestore</td><td>欧盟 / 美国（标准合同条款）</td></tr>
    <tr><td>Twilio</td><td>电话连接</td><td>欧盟 / 美国（标准合同条款）</td></tr>
    <tr><td>Stripe Payments Europe</td><td>付款处理</td><td>欧盟</td></tr>
    <tr><td>Hetzner Online GmbH</td><td>Partner Engine 托管</td><td>德国（欧盟）</td></tr>
</table>
<p>本清单的任何修改将提前至少三十（30）天通知数据控制者，期间数据控制者可提出反对，
并在不一致时无成本终止主合同。</p>

<h2>6. 欧盟外的传输</h2>
<p>任何向欧洲经济区以外的数据传输都受
<strong>欧盟委员会于 2021 年 6 月 4 日通过的标准合同条款（SCC）</strong>
（决定（欧盟）2021/914）或 GDPR 规定并由 Andmekaitse Inspektsioon 认可的等效措施约束。</p>

<h2>7. 数据主体的权利</h2>
<p>数据控制者仍是数据主体行使其权利（访问、更正、删除、限制、反对、可携带性）的唯一联系点。
处理者在数据控制者提出合理请求后的合理时间内提供满足数据主体权利所需的要素。</p>

<h2>8. 保留期限</h2>
<ul>
    <li>身份识别和联系数据：合同关系期限 + 3 年（爱沙尼亚商业时效，VÕS 第 146 条）；</li>
    <li>使用和开票数据：7 年（爱沙尼亚会计义务，Raamatupidamise seadus 第 12 条）；</li>
    <li>技术认证日志：12 个月；</li>
    <li>已签署文件的 SHA-256 指纹和接受证据：自签署之日起 10 年，符合 eIDAS 要求。</li>
</ul>

<h2>9. 数据的删除或返还</h2>
<p>主合同到期时，处理者将根据数据控制者在三十（30）天内通知的选择：</p>
<ul>
    <li>在 90 天内永久删除数据（除法律保留义务外）；或</li>
    <li>以结构化和常用格式返还数据。</li>
</ul>

<h2>10. 审计</h2>
<p>数据控制者在书面提前通知十五（15）个工作日后，可进行（或委托独立的、受保密义务约束的
第三方进行）对处理者实施的技术和组织措施的审计，每年限一次审计，由数据控制者承担费用，
但发现重大违约时除外。</p>

<h2>11. 责任</h2>
<p>根据 <strong>GDPR 第 82 条</strong>，每方对违反 GDPR 的处理造成的损害承担责任，
按其对违约的贡献比例。本 DPA 受爱沙尼亚法律管辖。</p>

@if(!empty($customClauses))
    <h2>12. 特殊条款</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>12.{{ $i + 1 }} {{ $clause['title'] ?? '特殊条款' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
