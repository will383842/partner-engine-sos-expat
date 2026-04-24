<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture SOS-Call {{ $period_label }}</title>
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f5f5f7; color:#1d1d1f;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.08);">

<tr><td style="background: linear-gradient(135deg,#2563eb 0%,#4f46e5 100%); padding:35px 30px; text-align:center;">
<h1 style="margin:0; color:#fff; font-size:24px; font-weight:700;">🧾 Nouvelle facture SOS-Call</h1>
<p style="margin:8px 0 0 0; color:rgba(255,255,255,0.9); font-size:14px;">{{ $period_label }}</p>
</td></tr>

<tr><td style="padding: 35px 30px;">

<p style="font-size:15px; line-height:1.6;">Bonjour,</p>

<p style="font-size:15px; line-height:1.6;">
Votre facture mensuelle SOS-Call pour la période <strong>{{ $period_label }}</strong> est maintenant disponible.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; border-radius:10px; margin:25px 0;">
<tr><td style="padding:20px;">
<p style="margin:0; font-size:13px; color:#6b7280; text-transform:uppercase; letter-spacing:1px;">Résumé</p>
<p style="margin:8px 0 4px 0; font-size:15px;">
  <strong>{{ $active_subscribers }}</strong> clients actifs
  × <strong>{{ $billing_rate }} {{ $currency }}</strong>
</p>
<p style="margin:0; font-size:26px; font-weight:700; color:#2563eb;">{{ $total_amount }} {{ $currency }}</p>
<p style="margin:12px 0 0 0; font-size:13px; color:#6b7280;">
N° facture : <strong>{{ $invoice_number }}</strong><br>
Échéance : <strong>{{ $due_date }}</strong>
</p>
</td></tr>
</table>

<h2 style="font-size:18px; margin:25px 0 12px 0;">💳 Comment régler</h2>

@if($stripe_hosted_url)
<table width="100%" cellpadding="0" cellspacing="0" style="margin:15px 0;"><tr><td align="center">
<a href="{{ $stripe_hosted_url }}" style="display:inline-block; background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%); color:#fff; text-decoration:none; padding:14px 32px; border-radius:10px; font-size:15px; font-weight:600;">
💳 Payer en ligne maintenant
</a>
</td></tr></table>
<p style="font-size:13px; color:#6b7280; text-align:center; margin:5px 0 25px 0;">Carte bancaire ou SEPA Direct Debit</p>
@endif

<p style="font-size:14px; line-height:1.6; color:#4b5563;">
<strong>Ou par virement SEPA :</strong><br>
IBAN : FR76 XXXX XXXX XXXX XXXX XXXX XXX<br>
BIC : XXXXXXXX<br>
Référence : <strong>{{ $invoice_number }}</strong>
</p>

<p style="margin-top:25px; padding:12px; background:#fef3c7; border-radius:8px; font-size:13px; color:#92400e;">
ℹ️ Appels déclenchés ce mois : {{ $calls_expert }} expert, {{ $calls_lawyer }} avocat. Grâce à la mutualisation, ces coûts ne vous sont pas refacturés.
</p>

<p style="margin-top:25px; font-size:13px; color:#6b7280;">
📎 Le détail complet figure dans la facture PDF attachée à cet email.
</p>

</td></tr>

<tr><td style="background:#f9fafb; padding:15px 30px; text-align:center; font-size:12px; color:#6b7280;">
SOS-Expat · <a href="mailto:facturation@sos-expat.com" style="color:#6b7280;">facturation@sos-expat.com</a>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
