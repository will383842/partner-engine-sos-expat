<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['ar']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>SOS-Call — Accès d'urgence juridique</title>
    <meta name="description" content="Accédez à votre service d'assistance juridique d'urgence SOS-Call avec votre code personnel.">

    {{-- Security headers --}}
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">

    {{-- Tailwind via CDN (Sprint 7 will migrate to Vite build) --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Alpine.js via CDN --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    {{-- libphonenumber-js for E.164 normalization --}}
    <script src="https://cdn.jsdelivr.net/npm/libphonenumber-js@1.11.20/bundle/libphonenumber-max.js"></script>

    {{-- Firebase Web SDK v10 (modular, loaded lazily when client triggers a call) --}}
    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        .sos-gradient { background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); }
        .sos-gradient-text {
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>

    <script>
        // Public config for client-side JS
        window.SOS_CALL_CONFIG = {!! $clientConfigJson !!};
    </script>
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100 text-slate-900" x-data="sosCallApp()" x-cloak>

    {{-- Header --}}
    <header class="sos-gradient text-white py-4 px-6 shadow-md">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="text-3xl">🆘</div>
                <div>
                    <div class="font-bold text-lg tracking-tight">SOS-Call</div>
                    <div class="text-xs text-white/80">Assistance juridique d'urgence</div>
                </div>
            </div>
            <div class="text-xs text-white/70 hidden sm:block">
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
                    Votre partenaire a activé votre accès SOS-Call.
                </p>
            </div>

            {{-- Toggle between code / phone+email --}}
            <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 border border-slate-200">
                <div class="flex gap-2 mb-6 p-1 bg-slate-100 rounded-xl">
                    <button @click="mode = 'code'" :class="mode === 'code' ? 'bg-white shadow font-semibold' : 'text-slate-600'" class="flex-1 py-2.5 px-4 rounded-lg transition text-sm">
                        🎫 Mon code
                    </button>
                    <button @click="mode = 'phone_email'" :class="mode === 'phone_email' ? 'bg-white shadow font-semibold' : 'text-slate-600'" class="flex-1 py-2.5 px-4 rounded-lg transition text-sm">
                        📧 Téléphone + Email
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
                            class="w-full px-4 py-3 text-lg font-mono tracking-wider rounded-xl border-2 border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition uppercase"
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
                            class="w-full px-4 py-3 text-lg rounded-xl border-2 border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition"
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
                            class="w-full px-4 py-3 text-lg rounded-xl border-2 border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition"
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
            <div class="text-center">
                <p class="text-sm text-slate-500 mb-2">Vous n'avez pas de code partenaire ?</p>
                <a :href="(window.SOS_CALL_CONFIG.frontendUrl || 'https://sos-expat.com') + '/sos-appel'" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    Accès standard payant (19€ expert / 49€ avocat) →
                </a>
            </div>
        </section>

        {{-- ==========================
             STATE 2: VERIFYING
             ========================== --}}
        <section x-show="state === 'verifying'" class="text-center py-16">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mb-6"></div>
            <h2 class="text-xl font-semibold text-slate-700">Vérification de votre couverture...</h2>
            <p class="mt-2 text-slate-500">Cela prend moins de 3 secondes</p>
        </section>

        {{-- ==========================
             STATE 3: ACCESS GRANTED
             ========================== --}}
        <section x-show="state === 'access_granted'" class="space-y-6">
            <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-6 text-center">
                <div class="text-5xl mb-3">✅</div>
                <h2 class="text-2xl font-bold text-green-900">Accès confirmé</h2>
                <p class="mt-2 text-green-800">
                    Couvert par <strong x-text="session.partner_name"></strong>
                </p>
                <template x-if="session.first_name">
                    <p class="mt-1 text-sm text-green-700">Bonjour <span x-text="session.first_name"></span></p>
                </template>
                <template x-if="session.calls_remaining !== null">
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
                    class="p-6 bg-white rounded-2xl shadow-md hover:shadow-lg transition border-2 border-blue-100 hover:border-blue-300 text-left disabled:opacity-50"
                >
                    <div class="text-4xl mb-3">👤</div>
                    <div class="font-bold text-lg text-slate-900">Expert Expat</div>
                    <p class="text-sm text-slate-600 mt-2">
                        Démarches, visa, administration locale
                    </p>
                </button>

                <button
                    x-show="session.call_types_allowed === 'both' || session.call_types_allowed === 'lawyer_only'"
                    @click="selectCallType('lawyer')"
                    :disabled="callLoading"
                    class="p-6 bg-white rounded-2xl shadow-md hover:shadow-lg transition border-2 border-red-100 hover:border-red-300 text-left disabled:opacity-50"
                >
                    <div class="text-4xl mb-3">⚖️</div>
                    <div class="font-bold text-lg text-slate-900">Avocat Local</div>
                    <p class="text-sm text-slate-600 mt-2">
                        Arrestation, accident, litige, urgence
                    </p>
                </button>
            </div>

            <div x-show="callLoading" class="text-center text-slate-600">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-blue-600 mr-2 align-middle"></div>
                Préparation de votre appel...
            </div>

            <div x-show="callError" class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-800 text-sm" x-text="callError"></div>
        </section>

        {{-- ==========================
             STATE 3b: PICK PHONE (confirm number before call)
             ========================== --}}
        <section x-show="state === 'pick_phone'" class="space-y-6">
            <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-6">
                <div class="text-4xl mb-3 text-center">📞</div>
                <h2 class="text-xl font-bold text-blue-900 text-center">Sur quel numéro vous appeler ?</h2>
                <p class="mt-2 text-sm text-blue-800 text-center">
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
                        class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
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
                <div class="text-4xl mb-3 text-center">⚠️</div>
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
                <div class="text-4xl mb-3">❌</div>
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
                    💳 Accès standard payant
                </a>
            </div>
        </section>

        {{-- ==========================
             STATE 6: RATE LIMITED
             ========================== --}}
        <section x-show="state === 'rate_limited'" class="space-y-6">
            <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-6 text-center">
                <div class="text-4xl mb-3">🚫</div>
                <h2 class="text-xl font-bold text-red-900">Trop de tentatives</h2>
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
                <div class="text-4xl mb-3">⏰</div>
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
                <div class="text-6xl mb-4">📞</div>
                <h2 class="text-2xl font-bold">Votre appel arrive</h2>
                <p class="mt-2 text-white/80">
                    <span x-text="callType === 'lawyer' ? 'Avocat Local' : 'Expert Expat'"></span>
                </p>

                <div class="mt-8 text-6xl font-bold tabular-nums">
                    <span x-text="String(Math.floor(countdown / 60)).padStart(2, '0')"></span>:<span x-text="String(countdown % 60).padStart(2, '0')"></span>
                </div>

                <p class="mt-6 text-sm text-white/80">
                    Gardez votre téléphone à portée.<br>
                    Vous allez recevoir un appel de SOS-Expat.
                </p>
            </div>

            <div class="bg-white rounded-xl p-4 border border-slate-200 text-sm text-slate-600 text-center">
                📱 Appel prévu au <strong x-text="maskedPhone"></strong>
            </div>
        </section>
    </main>

    {{-- Footer --}}
    <footer class="max-w-4xl mx-auto px-4 py-8 text-center text-xs text-slate-500">
        <p>SOS-Expat · Assistance juridique d'urgence · 24h/24 · 197 pays</p>
        <p class="mt-2">
            <a :href="(window.SOS_CALL_CONFIG.frontendUrl || 'https://sos-expat.com') + '/privacy'" class="hover:text-slate-700">Confidentialité</a>
            ·
            <a :href="(window.SOS_CALL_CONFIG.frontendUrl || 'https://sos-expat.com') + '/terms'" class="hover:text-slate-700">Conditions</a>
        </p>
    </footer>

    {{-- Alpine.js component --}}
    <script>
        function sosCallApp() {
            return {
                // State machine
                state: 'initial', // initial | verifying | access_granted | pick_phone | code_invalid | phone_match_email_mismatch | not_found | rate_limited | agreement_inactive | expired | quota_reached | call_in_progress
                mode: 'code', // 'code' | 'phone_email'

                // Form inputs
                code: '',
                phone: '',
                email: '',

                // Results
                loading: false,
                result: {},
                session: {},

                // Call state
                callLoading: false,
                callError: null,
                callType: null,
                countdown: 240, // 4 minutes (CALL_DELAY_SECONDS on Firebase)
                countdownInterval: null,
                maskedPhone: '',

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

                    // Validate phone format for phone+email mode
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
                            if (this.phone) {
                                this.maskedPhone = this.maskPhoneDisplay(this.normalizePhone(this.phone));
                            }
                        }
                    } catch (err) {
                        console.error('[SOS-Call] checkEligibility error:', err);
                        this.state = 'not_found';
                    } finally {
                        this.loading = false;
                    }
                },

                // Phone collected before call trigger (on access_granted → pick_phone flow)
                callPhoneInput: '',
                callPhoneError: null,

                selectCallType(callType) {
                    this.callType = callType;
                    this.callPhoneError = null;
                    // Pre-fill with the phone used in phone_email mode, if any
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

                        // Lazy-load Firebase Web SDK (modular, ESM from CDN)
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

                        const { data } = await trigger({
                            sosCallSessionToken: this.session.session_token,
                            providerType: callType,
                            clientPhone,
                            clientLanguage: '{{ $locale }}',
                        });

                        if (!data || !data.success) {
                            throw new Error((data && data.message) || 'Échec du déclenchement');
                        }

                        this.maskedPhone = this.maskPhoneDisplay(clientPhone);
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

                // --- Helpers ---

                normalizePhone(raw) {
                    if (!raw) return '';
                    try {
                        // libphonenumber-js global
                        const parsed = window.libphonenumber?.parsePhoneNumberFromString(raw, 'FR');
                        if (parsed && parsed.isValid()) {
                            return parsed.number; // E.164
                        }
                    } catch (_) {
                        // fall through
                    }
                    // Basic cleanup fallback
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

                // Firebase SDK lazy loader
                _firebase: null,
                async getFirebase() {
                    if (this._firebase) return this._firebase;

                    const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js');
                    const { getFunctions, httpsCallable, connectFunctionsEmulator } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-functions.js');

                    const cfg = window.SOS_CALL_CONFIG.firebase || {};
                    const app = initializeApp({
                        apiKey: cfg.apiKey,
                        authDomain: cfg.authDomain,
                        projectId: cfg.projectId,
                    });
                    const functions = getFunctions(app, cfg.region || 'us-central1');

                    this._firebase = {
                        httpsCallable: (name) => {
                            const fn = httpsCallable(functions, name);
                            // Match the v8 calling convention used in our Alpine code
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
