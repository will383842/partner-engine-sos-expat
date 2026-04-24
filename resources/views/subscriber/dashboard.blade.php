<!DOCTYPE html>
<html lang="{{ $subscriber->language ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace SOS-Call</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50">
    <nav class="bg-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="font-bold text-gray-900">🆘 SOS-Call · Mon espace</div>
            <form method="POST" action="/mon-acces/logout">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Se déconnecter</button>
            </form>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                Bonjour{{ $subscriber->first_name ? ' ' . $subscriber->first_name : '' }} 👋
            </h1>
            <p class="text-gray-600 mt-1">
                Vous êtes couvert par <strong>{{ $agreement?->partner_name ?? 'votre partenaire' }}</strong>
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Votre code SOS-Call</h2>
            @if ($subscriber->sos_call_code)
                <div class="flex items-center gap-3">
                    <div class="text-3xl font-mono font-bold text-gray-900 tracking-widest">
                        {{ $subscriber->sos_call_code }}
                    </div>
                    <button
                        onclick="navigator.clipboard.writeText('{{ $subscriber->sos_call_code }}'); this.textContent='✓ Copié'"
                        class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm font-semibold hover:bg-blue-200 transition">
                        Copier
                    </button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-gray-500">Statut</div>
                        @if ($subscriber->status === 'active')
                            <div class="text-green-700 font-semibold">🟢 Actif</div>
                        @elseif ($subscriber->status === 'suspended')
                            <div class="text-red-700 font-semibold">🔴 Suspendu</div>
                        @else
                            <div class="text-gray-700 font-semibold">⚪ {{ $subscriber->status }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-gray-500">Expiration</div>
                        <div class="text-gray-900 font-semibold">
                            {{ $subscriber->sos_call_expires_at ? $subscriber->sos_call_expires_at->format('d/m/Y') : 'Permanent' }}
                        </div>
                    </div>
                    @if ($maxCalls > 0)
                        <div class="col-span-2">
                            <div class="text-gray-500">Appels utilisés</div>
                            <div class="text-gray-900 font-semibold">{{ $totalCalls }} / {{ $maxCalls }}</div>
                        </div>
                    @endif
                </div>
            @else
                <div class="text-gray-500">Code non généré — contactez votre partenaire.</div>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-3">Services disponibles</h2>
            @php $allowed = $agreement?->call_types_allowed ?? 'both'; @endphp
            @if ($allowed === 'both' || $allowed === 'expat_only')
                <div class="bg-blue-50 rounded-lg p-4 mb-3">
                    <div class="font-semibold text-blue-900">👤 Expert Expat</div>
                    <div class="text-sm text-blue-700">Démarches administratives, visa, paperasse locale</div>
                </div>
            @endif
            @if ($allowed === 'both' || $allowed === 'lawyer_only')
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="font-semibold text-red-900">⚖️ Avocat Local</div>
                    <div class="text-sm text-red-700">Arrestation, accident, litige, urgence juridique</div>
                </div>
            @endif
            <a
                href="{{ $sosCallUrl }}"
                class="block mt-5 w-full text-center bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transition">
                🆘 Appeler maintenant
            </a>
        </div>

        @if ($recentCalls->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">5 derniers appels</h2>
                <ul class="divide-y divide-gray-200">
                    @foreach ($recentCalls as $call)
                        <li class="py-3 flex justify-between items-center text-sm">
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ $call->provider_type === 'lawyer' ? '⚖️ Avocat' : '👤 Expert' }}
                                </div>
                                <div class="text-gray-500">{{ $call->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                            <div class="text-gray-600">
                                @if ($call->duration_seconds)
                                    {{ round($call->duration_seconds / 60) }} min
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-xl p-6 text-sm text-gray-700">
            <h2 class="text-lg font-bold text-gray-900 mb-3">FAQ</h2>
            <details class="mb-3"><summary class="cursor-pointer font-medium">Comment utiliser le service ?</summary>
                <p class="mt-2 text-gray-600">Rendez-vous sur <a href="/sos-call" class="text-blue-600">sos-call.sos-expat.com</a>, entrez votre code et choisissez Expert ou Avocat.</p>
            </details>
            <details class="mb-3"><summary class="cursor-pointer font-medium">Que faire si j'ai perdu mon code ?</summary>
                <p class="mt-2 text-gray-600">Vous pouvez aussi vous identifier avec votre téléphone et email sur la page SOS-Call.</p>
            </details>
            <details><summary class="cursor-pointer font-medium">Combien d'appels puis-je faire ?</summary>
                <p class="mt-2 text-gray-600">@if($maxCalls > 0){{ $maxCalls }} appels maximum.@else Aucune limite dans le cadre de votre contrat.@endif</p>
            </details>
        </div>
    </main>
</body>
</html>
