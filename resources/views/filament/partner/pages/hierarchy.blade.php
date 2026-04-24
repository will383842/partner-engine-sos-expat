<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Dimension switcher --}}
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-sm font-medium text-slate-700">Grouper par :</span>
                @foreach(['group_label' => 'Cabinet / Unité', 'region' => 'Région', 'department' => 'Département'] as $key => $label)
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
                            ← Retour à la liste
                        </button>
                        <h2 class="text-lg font-bold text-slate-900 mt-1">
                            {{ $dimensionLabels[$dimension] }} : {{ $drillDown }}
                        </h2>
                        <p class="text-sm text-slate-500">{{ count($drillSubscribers) }} clients</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Nom</th>
                                <th class="text-left px-4 py-2 font-medium">Email</th>
                                <th class="text-left px-4 py-2 font-medium">Téléphone</th>
                                <th class="text-left px-4 py-2 font-medium">Code</th>
                                <th class="text-left px-4 py-2 font-medium">Appels (mois)</th>
                                <th class="text-left px-4 py-2 font-medium">Statut</th>
                                <th class="text-right px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($drillSubscribers as $sub)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $sub['name'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $sub['email'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $sub['phone'] ?: '—' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-700">{{ $sub['code'] ?: '—' }}</td>
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
                                            $statusLabel = [
                                                'active' => 'Actif',
                                                'invited' => 'Invité',
                                                'suspended' => 'Suspendu',
                                            ][$sub['status']] ?? $sub['status'];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="{{ url('/subscribers/' . $sub['id'] . '/edit') }}"
                                           class="text-red-700 hover:underline text-xs">Éditer</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Aucun client dans ce groupe.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- Aggregated view --}}
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium">{{ $dimensionLabels[$dimension] }}</th>
                                <th class="text-right px-4 py-3 font-medium">Clients actifs</th>
                                <th class="text-right px-4 py-3 font-medium">Total clients</th>
                                <th class="text-right px-4 py-3 font-medium">Appels ce mois</th>
                                <th class="text-right px-4 py-3 font-medium">Taux d'usage</th>
                                <th class="text-right px-4 py-3 font-medium">Facture estimée</th>
                                <th class="text-right px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($rows as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ $row['label'] }}
                                        @if($row['is_unassigned'] ?? false)
                                            <span class="inline-flex items-center px-2 py-0.5 ml-2 rounded-full text-xs font-medium bg-amber-100 text-amber-800">À renseigner</span>
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
                                                Voir les clients →
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">
                                    Aucun client n'est assigné à un {{ strtolower($dimensionLabels[$dimension]) }} pour l'instant.
                                    <br><span class="text-xs">Pour y remédier, édite tes clients depuis "Mes clients" et renseigne les champs hiérarchie.</span>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
                💡 Pour créer une hiérarchie, ouvre la fiche d'un client dans <strong>Mes clients</strong> et renseigne
                les champs <em>Cabinet</em>, <em>Région</em> et <em>Département</em>. Les agrégations se mettent à jour ici en temps réel.
            </div>
        @endif

    </div>
</x-filament-panels::page>
