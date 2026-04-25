<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SOS-Expat Admin — Code de connexion</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 560px; margin: 0 auto; padding: 32px 24px; background: #f9fafb; color: #111827;">

    <div style="background: white; border-radius: 12px; padding: 32px; border: 1px solid #e5e7eb;">

        <h2 style="margin: 0 0 16px; color: #111827;">Bonjour {{ $name }},</h2>

        <p style="margin: 0 0 24px; color: #374151; line-height: 1.5;">
            Voici votre code de connexion à la console d'administration SOS-Expat. Il est valable pendant <strong>{{ $minutes }} minutes</strong>.
        </p>

        <div style="text-align: center; padding: 24px; background: #f3f4f6; border-radius: 8px; margin-bottom: 24px;">
            <div style="font-family: 'Courier New', monospace; font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #1f2937;">
                {{ $code }}
            </div>
        </div>

        <p style="margin: 0 0 8px; color: #6b7280; font-size: 14px; line-height: 1.5;">
            Si vous n'avez pas tenté de vous connecter, ignorez ce message et changez immédiatement votre mot de passe.
        </p>

        <hr style="margin: 24px 0; border: none; border-top: 1px solid #e5e7eb;">

        <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.5;">
            <strong>EN</strong> — Hello {{ $name }}, here is your SOS-Expat admin login code, valid for {{ $minutes }} minutes:
            <span style="font-family: 'Courier New', monospace; font-weight: bold; letter-spacing: 2px;">{{ $code }}</span>.
            If you didn't try to log in, ignore this email and reset your password.
        </p>
    </div>

    <p style="text-align: center; margin: 24px 0 0; color: #9ca3af; font-size: 12px;">
        SOS-Expat by WorldExpat OÜ &mdash; <a href="https://sos-expat.com" style="color: #6b7280;">sos-expat.com</a>
    </p>

</body>
</html>
