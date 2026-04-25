<x-filament-panels::page>
    @if(!$agreement)
        <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-red-800">
            {{ __('panel.my_account.no_agreement') }}
        </div>
    @else
        <div class="grid gap-6 md:grid-cols-2">

            {{-- Carte principale contrat --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm md:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('panel.my_account.partner_company') }}</div>
                        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $agreement->partner_name }}</div>
                        @if($agreement->billing_email)
                            <div class="text-sm text-slate-600 mt-1">{{ $agreement->billing_email }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        @php
                            $statusKey = [
                                'active' => 'status_active',
                                'paused' => 'status_paused',
                                'expired' => 'status_expired',
                                'draft' => 'status_draft',
                            ][$agreement->status] ?? 'status_active';
                            $statusColor = [
                                'active' => 'bg-green-100 text-green-800 border-green-300',
                                'paused' => 'bg-amber-100 text-amber-800 border-amber-300',
                                'expired' => 'bg-red-100 text-red-800 border-red-300',
                                'draft' => 'bg-slate-100 text-slate-700 border-slate-300',
                            ][$agreement->status] ?? 'bg-slate-100 text-slate-700 border-slate-300';
                        @endphp
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border {{ $statusColor }}">
                            {{ __('panel.my_account.' . $statusKey) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Modèle économique --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('panel.my_account.economic_model') }}</div>
                @php
                    $modelKey = [
                        'commission' => 'model_commission',
                        'sos_call' => 'model_sos_call',
                        'hybrid' => 'model_hybrid',
                    ][$agreement->economic_model] ?? 'model_commission';
                @endphp
                <div class="text-xl font-bold text-slate-900 mt-2">{{ __('panel.my_account.' . $modelKey) }}</div>

                @if(in_array($agreement->economic_model, ['sos_call', 'hybrid'], true))
                    <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-600">{{ __('panel.my_account.rate_per_client') }}</span>
                            <span class="font-semibold text-slate-900">
                                {{ number_format($agreement->billing_rate, 2, ',', ' ') }}
                                {{ $agreement->billing_currency === 'USD' ? '$' : '€' }} {{ __('panel.my_account.rate_suffix') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">{{ __('panel.my_account.call_types') }}</span>
                            @php
                                $typesKey = [
                                    'both' => 'call_types_both',
                                    'expat_only' => 'call_types_expat',
                                    'lawyer_only' => 'call_types_lawyer',
                                ][$agreement->call_types_allowed] ?? 'call_types_both';
                            @endphp
                            <span class="font-semibold text-slate-900">{{ __('panel.my_account.' . $typesKey) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">{{ __('panel.my_account.payment_terms') }}</span>
                            <span class="font-semibold text-slate-900">
                                {{ __('panel.my_account.payment_terms_days', ['days' => $agreement->payment_terms_days ?? 15]) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Quotas --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('panel.my_account.quotas_section') }}</div>
                <div class="mt-4 space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-slate-600">{{ __('panel.my_account.active_clients_label') }}</span>
                            <span class="font-semibold text-slate-900">
                                {{ $activeSubscribersCount }}
                                @if($agreement->max_subscribers && $agreement->max_subscribers > 0)
                                    / {{ $agreement->max_subscribers }}
                                @else
                                    / ∞
                                @endif
                            </span>
                        </div>
                        @if($agreement->max_subscribers && $agreement->max_subscribers > 0)
                            <div class="w-full bg-slate-100 rounded-full h-2">
                                @php $pct = min(100, ($activeSubscribersCount / $agreement->max_subscribers) * 100); @endphp
                                <div class="bg-red-600 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                            </div>
                        @endif
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('panel.my_account.calls_per_client') }}</span>
                        <span class="font-semibold text-slate-900">
                            {{ ($agreement->max_calls_per_subscriber && $agreement->max_calls_per_subscriber > 0) ? $agreement->max_calls_per_subscriber : __('panel.common.unlimited') }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('panel.my_account.default_access_duration') }}</span>
                        <span class="font-semibold text-slate-900">
                            {{ $agreement->default_subscriber_duration_days
                                ? __('panel.my_account.duration_days', ['days' => $agreement->default_subscriber_duration_days])
                                : __('panel.my_account.duration_permanent') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Dates contrat --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm md:col-span-2">
                <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('panel.my_account.contract_dates') }}</div>
                <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
                    <div>
                        <div class="text-slate-600">{{ __('panel.my_account.contract_start') }}</div>
                        <div class="font-semibold text-slate-900 mt-1">
                            {{ $agreement->starts_at ? $agreement->starts_at->locale(app()->getLocale())->isoFormat('LL') : __('panel.common.dash') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-600">{{ __('panel.my_account.contract_end') }}</div>
                        <div class="font-semibold text-slate-900 mt-1">
                            {{ $agreement->expires_at ? $agreement->expires_at->locale(app()->getLocale())->isoFormat('LL') : __('panel.my_account.contract_end_none') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Note --}}
            <div class="md:col-span-2 bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
                {{ __('panel.my_account.contact_note') }}
            </div>

        </div>
    @endif
</x-filament-panels::page>
