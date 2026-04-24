<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Votre lien de connexion</title>
</head>
<body style="margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#f5f5f7;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7; padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#fff; border-radius:16px; overflow:hidden;">

<tr><td style="background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%); padding:35px 30px; text-align:center;">
<h1 style="margin:0; color:#fff; font-size:24px; font-weight:700;">🔑 Lien de connexion</h1>
<p style="margin:8px 0 0 0; color:rgba(255,255,255,0.9); font-size:14px;">Votre espace SOS-Call</p>
</td></tr>

<tr><td style="padding:35px 30px; font-size:15px; line-height:1.6; color:#1d1d1f;">

@if(!empty($body_override))
    {!! $body_override !!}
@else
    <p>Bonjour{{ $first_name ? ' ' . $first_name : '' }},</p>

    <p>Voici votre lien de connexion à votre espace personnel SOS-Call :</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:25px 0;"><tr><td align="center">
    <a href="{{ $auth_url }}" style="display:inline-block; background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%); color:#fff; text-decoration:none; padding:14px 32px; border-radius:10px; font-size:15px; font-weight:600;">
    Accéder à mon espace
    </a>
    </td></tr></table>

    <p style="color:#6b7280; font-size:13px; line-height:1.6;">
    ⏰ Ce lien expire dans <strong>15 minutes</strong> et ne peut être utilisé qu'une seule fois.<br>
    🔒 Si vous n'avez pas demandé cette connexion, ignorez simplement cet email.
    </p>

    <p style="margin-top:25px; font-size:13px; color:#6b7280;">
    Couvert par <strong>{{ $partner_name }}</strong> avec SOS-Expat
    </p>
@endif

</td></tr>

<tr><td style="background:#f9fafb; padding:15px 30px; text-align:center; font-size:12px; color:#6b7280;">
SOS-Expat · sos-expat.com
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
