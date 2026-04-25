<h2>1. الأطراف</h2>
<table>
    <tr>
        <th style="width: 35%;">المُقدِّم (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            شركة إستونية ذات مسؤولية محدودة (Osaühing — OÜ)<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) السجل التجاري الإستوني (Äriregister): {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) رقم ضريبة القيمة المضافة الأوروبي: {{ $vars['provider_vat'] }}<br>@endif
            البريد الإلكتروني: {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>الشريك</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            المعرف الداخلي: {{ $vars['partner_firebase_id'] }}<br>
            بريد الفوترة: {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) مرجع الاتفاقية: {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. فترة الالتزام</h2>
<table>
    <tr><th>تاريخ السريان</th><td>{{ $vars['starts_at'] }}</td></tr>
    <tr><th>تاريخ الانتهاء</th>
        <td>{{ $vars['expires_at'] ?: 'مدة غير محددة — قابلة للإنهاء بإخطار 30 يوماً' }}</td></tr>
</table>

<h2>3. النموذج الاقتصادي</h2>
<p>تعتمد طلبية الخدمة هذه النموذج الاقتصادي التالي:
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (اشتراك شهري B2B)</strong> — يدفع الشريك اشتراكاً شهرياً، ويتصل مشتركوه مجاناً.
@elseif($vars['economic_model'] === 'commission')
    <strong>عمولة لكل مكالمة</strong> — يحصل الشريك على عمولة مقابل كل مكالمة من أحد مستخدميه المُحالين.
@elseif($vars['economic_model'] === 'hybrid')
    <strong>هجين</strong> — مزيج من اشتراك شهري وعمولة.
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. تسعير SOS-Call</h2>
    <table>
        <tr><th>عملة الفوترة</th><td><strong>{{ $vars['billing_currency'] }}</strong></td></tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr><th>الرسم الثابت الشهري</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr><th>لكل مشترك نشط شهرياً</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, '.', ',') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        <tr><th>أجل السداد</th><td>{{ $vars['payment_terms_days'] }} يوماً نهاية الشهر</td></tr>
        <tr><th>أنواع المكالمات المسموح بها</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') Expat فقط @break
                    @case('lawyer_only') المحامي فقط @break
                    @default Expat والمحامي
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>الشرائح التسعيرية (رسم ثابت حسب عدد المشتركين)</h3>
        <table>
            <tr><th>المشتركون النشطون</th><th>الاشتراك الشهري</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'غير محدود' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, '.', ',') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. الحصص والحدود</h2>
<table>
    <tr><th>الحد الأقصى للمشتركين</th>
        <td>@if(($vars['max_subscribers'] ?? 0) > 0) {{ $vars['max_subscribers'] }} @else غير محدود @endif</td></tr>
    <tr><th>المدة الافتراضية للاشتراك</th>
        <td>@if(($vars['default_subscriber_duration_days'] ?? 0) > 0) {{ $vars['default_subscriber_duration_days'] }} يوماً @else دائم (حتى انتهاء العقد) @endif</td></tr>
    <tr><th>المدة القصوى للاشتراك</th>
        <td>@if(($vars['max_subscriber_duration_days'] ?? 0) > 0) {{ $vars['max_subscriber_duration_days'] }} يوماً @else دون حد @endif</td></tr>
</table>

<h2>6. الإحالة إلى الشروط العامة</h2>
<div class="important">
    تخضع طلبية الخدمة هذه <strong>للشروط العامة للبيع B2B SOS-Call</strong>
    و<strong>اتفاقية معالجة البيانات (DPA)</strong> المرفقتين بهذا العقد والموقعتين معه،
    وفق <strong>القانون الإستوني</strong>. في حالة التعارض، تسود الشروط الخاصة لطلبية الخدمة هذه
    على الشروط العامة، باستثناء الأحكام الإلزامية المتعلقة بحماية البيانات الشخصية
    (RGPD + IKS الإستوني)، التي تسود في كل الأحوال.
</div>

@if(!empty($customClauses))
    <h2>7. الشروط الخاصة</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'شرط خاص' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
