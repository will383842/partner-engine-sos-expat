<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SOS-Expat Admin — 2FA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 max-w-md w-full">

        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/30 mb-4">
                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v3m0-3h3m-3 0H9m12-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Vérification en deux étapes</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                Un code à 6 chiffres a été envoyé à <strong>{{ Auth::user()->email }}</strong>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Valable pendant {{ $expiresInMinutes }} minutes
            </p>
        </div>

        @if(session('status'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-sm text-green-800 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-800 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/admin/2fa-verify" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Code de connexion
                </label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    class="w-full text-center text-3xl font-mono tracking-widest px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="••••••"
                />
            </div>

            <button
                type="submit"
                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors"
            >
                Vérifier
            </button>
        </form>

        <div class="mt-6 text-center text-sm">
            <form method="POST" action="/admin/2fa-resend" class="inline">
                @csrf
                <button type="submit" class="text-blue-600 dark:text-blue-400 hover:underline">
                    Renvoyer un code
                </button>
            </form>
            <span class="text-gray-300 dark:text-gray-600 mx-2">·</span>
            <form method="POST" action="/admin/logout" class="inline">
                @csrf
                <button type="submit" class="text-gray-500 dark:text-gray-400 hover:underline">
                    Se déconnecter
                </button>
            </form>
        </div>

    </div>

</body>
</html>
