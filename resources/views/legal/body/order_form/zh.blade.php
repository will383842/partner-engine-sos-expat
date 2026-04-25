<h2>1. 当事方</h2>
<table>
    <tr>
        <th style="width: 35%;">提供商（SOS-Expat）</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            爱沙尼亚有限责任公司（Osaühing — OÜ）<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) 爱沙尼亚商业登记处（Äriregister）：{{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) 欧盟增值税号：{{ $vars['provider_vat'] }}<br>@endif
            电子邮件：{{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>合作伙伴</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            内部标识符：{{ $vars['partner_firebase_id'] }}<br>
            开票邮箱：{{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) 协议引用：{{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. 承诺期</h2>
<table>
    <tr><th>生效日期</th><td>{{ $vars['starts_at'] }}</td></tr>
    <tr><th>到期日期</th>
        <td>{{ $vars['expires_at'] ?: '无固定期限 — 可提前 30 天通知终止' }}</td></tr>
</table>

<h2>3. 经济模式</h2>
<p>本订单选择以下经济模式：
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call（B2B 月度订阅）</strong> — 合作伙伴支付月度订阅费，其订户免费通话。
@elseif($vars['economic_model'] === 'commission')
    <strong>每次通话佣金</strong> — 合作伙伴对其推荐用户的每次通话获得佣金。
@elseif($vars['economic_model'] === 'hybrid')
    <strong>混合</strong> — 月度订阅与佣金的结合。
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. SOS-Call 定价</h2>
    <table>
        <tr><th>计费货币</th><td><strong>{{ $vars['billing_currency'] }}</strong></td></tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr><th>固定月费</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr><th>每位活跃订户每月</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        <tr><th>付款期限</th><td>月末后 {{ $vars['payment_terms_days'] }} 天</td></tr>
        <tr><th>允许的通话类型</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') 仅 Expat @break
                    @case('lawyer_only') 仅律师 @break
                    @default Expat 和律师
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>分级定价（按订户数量的固定费用）</h3>
        <table>
            <tr><th>活跃订户</th><th>月度订阅</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? '无限' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, '.', ',') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. 配额与限制</h2>
<table>
    <tr><th>最大订户数量</th>
        <td>@if(($vars['max_subscribers'] ?? 0) > 0) {{ $vars['max_subscribers'] }} @else 无限 @endif</td></tr>
    <tr><th>默认订阅时长</th>
        <td>@if(($vars['default_subscriber_duration_days'] ?? 0) > 0) {{ $vars['default_subscriber_duration_days'] }} 天 @else 永久（直至合同到期） @endif</td></tr>
    <tr><th>最长订阅时长</th>
        <td>@if(($vars['max_subscriber_duration_days'] ?? 0) > 0) {{ $vars['max_subscriber_duration_days'] }} 天 @else 无上限 @endif</td></tr>
</table>

<h2>6. 通用条款引用</h2>
<div class="important">
    本订单受附在本合同后并共同签署的 <strong>SOS-Call B2B 通用销售条款</strong>
    和 <strong>数据处理协议（DPA）</strong> 管辖，依据 <strong>爱沙尼亚法律</strong>。
    如有冲突，本订单的特殊规定优先于通用条款，但与个人数据保护相关的强制性规定
    （GDPR + 爱沙尼亚 IKS）在任何情况下均优先适用。
</div>

@if(!empty($customClauses))
    <h2>7. 特别条款</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? '特殊条款' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
