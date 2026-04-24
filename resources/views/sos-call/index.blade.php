<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['ar']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>SOS-Expat · Mise en relation en moins de 5 minutes</title>
    <meta name="description" content="Accédez à votre service d'assistance SOS-Expat — juridique ou pratique — mise en relation en moins de 5 minutes.">

    {{-- Security headers --}}
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">

    <link rel="icon" type="image/webp" href="https://sos-expat.com/sos-logo.webp">

    {{-- Tailwind via CDN (Sprint 7 will migrate to Vite build) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sos: {
                            red: '#dc2626',
                            dark: '#0f172a',
                            light: '#fef2f2',
                        }
                    }
                }
            }
        }
    </script>

    {{-- Alpine.js via CDN --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    {{-- libphonenumber-js for E.164 normalization --}}
    <script src="https://cdn.jsdelivr.net/npm/libphonenumber-js@1.11.20/bundle/libphonenumber-max.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        .sos-gradient { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); }
        .sos-gradient-text {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>

    <script>
        window.SOS_CALL_CONFIG = {!! $clientConfigJson !!};
    </script>
</head>
<body class="min-h-screen bg-white text-slate-900" x-data="sosCallApp()" x-cloak>

    {{-- Header --}}
    <header class="sos-gradient text-white py-4 px-6 shadow-md">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-white rounded-full p-1 shadow-sm">
                    <img src="https://sos-expat.com/sos-logo.webp" alt="SOS-Expat" class="h-9 w-9 object-contain">
                </div>
                <div>
                    <div class="font-bold text-lg tracking-tight text-white">SOS-Expat</div>
                    <div class="text-xs text-white/90">Aide juridique ou pratique · Moins de 5 minutes</div>
                </div>
            </div>
            <div class="text-xs text-white/80 hidden sm:block">
                24h/24 · 197 pays · 9 langues
            </div>
        </div>
    </header>

    {{-- Main container --}}
    <main class="max-w-xl mx-auto px-4 py-8 sm:py-12">

        {{-- ==========================
             STATE 1: INITIAL (choice between code / phone+email)
             ========================== --}}
        <section x-show="state === 'initial'" class="space-y-6">
            <div class="text-center mb-6">
                <h1 class="text-3xl sm:text-4xl font-bold text-slate-900">
                    Accédez à votre service
                </h1>
                <p class="mt-3 text-slate-600">
                    Votre partenaire a activé votre accès SOS-Expat gratuit.
                </p>
            </div>

            {{-- Toggle between code / phone+email --}}
            <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 border border-slate-200">
                <div class="flex gap-2 mb-6 p-1 bg-slate-100 rounded-xl">
                    <button @click="mode = 'code'" :class="mode === 'code' ? 'bg-white shadow font-semibold text-sos-red' : 'text-slate-600'" class="flex-1 py-2.5 px-4 rounded-lg transition text-sm">
                        Mon code
                    </button>
                    <button @click="mode = 'phone_email'" :class="mode === 'phone_email' ? 'bg-white shadow font-semibold text-sos-red' : 'text-slate-600'" class="flex-1 py-2.5 px-4 rounded-lg transition text-sm">
                        Téléphone + Email
                    </button>
                </div>

                {{-- Code form --}}
                <form @submit.prevent="checkEligibility()" x-show="mode === 'code'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Votre code SOS-Call
                        </label>
                        <input
                            type="text"
                            x-model="code"
                            @input="code = $event.target.value.toUpperCase()"
                            placeholder="XXX-2026-XXXXX"
                            maxlength="20"
                            autocomplete="off"
                            autocapitalize="characters"
                            class="w-full px-4 py-3 text-lg font-mono tracking-wider rounded-xl border-2 border-slate-200 focus:border-sos-red focus:ring-2 focus:ring-red-200 focus:outline-none transition uppercase"
                            :disabled="loading"
                        >
                        <p class="mt-2 text-xs text-slate-500">
                            Format : 3 lettres - année - 5 caractères (reçu par email lors de votre inscription)
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="!code || loading"
                        class="w-full py-3.5 px-6 sos-gradient text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!loading">Vérifier ma couverture →</span>
                        <span x-show="loading">Vérification en cours...</span>
                    </button>
                </form>

                {{-- Phone + email form --}}
                <form @submit.prevent="checkEligibility()" x-show="mode === 'phone_email'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Téléphone <span class="text-slate-400">(format international)</span>
                        </label>
                        <input
                            type="tel"
                            x-model="phone"
                            placeholder="+33 6 12 34 56 78"
                            autocomplete="tel"
                            inputmode="tel"
                            class="w-full px-4 py-3 text-lg rounded-xl border-2 border-slate-200 focus:border-sos-red focus:ring-2 focus:ring-red-200 focus:outline-none transition"
                            :disabled="loading"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Email
                        </label>
                        <input
                            type="email"
                            x-model="email"
                            placeholder="votre@email.com"
                            autocomplete="email"
                            inputmode="email"
                            class="w-full px-4 py-3 text-lg rounded-xl border-2 border-slate-200 focus:border-sos-red focus:ring-2 focus:ring-red-200 focus:outline-none transition"
                            :disabled="loading"
                        >
                    </div>

                    <button
                        type="submit"
                        :disabled="!phone || !email || loading"
                        class="w-full py-3.5 px-6 sos-gradient text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!loading">Vérifier ma couverture →</span>
                        <span x-show="loading">Vérification en cours...</span>
                    </button>
                </form>
            </div>

            {{-- Fallback to standard paid access --}}
            <div class="text-center bg-slate-50 rounded-xl p-4 border border-slate-200">
                <p class="text-sm text-slate-600 mb-2">Vous n'avez pas de code partenaire ?</p>
                <a :href="(window.SOS_CALL_CONFIG.frontendUrl || 'https://sos-expat.com') + '/sos-appel'" class="inline-block text-slate-900 hover:text-sos-red text-sm font-medium">
                    Accès standard payant :
                    <span x-text="'Expert ' + pricing.expat.eur + '€ / $' + pricing.expat.usd"></span>
                    ·
                    <span x-text="'Avocat ' + pricing.lawyer.eur + '€ / $' + pricing.lawyer.usd"></span>
                    →
                </a>
                <p class="mt-2 text-xs text-slate-400">
                    <span x-text="'Expert : ' + pricing.expat.duration + ' min · Avocat : ' + pricing.lawyer.duration + ' min'"></span>
                </p>
            </div>
        </section>

        {{-- ==========================
             STATE 2: VERIFYING
             ========================== --}}
        <section x-show="state === 'verifying'" class="text-center py-16">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-sos-red mb-6"></div>
            <h2 class="text-xl font-semibold text-slate-700">Vérification de votre couverture...</h2>
            <p class="mt-2 text-slate-500">Cela prend moins de 3 secondes</p>
        </section>

        {{-- ==========================
             STATE 2b: REDIRECTING to SPA wizard after access_granted
             ========================== --}}
        <section x-show="state === 'redirecting'" class="text-center py-16">
            <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-6 mb-6">
                <div class="text-5xl mb-3">✓</div>
                <h2 class="text-2xl font-bold text-green-900">Accès confirmé</h2>
                <template x-if="session.partner_name">
                    <p class="mt-2 text-green-800">Couvert par <strong x-text="session.partner_name"></strong></p>
                </template>
            </div>
            <div class="inline-block animate-spin rounded-full h-10 w-10 border-t-4 border-b-4 border-sos-red mb-4"></div>
            <p class="text-slate-600">Redirection vers le choix de votre prestataire...</p>
        </section>

        {{-- ==========================
             STATE 3: ACCESS GRANTED
             ========================== --}}
        <section x-show="state === 'access_granted'" class="space-y-6">
            <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-6 text-center">
                <div class="text-5xl mb-3">✓</div>
                <h2 class="text-2xl font-bold text-green-900">Accès confirmé</h2>
                <p class="mt-2 text-green-800">
                    Couvert par <strong x-text="session.partner_name"></strong>
                </p>
                <template x-if="session.first_name">
                    <p class="mt-1 text-sm text-green-700">Bonjour <span x-text="session.first_name"></span></p>
                </template>
                <template x-if="session.calls_remaining !== null && session.calls_remaining !== undefined">
                    <p class="mt-2 text-sm text-green-700">
                        <span x-text="session.calls_remaining"></span> appel(s) restant(s) ce mois
                    </p>
                </template>
                <template x-if="session.expires_at">
                    <p class="mt-1 text-xs text-green-600">
                        Valable jusqu'au <span x-text="formatDate(session.expires_at)"></span>
                    </p>
                </template>
            </div>

            <div class="text-center">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">De quoi avez-vous besoin ?</h3>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <button
                    x-show="session.call_types_allowed === 'both' || session.call_types_allowed === 'expat_only'"
                    @click="selectCallType('expat')"
                    :disabled="callLoading"
                    class="p-6 bg-white rounded-2xl shadow-md hover:shadow-lg transition border-2 border-slate-200 hover:border-sos-red text-left disabled:opacity-50"
                >
                    <div class="font-bold text-lg text-slate-900">Expert Expat</div>
                    <p class="text-sm text-slate-600 mt-2">
                        Démarches, visa, administration, aide pratique
                    </p>
                    <p class="text-xs text-slate-400 mt-2" x-text="pricing.expat.duration + ' min d\'appel'"></p>
                </button>

                <button
                    x-show="session.call_types_allowed === 'both' || session.call_types_allowed === 'lawyer_only'"
                    @click="selectCallType('lawyer')"
                    :disabled="callLoading"
                    class="p-6 bg-white rounded-2xl shadow-md hover:shadow-lg transition border-2 border-slate-200 hover:border-sos-red text-left disabled:opacity-50"
                >
                    <div class="font-bold text-lg text-slate-900">Avocat Local</div>
                    <p class="text-sm text-slate-600 mt-2">
                        Arrestation, accident, litige, conseil juridique
                    </p>
                    <p class="text-xs text-slate-400 mt-2" x-text="pricing.lawyer.duration + ' min d\'appel'"></p>
                </button>
            </div>

            <div x-show="callLoading" class="text-center text-slate-600">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-sos-red mr-2 align-middle"></div>
                Préparation de votre appel...
            </div>

            <div x-show="callError" class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-800 text-sm" x-text="callError"></div>
        </section>

        {{-- ==========================
             STATE 3b: PICK PHONE (confirm number before call)
             ========================== --}}
        <section x-show="state === 'pick_phone'" class="space-y-6">
            <div class="bg-slate-50 border-2 border-slate-200 rounded-2xl p-6">
                <h2 class="text-xl font-bold text-slate-900 text-center">Sur quel numéro vous appeler ?</h2>
                <p class="mt-2 text-sm text-slate-700 text-center">
                    Vous allez recevoir un appel d'un
                    <span x-text="callType === 'lawyer' ? 'avocat' : 'expert expat'"></span>
                    dans moins de 5 minutes.
                </p>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Votre téléphone (format international)</label>
                    <input
                        type="tel"
                        x-model="callPhoneInput"
                        @keydown.enter="confirmPhoneAndTriggerCall()"
                        :disabled="callLoading"
                        placeholder="+33 6 12 34 56 78"
                        class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sos-red focus:border-sos-red">
                    <p x-show="callPhoneError" x-text="callPhoneError" class="mt-2 text-sm text-red-700"></p>
                </div>

                <div class="mt-5 flex gap-3">
                    <button
                        @click="state = 'access_granted'; callError = null"
                        :disabled="callLoading"
                        class="px-4 py-3 bg-white border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition disabled:opacity-50">
                        ← Retour
                    </button>
                    <button
                        @click="confirmPhoneAndTriggerCall()"
                        :disabled="callLoading"
                        class="flex-1 sos-gradient text-white py-3 rounded-lg font-semibold hover:opacity-95 transition disabled:opacity-50">
                        <span x-show="!callLoading">Me faire appeler maintenant</span>
                        <span x-show="callLoading">Déclenchement…</span>
                    </button>
                </div>

                <div x-show="callError" class="mt-4 bg-red-50 border border-red-200 rounded-xl p-3 text-red-800 text-sm" x-text="callError"></div>
            </div>
        </section>

        {{-- ==========================
             STATE 4: CODE INVALID (or email mismatch)
             ========================== --}}
        <section x-show="state === 'phone_match_email_mismatch'" class="space-y-6">
            <div class="bg-amber-50 border-2 border-amber-200 rounded-2xl p-6">
                <h2 class="text-xl font-bold text-amber-900 text-center">Email incorrect</h2>
                <p class="mt-3 text-amber-800 text-center">
                    Votre numéro de téléphone est reconnu, mais l'email saisi ne correspond pas.
                </p>
                <p class="mt-2 text-sm text-amber-700 text-center">
                    Assurez-vous d'utiliser l'email fourni à
                    <strong x-text="result.partner_name || 'votre partenaire'"></strong>
                    lors de votre inscription.
                </p>
                <template x-if="result.attempts_remaining !== undefined">
                    <p class="mt-3 text-xs text-amber-600 text-center">
                        Tentatives restantes : <strong x-text="result.attempts_remaining"></strong>
                    </p>
                </template>
            </div>

            <button @click="resetToInitial()" class="w-full py-3 px-6 bg-white border-2 border-slate-300 text-slate-700 font-medium rounded-xl hover:bg-slate-50 transition">
                Réessayer avec d'autres informations
            </button>
        </section>

        {{-- ==========================
             STATE 5: NOT FOUND
             ========================== --}}
        <section x-show="state === 'not_found'" class="space-y-6">
            <div class="bg-slate-100 border border-slate-200 rounded-2xl p-6 text-center">
                <h2 class="text-xl font-bold text-slate-900">Accès non trouvé</h2>
                <p class="mt-3 text-slate-700">
                    Ces informations ne correspondent à aucun accès SOS-Call actif.
                </p>
                <p class="mt-2 text-sm text-slate-600">
                    Vérifiez que vous utilisez bien les coordonnées communiquées à votre partenaire.
                </p>
            </div>

            <div class="space-y-3">
                <button @click="resetToInitial()" class="w-full py-3 px-6 bg-white border-2 border-slate-300 text-slate-700 font-medium rounded-xl hover:bg-slate-50 transition">
                    Réessayer
                </button>

                <a :href="(window.SOS_CALL_CONFIG.frontendUrl || 'https://sos-expat.com') + '/sos-appel'" class="block text-center py-3 px-6 sos-gradient text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition">
                    Accès standard payant
                </a>
            </div>
        </section>

        {{-- ==========================
             STATE 6: RATE LIMITED
             ========================== --}}
        <section x-show="state === 'rate_limited'" class="space-y-6">
            <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-6 text-center">
                <h2 class="text-xl font-bold text-sos-red">Trop de tentatives</h2>
                <p class="mt-3 text-red-800">
                    Pour des raisons de sécurité, l'accès est temporairement bloqué.
                </p>
                <p class="mt-2 text-sm text-red-700">
                    Veuillez réessayer dans 10 minutes.
                </p>
            </div>
        </section>

        {{-- ==========================
             STATE 7: OTHER ERRORS (agreement_inactive, expired, quota_reached, etc)
             ========================== --}}
        <section x-show="['agreement_inactive', 'expired', 'quota_reached'].includes(state)" class="space-y-6">
            <div class="bg-orange-50 border-2 border-orange-200 rounded-2xl p-6 text-center">
                <h2 class="text-xl font-bold text-orange-900" x-text="stateLabel(state)"></h2>
                <p class="mt-3 text-orange-800" x-text="stateDescription(state)"></p>
            </div>

            <button @click="resetToInitial()" class="w-full py-3 px-6 bg-white border-2 border-slate-300 text-slate-700 font-medium rounded-xl hover:bg-slate-50 transition">
                Retour
            </button>
        </section>

        {{-- ==========================
             STATE 8: CALL IN PROGRESS (countdown)
             ========================== --}}
        <section x-show="state === 'call_in_progress'" class="space-y-6">
            <div class="sos-gradient rounded-2xl p-8 text-white text-center shadow-xl">
                <h2 class="text-2xl font-bold">Votre appel arrive</h2>
                <p class="mt-2 text-white/90">
                    <span x-text="callType === 'lawyer' ? 'Avocat Local' : 'Expert Expat'"></span>
                </p>
                <template x-if="providerDisplayName">
                    <p class="mt-1 text-sm text-white/80">avec <strong x-text="providerDisplayName"></strong></p>
                </template>

                <div class="mt-8 text-6xl font-bold tabular-nums">
                    <span x-text="String(Math.floor(countdown / 60)).padStart(2, '0')"></span>:<span x-text="String(countdown % 60).padStart(2, '0')"></span>
                </div>

                <p class="mt-6 text-sm text-white/90">
                    Gardez votre téléphone à portée.<br>
                    Vous allez recevoir un appel de SOS-Expat.
                </p>
            </div>

            <div class="bg-white rounded-xl p-4 border border-slate-200 text-sm text-slate-600 text-center">
                Appel prévu au <strong x-text="maskedPhone"></strong>
            </div>
        </section>
    </main>

    {{-- Footer --}}
    <footer class="max-w-4xl mx-auto px-4 py-8 text-center text-xs text-slate-500 border-t border-slate-100 mt-12">
        <p>SOS-Expat · Aide juridique ou pratique · Mise en relation en moins de 5 minutes · 24h/24 · 197 pays</p>
        <p class="mt-2">
            <a :href="(window.SOS_CALL_CONFIG.frontendUrl || 'https://sos-expat.com') + '/privacy'" class="hover:text-slate-900">Confidentialité</a>
            ·
            <a :href="(window.SOS_CALL_CONFIG.frontendUrl || 'https://sos-expat.com') + '/terms'" class="hover:text-slate-900">Conditions</a>
        </p>
    </footer>

    {{-- Alpine.js component --}}
    <script>
        function sosCallApp() {
            return {
                // State machine
                state: 'initial',
                mode: 'code',

                // Form inputs
                code: '',
                phone: '',
                email: '',

                // Results
                loading: false,
                result: {},
                session: {},

                // Pricing (hydrated from Firestore admin_config/pricing)
                pricing: (window.SOS_CALL_CONFIG && window.SOS_CALL_CONFIG.pricing) || {
                    expat: { eur: 19, usd: 25, duration: 30 },
                    lawyer: { eur: 49, usd: 55, duration: 20 },
                },

                // Call state
                callLoading: false,
                callError: null,
                callType: null,
                countdown: 240,
                countdownInterval: null,
                maskedPhone: '',
                providerDisplayName: '',
                callPhoneInput: '',
                callPhoneError: null,

                async init() {
                    // Lazy-fetch dynamic pricing from Firestore admin_config/pricing
                    try {
                        const cfg = window.SOS_CALL_CONFIG || {};
                        if (!cfg.firebase || !cfg.firebase.projectId) return;
                        const { initializeApp, getApps, getApp } = await import('https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js');
                        const { getFirestore, doc, getDoc } = await import('https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js');
                        const app = getApps().length ? getApp() : initializeApp({
                            apiKey: cfg.firebase.apiKey,
                            authDomain: cfg.firebase.authDomain,
                            projectId: cfg.firebase.projectId,
                        });
                        const db = getFirestore(app);
                        const snap = await getDoc(doc(db, 'admin_config', 'pricing'));
                        if (!snap.exists()) return;
                        const data = snap.data();
                        const newPricing = { expat: { ...this.pricing.expat }, lawyer: { ...this.pricing.lawyer } };
                        if (data.expat?.eur?.totalAmount) newPricing.expat.eur = data.expat.eur.totalAmount;
                        if (data.expat?.usd?.totalAmount) newPricing.expat.usd = data.expat.usd.totalAmount;
                        if (data.expat?.eur?.duration) newPricing.expat.duration = data.expat.eur.duration;
                        if (data.lawyer?.eur?.totalAmount) newPricing.lawyer.eur = data.lawyer.eur.totalAmount;
                        if (data.lawyer?.usd?.totalAmount) newPricing.lawyer.usd = data.lawyer.usd.totalAmount;
                        if (data.lawyer?.eur?.duration) newPricing.lawyer.duration = data.lawyer.eur.duration;
                        this.pricing = newPricing;
                    } catch (err) {
                        console.warn('[SOS-Call] dynamic pricing fetch failed, using defaults', err);
                    }
                },

                resetToInitial() {
                    this.state = 'initial';
                    this.loading = false;
                    this.callLoading = false;
                    this.callError = null;
                    this.result = {};
                    this.session = {};
                    if (this.countdownInterval) {
                        clearInterval(this.countdownInterval);
                        this.countdownInterval = null;
                    }
                },

                async checkEligibility() {
                    this.loading = true;
                    this.state = 'verifying';

                    const payload = this.mode === 'code'
                        ? { code: this.code.trim() }
                        : { phone: this.normalizePhone(this.phone), email: this.email.trim().toLowerCase() };

                    if (this.mode === 'phone_email' && !payload.phone) {
                        this.loading = false;
                        this.state = 'initial';
                        alert('Format de téléphone invalide. Exemple: +33612345678');
                        return;
                    }

                    try {
                        const firebase = await this.getFirebase();
                        const checkSosCallCode = firebase.httpsCallable('checkSosCallCode');
                        const response = await checkSosCallCode(payload);
                        const data = response.data || {};

                        this.result = data;
                        this.state = data.status || 'not_found';

                        if (this.state === 'access_granted') {
                            this.session = data;
                            // Redirect to the main SPA wizard on sos-expat.com with the session token.
                            // The user will pick a specific provider there (list, filters, ratings)
                            // and BookingRequest will bypass Stripe thanks to the token.
                            const cfg = window.SOS_CALL_CONFIG || {};
                            const base = (cfg.frontendUrl || 'https://sos-expat.com').replace(/\/$/, '');
                            const params = new URLSearchParams({
                                sosCallToken: data.session_token || '',
                            });
                            if (data.partner_name) params.set('partnerName', data.partner_name);
                            if (data.call_types_allowed) params.set('callTypesAllowed', data.call_types_allowed);
                            if (data.expires_at) params.set('sosCallExpiresAt', data.expires_at);
                            // Short delay so the user sees the "access confirmed" state briefly before redirect
                            this.state = 'redirecting';
                            setTimeout(() => {
                                const loc = '{{ $locale }}';
                                window.location.href = `${base}/${loc}-${loc}/sos-appel?${params.toString()}`;
                            }, 600);
                        }
                    } catch (err) {
                        console.error('[SOS-Call] checkEligibility error:', err);
                        this.state = 'not_found';
                    } finally {
                        this.loading = false;
                    }
                },

                selectCallType(callType) {
                    this.callType = callType;
                    this.callPhoneError = null;
                    if (this.phone && !this.callPhoneInput) {
                        this.callPhoneInput = this.phone;
                    }
                    this.state = 'pick_phone';
                },

                async confirmPhoneAndTriggerCall() {
                    this.callPhoneError = null;
                    const normalized = this.normalizePhone(this.callPhoneInput);
                    if (!normalized) {
                        this.callPhoneError = 'Numéro invalide. Format attendu: +33612345678 (E.164 international).';
                        return;
                    }
                    this.callPhoneInput = normalized;
                    await this.triggerCall(this.callType, normalized);
                },

                async triggerCall(callType, clientPhone) {
                    this.callLoading = true;
                    this.callError = null;
                    this.callType = callType;

                    try {
                        const cfg = window.SOS_CALL_CONFIG || {};
                        if (!cfg.firebase || !cfg.firebase.projectId) {
                            throw new Error('Firebase config missing on server');
                        }

                        const [{ initializeApp, getApp, getApps }, { getFunctions, httpsCallable }] = await Promise.all([
                            import('https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js'),
                            import('https://www.gstatic.com/firebasejs/10.12.2/firebase-functions.js'),
                        ]);

                        const app = getApps().length ? getApp() : initializeApp({
                            apiKey: cfg.firebase.apiKey,
                            authDomain: cfg.firebase.authDomain,
                            projectId: cfg.firebase.projectId,
                        });

                        const functions = getFunctions(app, cfg.firebase.region || 'us-central1');
                        const trigger = httpsCallable(functions, 'triggerSosCallFromWeb');

                        // Try to guess the client country from the phone number (E.164 starts with country code)
                        let clientCountry = '';
                        try {
                            const parsed = window.libphonenumber?.parsePhoneNumberFromString(clientPhone);
                            if (parsed?.country) clientCountry = parsed.country;
                        } catch (_) {}

                        const { data } = await trigger({
                            sosCallSessionToken: this.session.session_token,
                            providerType: callType,
                            clientPhone,
                            clientLanguage: '{{ $locale }}',
                            clientCountry,
                        });

                        if (!data || !data.success) {
                            throw new Error((data && data.message) || 'Échec du déclenchement');
                        }

                        this.maskedPhone = this.maskPhoneDisplay(clientPhone);
                        this.providerDisplayName = data.providerDisplayName || '';
                        this.state = 'call_in_progress';
                        this.startCountdown(data.delaySeconds || 240);
                    } catch (err) {
                        console.error('[SOS-Call] triggerCall error:', err);
                        const msg = err && (err.message || err.code);
                        this.callError = 'Impossible de déclencher l\'appel' + (msg ? ' ('+ msg +')' : '') + '. Veuillez réessayer.';
                    } finally {
                        this.callLoading = false;
                    }
                },

                startCountdown(seconds) {
                    this.countdown = seconds || 240;
                    this.countdownInterval = setInterval(() => {
                        this.countdown--;
                        if (this.countdown <= 0) {
                            clearInterval(this.countdownInterval);
                            this.countdownInterval = null;
                        }
                    }, 1000);
                },

                normalizePhone(raw) {
                    if (!raw) return '';
                    try {
                        const parsed = window.libphonenumber?.parsePhoneNumberFromString(raw, 'FR');
                        if (parsed && parsed.isValid()) {
                            return parsed.number;
                        }
                    } catch (_) {}
                    const cleaned = (raw || '').replace(/[^\d+]/g, '');
                    if (/^\+[1-9]\d{6,14}$/.test(cleaned)) {
                        return cleaned;
                    }
                    return '';
                },

                maskPhoneDisplay(e164) {
                    if (!e164) return '';
                    return e164.substring(0, 4) + ' ** ** ** ' + e164.substring(e164.length - 2);
                },

                formatDate(iso) {
                    if (!iso) return '';
                    try {
                        return new Date(iso).toLocaleDateString('{{ $locale }}', { year: 'numeric', month: 'long', day: 'numeric' });
                    } catch (_) {
                        return iso;
                    }
                },

                stateLabel(state) {
                    return {
                        agreement_inactive: 'Service temporairement désactivé',
                        expired: 'Votre accès a expiré',
                        quota_reached: 'Quota d\'appels atteint',
                    }[state] || 'Erreur';
                },

                stateDescription(state) {
                    return {
                        agreement_inactive: 'Le service SOS-Call n\'est pas actif pour votre partenaire actuellement. Contactez votre partenaire pour plus d\'informations.',
                        expired: 'Votre accès SOS-Call a expiré. Contactez votre partenaire pour le renouveler.',
                        quota_reached: 'Vous avez utilisé tous vos appels inclus pour cette période. Contactez votre partenaire pour étendre votre quota.',
                    }[state] || 'Une erreur est survenue.';
                },

                _firebase: null,
                async getFirebase() {
                    if (this._firebase) return this._firebase;

                    const { initializeApp, getApps, getApp } = await import('https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js');
                    const { getFunctions, httpsCallable } = await import('https://www.gstatic.com/firebasejs/10.12.2/firebase-functions.js');

                    const cfg = window.SOS_CALL_CONFIG.firebase || {};
                    const app = getApps().length ? getApp() : initializeApp({
                        apiKey: cfg.apiKey,
                        authDomain: cfg.authDomain,
                        projectId: cfg.projectId,
                    });
                    const functions = getFunctions(app, cfg.region || 'us-central1');

                    this._firebase = {
                        httpsCallable: (name) => {
                            const fn = httpsCallable(functions, name);
                            return async (data) => fn(data);
                        },
                    };
                    return this._firebase;
                },
            };
        }
    </script>

</body>
</html>
