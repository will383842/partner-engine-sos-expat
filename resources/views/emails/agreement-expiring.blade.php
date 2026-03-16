<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Bonjour,</p>

    <p>Votre accord commercial <strong>{{ $agreement->name }}</strong> avec SOS-Expat
    expire dans <strong>{{ $daysRemaining }}</strong> jour(s).</p>

    <p>Date d'expiration : {{ $agreement->expires_at->format('d/m/Y') }}</p>

    <p>Si vous souhaitez renouveler votre accord, veuillez contacter votre référent SOS-Expat.</p>

    <p>Cordialement,<br>L'équipe SOS-Expat</p>
</body>
</html>
