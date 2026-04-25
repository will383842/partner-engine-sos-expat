<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Dimension switcher --}}
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-sm font-medium text-slate-700">{{ __('panel.hierarchy.group_by') }}</span>
                @foreach($dimensionLabels as $key => $label)
                    <button
                        wire:click="$set('dimension', '{{ $key }}'); $set('drillDown', null)"
                        class="px-4 py-2 text-sm rounded-lg border transition
                            {{ $dimension === $key
                                ? 'bg-red-600 text-white border-red-600'
                                : 'bg-white text-slate-700 border-slate-200 hover:border-slate-400' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if($drillDown)
            {{-- Drill-down view: subscribers of one cabinet --}}
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-200 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <button wire:click="exitDrill" class="text-sm text-red-700 hover:underline inline-flex items-center gap-1">
                            {{ __('panel.hierarchy.back_to_list') }}
                        </button>
                        <h2 class="text-lg font-bold text-slate-900 mt-1">
                            {{ $dimensionLabels[$dimension] }} : {{ $drillDown }}
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ __('panel.hierarchy.clients_count', ['count' => count($drillSubscribers)]) }}
                        </p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">{{ __('panel.hierarchy.col_name') }}</th>
                                <th class="text-left px-4 py-2 font-medium">{{ __('panel.hierarchy.col_email') }}</th>
                                <th class="text-left px-4 py-2 font-medium">{{ __('panel.hierarchy.col_phone') }}</th>
                                <th class="text-left px-4 py-2 font-medium">{{ __('panel.hierarchy.col_code') }}</th>
                                <th class="text-left px-4 py-2 font-medium">{{ __('panel.hierarchy.col_calls_month') }}</th>
                                <th class="text-left px-4 py-2 font-medium">{{ __('panel.hierarchy.col_status') }}</th>
                                <th class="text-right px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($drillSubscribers as $sub)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $sub['name'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $sub['email'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $sub['phone'] ?: __('panel.common.dash') }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-700">{{ $sub['code'] ?: __('panel.common.dash') }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-800">
                                            {{ $sub['calls_month'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @php
                                            $statusColor = [
                                                'active' => 'bg-green-100 text-green-800',
                                                'invited' => 'bg-amber-100 text-amber-800',
                                                'suspended' => 'bg-red-100 text-red-800',
                                            ][$sub['status']] ?? 'bg-slate-100 text-slate-700';
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                            {{ __('panel.common.' . $sub['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="{{ url('/subscribers/' . $sub['id'] . '/edit') }}"
                                           class="text-red-700 hover:underline text-xs">{{ __('panel.hierarchy.edit') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">{{ __('panel.hierarchy.empty_drill') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- Aggregated view --}}
            @if(($monthlyBaseFee ?? 0) > 0)
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-900">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong>{{ __('panel.hierarchy.flat_fee_notice', ['amount' => $currencySymbol . ' ' . number_format($monthlyBaseFee, 2, ',', ' ')]) }}</strong>
                            <p class="text-xs mt-0.5 text-blue-800">{{ __('panel.hierarchy.flat_fee_explanation') }}</p>
                            <p class="text-xs mt-1 font-semibold">{{ __('panel.hierarchy.total_estimated', ['amount' => $currencySymbol . ' ' . number_format($totalEstimatedInvoice, 2, ',', ' ')]) }}</p>
                        </div>
                    </div>
                </div>
            @endif
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium">{{ $dimensionLabels[$dimension] }}</th>
                                <th class="text-right px-4 py-3 font-medium">{{ __('panel.hierarchy.col_active_clients') }}</th>
                                <th class="text-right px-4 py-3 font-medium">{{ __('panel.hierarchy.col_total_clients') }}</th>
                                <th class="text-right px-4 py-3 font-medium">{{ __('panel.hierarchy.col_calls_month') }}</th>
                                <th class="text-right px-4 py-3 font-medium">{{ __('panel.hierarchy.col_usage_pct') }}</th>
                                <th class="text-right px-4 py-3 font-medium">{{ __('panel.hierarchy.col_est_invoice') }}</th>
                                <th class="text-right px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($rows as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ $row['label'] }}
                                        @if($row['is_unassigned'] ?? false)
                                            <span class="inline-flex items-center px-2 py-0.5 ml-2 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ __('panel.hierarchy.unassigned_badge') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $row['subs_active'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600">{{ $row['subs_total'] }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-800">
                                            {{ $row['calls_month'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ $row['usage_pct'] }}%</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                        {{ $currencySymbol }} {{ number_format($row['estimated_invoice'], 2, ',', ' ') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if(!($row['is_unassigned'] ?? false))
                                            <button wire:click="drillInto(@js($row['label']))"
                                                    class="text-red-700 hover:underline text-xs font-medium">
                                                {{ __('panel.hierarchy.see_clients') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">
                                    {{ __('panel.hierarchy.empty_no_hierarchy', ['dimension' => strtolower($dimensionLabels[$dimension])]) }}
                                    <br><span class="text-xs">{{ __('panel.hierarchy.empty_hint') }}</span>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
                {!! nl2br(e(__('panel.hierarchy.footer_hint'))) !!}
            </div>
        @endif

    </div>
</x-filament-panels::page>
