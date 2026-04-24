<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accès SOS-Call suspendus</title>
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, sans-serif; background:#f5f5f7;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#fff; border-radius:16px; overflow:hidden;">

<tr><td style="background:#dc2626; padding:35px 30px; text-align:center;">
<h1 style="margin:0; color:#fff; font-size:24px; font-weight:700;">⚠️ Accès SOS-Call suspendus</h1>
<p style="margin:8px 0 0 0; color:rgba(255,255,255,0.9); font-size:14px;">Facture impayée</p>
</td></tr>

<tr><td style="padding:35px 30px; font-size:15px; line-height:1.6; color:#1d1d1f;">

<p>Bonjour,</p>

<p>
Votre facture <strong>{{ $invoice_number }}</strong> d'un montant de <strong>{{ $total_amount }} {{ $currency }}</strong>
est restée impayée au-delà de l'échéance du {{ $due_date }} ({{ $days_overdue }} jour(s) de retard).
</p>

<div style="background:#fee2e2; border-radius:8px; padding:15px; margin:20px 0; color:#991b1b;">
<strong>⚠️ Les accès SOS-Call de <span style="font-size:18px;">{{ $suspended_count }}</span> de vos clients ont été automatiquement suspendus.</strong>
</div>

<p>
Vos clients ne peuvent plus utiliser le service d'assistance juridique d'urgence jusqu'au règlement de la facture.
</p>

<h2 style="font-size:18px;">Régulariser maintenant</h2>

@if($stripe_hosted_url)
<table width="100%" cellpadding="0" cellspacing="0" style="margin:15px 0;"><tr><td align="center">
<a href="{{ $stripe_hosted_url }}" style="display:inline-block; background:#dc2626; color:#fff; text-decoration:none; padding:14px 32px; border-radius:10px; font-size:15px; font-weight:600;">
💳 Payer la facture maintenant
</a>
</td></tr></table>
@endif

<p>
Dès réception du paiement, vos clients pourront à nouveau accéder au service SOS-Call.
</p>

<p style="margin-top:25px; font-size:13px; color:#6b7280;">
En cas de question ou litige, contactez <a href="mailto:facturation@sos-expat.com">facturation@sos-expat.com</a>
</p>

</td></tr>

<tr><td style="background:#f9fafb; padding:15px 30px; text-align:center; font-size:12px; color:#6b7280;">
SOS-Expat · facturation@sos-expat.com
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
