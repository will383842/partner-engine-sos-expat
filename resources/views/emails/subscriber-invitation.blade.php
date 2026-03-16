<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Bonjour {{ $subscriber->first_name ?? '' }},</p>

    <p>{{ $partnerName }} a négocié un accès privilégié à SOS-Expat pour vous.</p>

    <p>Vous bénéficiez de :</p>
    <ul>
        <li>✅ {{ $discountLabel }} sur chaque consultation</li>
        <li>✅ Accès à des avocats et experts expatriation dans 197 pays</li>
        <li>✅ Disponible 24/7 en 9 langues</li>
    </ul>

    <p>👉 <a href="{{ $invitationLink }}">Activez votre accès</a></p>

    <p>Ce lien est personnel et réservé aux membres de {{ $partnerName }}.</p>
</body>
</html>
