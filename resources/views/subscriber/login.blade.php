<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon accès SOS-Call</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <div class="text-5xl mb-3">🆘</div>
            <h1 class="text-2xl font-bold text-gray-900">Mon accès SOS-Call</h1>
            <p class="text-sm text-gray-600 mt-2">Connectez-vous pour accéder à votre espace personnel</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 text-red-800 rounded-lg text-sm">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="/mon-acces/magic-link">
                @csrf
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Votre email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    autofocus
                    placeholder="vous@exemple.com"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                <button
                    type="submit"
                    class="w-full mt-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transition">
                    Recevoir le lien de connexion
                </button>
            </form>

            <p class="mt-6 text-xs text-gray-500 text-center">
                Vous recevrez un email avec un lien de connexion sécurisé valable 15 minutes.
            </p>
        </div>

        <div class="text-center mt-6 text-sm text-gray-600">
            En cas d'urgence, utilisez directement <a href="/sos-call" class="text-blue-600 font-semibold">sos-call.sos-expat.com</a>
        </div>
    </div>
</body>
</html>
