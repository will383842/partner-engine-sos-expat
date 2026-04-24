<x-filament-panels::page>
    @if(!$agreement)
        <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-red-800">
            Aucun contrat n'est associé à votre compte. Contactez votre interlocuteur SOS-Expat.
        </div>
    @else
        <div class="grid gap-6 md:grid-cols-2">

            {{-- Carte principale contrat --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm md:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">Entreprise partenaire</div>
                        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $agreement->partner_name }}</div>
                        @if($agreement->billing_email)
                            <div class="text-sm text-slate-600 mt-1">{{ $agreement->billing_email }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        @php
                            $statusLabel = [
                                'active' => 'Contrat actif',
                                'paused' => 'Suspendu',
                                'expired' => 'Expiré',
                                'draft' => 'En préparation',
                            ][$agreement->status] ?? $agreement->status;
                            $statusColor = [
                                'active' => 'bg-green-100 text-green-800 border-green-300',
                                'paused' => 'bg-amber-100 text-amber-800 border-amber-300',
                                'expired' => 'bg-red-100 text-red-800 border-red-300',
                                'draft' => 'bg-slate-100 text-slate-700 border-slate-300',
                            ][$agreement->status] ?? 'bg-slate-100 text-slate-700 border-slate-300';
                        @endphp
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border {{ $statusColor }}">
                            {{ $statusLabel }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Modèle économique --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">Modèle économique</div>
                @php
                    $modelLabel = [
                        'commission' => 'Commission à l\'acte',
                        'sos_call' => 'SOS-Call — Forfait mensuel',
                        'hybrid' => 'Hybride',
                    ][$agreement->economic_model] ?? $agreement->economic_model;
                @endphp
                <div class="text-xl font-bold text-slate-900 mt-2">{{ $modelLabel }}</div>

                @if(in_array($agreement->economic_model, ['sos_call', 'hybrid'], true))
                    <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Tarif par client actif</span>
                            <span class="font-semibold text-slate-900">
                                {{ number_format($agreement->billing_rate, 2, ',', ' ') }}
                                {{ $agreement->billing_currency === 'USD' ? '$' : '€' }} / mois
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Types d'appels autorisés</span>
                            <span class="font-semibold text-slate-900">
                                {{ ['both' => 'Expert + Avocat', 'expat_only' => 'Expert seulement', 'lawyer_only' => 'Avocat seulement'][$agreement->call_types_allowed] ?? $agreement->call_types_allowed }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Délai de paiement</span>
                            <span class="font-semibold text-slate-900">{{ $agreement->payment_terms_days ?? 15 }} jours</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Quotas --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">Quotas & limites</div>
                <div class="mt-4 space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-slate-600">Clients actifs</span>
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
                        <span class="text-slate-600">Appels par client</span>
                        <span class="font-semibold text-slate-900">
                            {{ ($agreement->max_calls_per_subscriber && $agreement->max_calls_per_subscriber > 0) ? $agreement->max_calls_per_subscriber : 'Illimité' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">Durée d'accès client (par défaut)</span>
                        <span class="font-semibold text-slate-900">
                            {{ $agreement->default_subscriber_duration_days ? $agreement->default_subscriber_duration_days . ' jours' : 'Permanent' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Dates contrat --}}
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm md:col-span-2">
                <div class="text-sm font-medium text-slate-500 uppercase tracking-wide">Dates contractuelles</div>
                <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
                    <div>
                        <div class="text-slate-600">Début du contrat</div>
                        <div class="font-semibold text-slate-900 mt-1">
                            {{ $agreement->starts_at ? $agreement->starts_at->format('d/m/Y') : '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-600">Fin du contrat</div>
                        <div class="font-semibold text-slate-900 mt-1">
                            {{ $agreement->expires_at ? $agreement->expires_at->format('d/m/Y') : 'Sans date de fin' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Note --}}
            <div class="md:col-span-2 bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
                Pour modifier votre contrat, vos quotas ou votre interlocuteur de facturation, contactez votre chargé de compte SOS-Expat.
            </div>

        </div>
    @endif
</x-filament-panels::page>
