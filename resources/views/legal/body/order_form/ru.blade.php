<h2>1. Стороны</h2>
<table>
    <tr>
        <th style="width: 35%;">Поставщик (SOS-Expat)</th>
        <td>
            <strong>{{ $vars['provider_legal_name'] }}</strong><br>
            Эстонское общество с ограниченной ответственностью (Osaühing — OÜ)<br>
            @if($vars['provider_address'] ?? null) {{ $vars['provider_address'] }}<br>@endif
            @if($vars['provider_siret'] ?? null) Эстонский Коммерческий регистр (Äriregister): {{ $vars['provider_siret'] }}<br>@endif
            @if($vars['provider_vat'] ?? null) НДС ЕС: {{ $vars['provider_vat'] }}<br>@endif
            E-mail: {{ $vars['provider_email'] }}
        </td>
    </tr>
    <tr>
        <th>Партнёр</th>
        <td>
            <strong>{{ $vars['partner_name'] }}</strong><br>
            Внутренний идентификатор: {{ $vars['partner_firebase_id'] }}<br>
            E-mail для счетов: {{ $vars['billing_email'] }}<br>
            @if($vars['agreement_name'] ?? null) Ссылка на соглашение: {{ $vars['agreement_name'] }}<br>@endif
        </td>
    </tr>
</table>

<h2>2. Период действия</h2>
<table>
    <tr><th>Дата вступления в силу</th><td>{{ $vars['starts_at'] }}</td></tr>
    <tr><th>Дата истечения</th>
        <td>{{ $vars['expires_at'] ?: 'Бессрочный — расторгается с уведомлением за 30 дней' }}</td></tr>
</table>

<h2>3. Экономическая модель</h2>
<p>Настоящий заказ выбирает следующую экономическую модель:
@if($vars['economic_model'] === 'sos_call')
    <strong>SOS-Call (B2B-ежемесячная подписка)</strong> — Партнёр платит ежемесячную подписку,
    его Абоненты звонят бесплатно.
@elseif($vars['economic_model'] === 'commission')
    <strong>Комиссия за звонок</strong> — Партнёр получает комиссию за каждый звонок одного
    из своих привлечённых пользователей.
@elseif($vars['economic_model'] === 'hybrid')
    <strong>Гибридная</strong> — сочетание ежемесячной подписки и комиссии.
@else
    {{ $vars['economic_model'] }}
@endif
</p>

@if(in_array($vars['economic_model'], ['sos_call', 'hybrid'], true))
    <h2>4. Тарификация SOS-Call</h2>
    <table>
        <tr><th>Валюта счетов</th><td><strong>{{ $vars['billing_currency'] }}</strong></td></tr>
        @if(($vars['monthly_base_fee'] ?? 0) > 0)
            <tr><th>Фиксированная ежемесячная плата</th>
                <td><strong>{{ number_format((float)$vars['monthly_base_fee'], 2, ',', ' ') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        @if(($vars['billing_rate'] ?? 0) > 0)
            <tr><th>За активного абонента в месяц</th>
                <td><strong>{{ number_format((float)$vars['billing_rate'], 2, ',', ' ') }} {{ $vars['billing_currency'] }}</strong></td></tr>
        @endif
        <tr><th>Срок оплаты</th><td>{{ $vars['payment_terms_days'] }} дней с конца месяца</td></tr>
        <tr><th>Разрешённые типы звонков</th>
            <td>
                @switch($vars['call_types_allowed'])
                    @case('expat_only') Только Expat @break
                    @case('lawyer_only') Только адвокат @break
                    @default Expat и адвокат
                @endswitch
            </td>
        </tr>
    </table>

    @if(!empty($vars['pricing_tiers']))
        <h3>Тарифные уровни (фиксированная плата по числу абонентов)</h3>
        <table>
            <tr><th>Активные абоненты</th><th>Ежемесячная подписка</th></tr>
            @foreach($vars['pricing_tiers'] as $tier)
                <tr>
                    <td>{{ $tier['min'] ?? 0 }} – {{ $tier['max'] ?? 'без ограничений' }}</td>
                    <td>{{ number_format((float)($tier['amount'] ?? 0), 2, ',', ' ') }} {{ $vars['billing_currency'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<h2>5. Квоты и лимиты</h2>
<table>
    <tr><th>Максимальное число абонентов</th>
        <td>@if(($vars['max_subscribers'] ?? 0) > 0) {{ $vars['max_subscribers'] }} @else Без ограничений @endif</td></tr>
    <tr><th>Срок подписки по умолчанию</th>
        <td>@if(($vars['default_subscriber_duration_days'] ?? 0) > 0) {{ $vars['default_subscriber_duration_days'] }} дней @else Постоянно (до истечения договора) @endif</td></tr>
    <tr><th>Максимальный срок подписки</th>
        <td>@if(($vars['max_subscriber_duration_days'] ?? 0) > 0) {{ $vars['max_subscriber_duration_days'] }} дней @else Без ограничения @endif</td></tr>
</table>

<h2>6. Ссылка на общие условия</h2>
<div class="important">
    Настоящий заказ регулируется <strong>Общими условиями продажи B2B SOS-Call</strong> и
    <strong>Соглашением об обработке данных (DPA)</strong>, приложенными к настоящему договору
    и подписанными совместно, согласно <strong>эстонскому праву</strong>. В случае противоречия
    особые положения настоящего заказа имеют приоритет над общими Условиями, за исключением
    императивных положений о защите персональных данных (GDPR + эстонский IKS), которые в
    любом случае имеют приоритет.
</div>

@if(!empty($customClauses))
    <h2>7. Особые условия</h2>
    @foreach($customClauses as $i => $clause)
        <div class="clause">
            <h3>7.{{ $i + 1 }} {{ $clause['title'] ?? 'Особое условие' }}</h3>
            <p>{!! nl2br(e($clause['content'] ?? '')) !!}</p>
        </div>
    @endforeach
@endif
