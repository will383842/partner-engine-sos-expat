<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email envoyé</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full text-center">
        <div class="text-6xl mb-4">📧</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-3">Vérifiez votre boîte mail</h1>
        <p class="text-gray-600 mb-6">
            Si votre email <strong>{{ $email }}</strong> est enregistré,
            vous recevrez un lien de connexion dans quelques instants.
        </p>
        <div class="bg-white rounded-2xl shadow-xl p-6 text-sm text-gray-700">
            <p class="mb-3">⏰ Le lien est valable <strong>15 minutes</strong></p>
            <p class="mb-3">🔒 Usage unique — à utiliser rapidement</p>
            <p>Vérifiez aussi votre dossier spam si nécessaire.</p>
        </div>
        <a href="/mon-acces" class="inline-block mt-6 text-sm text-blue-600 font-semibold hover:underline">
            ← Retour
        </a>
    </div>
</body>
</html>
