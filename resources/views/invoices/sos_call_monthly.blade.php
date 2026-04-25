<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 40px 40px 60px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1d1d1f; line-height: 1.5; }
        h1 { font-size: 22pt; color: #2563eb; margin: 0 0 10px 0; }
        h2 { font-size: 14pt; color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-top: 25px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .header-left { width: 55%; }
        .header-right { width: 40%; text-align: right; }
        .invoice-meta { background: #f3f4f6; padding: 12px; border-radius: 6px; margin-top: 12px; }
        .invoice-meta div { margin-bottom: 4px; }
        .invoice-meta strong { color: #111827; }
        table.items { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table.items th { background: #f3f4f6; text-align: left; padding: 8px; font-weight: bold; border-bottom: 2px solid #d1d5db; }
        table.items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        table.items .right { text-align: right; }
        .total-row { background: #fef3c7; font-weight: bold; font-size: 13pt; }
        .info-box { background: #f0f9ff; border: 1px solid #7dd3fc; padding: 12px; border-radius: 6px; margin: 20px 0; font-size: 10pt; color: #075985; }
        .payment-box { margin-top: 25px; padding: 15px; border: 1px solid #d1d5db; border-radius: 6px; }
        .footer { position: fixed; bottom: 0; left: 40px; right: 40px; text-align: center; font-size: 9pt; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <h1>SOS-Expat</h1>
        <div>Assistance juridique d'urgence</div>
        <div>partner-engine.sos-expat.com</div>
    </div>
    <div class="header-right">
        <div class="invoice-meta">
            <div><strong>Facture n°</strong> {{ $invoice->invoice_number }}</div>
            <div><strong>Émise le</strong> {{ $invoice->created_at->format('d/m/Y') }}</div>
            <div><strong>Échéance</strong> {{ $invoice->due_date->format('d/m/Y') }}</div>
            <div><strong>Période</strong> {{ $invoice->period }}</div>
        </div>
    </div>
</div>

<h2>Facturé à</h2>
<div>
    <strong>{{ $agreement->partner_name ?? 'Partenaire' }}</strong><br>
    @if($agreement->company_name ?? null)
        {{ $agreement->company_name }}<br>
    @endif
    @if($agreement->vat_number ?? null)
        TVA : {{ $agreement->vat_number }}<br>
    @endif
    @if($agreement->billing_email ?? null)
        {{ $agreement->billing_email }}
    @endif
</div>

@php
    $monthlyBaseFee = (float) ($invoice->monthly_base_fee ?? 0);
    $perMemberTotal = (float) $invoice->billing_rate * $invoice->active_subscribers;
    $pricingTier = is_array($invoice->pricing_tier) ? $invoice->pricing_tier : null;
@endphp

<h2>Détail de la facture</h2>
<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="right">Quantité</th>
            <th class="right">Tarif unitaire</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @if($monthlyBaseFee > 0)
            <tr>
                <td>
                    @if($pricingTier)
                        Forfait palier SOS-Call — {{ $invoice->period }}<br>
                        <span style="color:#6b7280; font-size:9pt">
                            Palier {{ $pricingTier['min'] }}–{{ $pricingTier['max'] === null ? '∞' : $pricingTier['max'] }} clients
                            ({{ $invoice->active_subscribers }} clients ce mois)
                        </span>
                    @else
                        Forfait fixe mensuel SOS-Call — {{ $invoice->period }}<br>
                        <span style="color:#6b7280; font-size:9pt">Abonnement de base, indépendant du nombre de clients</span>
                    @endif
                </td>
                <td class="right">1</td>
                <td class="right">{{ number_format($monthlyBaseFee, 2, ',', ' ') }} {{ $invoice->billing_currency }}</td>
                <td class="right">{{ number_format($monthlyBaseFee, 2, ',', ' ') }} {{ $invoice->billing_currency }}</td>
            </tr>
        @endif
        @if($perMemberTotal > 0)
            <tr>
                <td>
                    Abonnement SOS-Call par client — {{ $invoice->period }}<br>
                    <span style="color:#6b7280; font-size:9pt">Clients couverts avec accès d'urgence juridique 24h/24</span>
                </td>
                <td class="right">{{ $invoice->active_subscribers }}</td>
                <td class="right">{{ number_format($invoice->billing_rate, 2, ',', ' ') }} {{ $invoice->billing_currency }}</td>
                <td class="right">{{ number_format($perMemberTotal, 2, ',', ' ') }} {{ $invoice->billing_currency }}</td>
            </tr>
        @endif
        @if($monthlyBaseFee == 0 && $perMemberTotal == 0)
            <tr>
                <td colspan="4" style="color:#6b7280; font-style:italic">Aucune facturation pour cette période.</td>
            </tr>
        @endif
        <tr class="total-row">
            <td colspan="3" class="right">Total à régler</td>
            <td class="right">{{ number_format($invoice->total_amount, 2, ',', ' ') }} {{ $invoice->billing_currency }}</td>
        </tr>
    </tbody>
</table>

<div class="info-box">
    <strong>Information (non facturée)</strong><br>
    Appels déclenchés ce mois via SOS-Call :
    <strong>{{ $invoice->calls_expert }}</strong> expert expat,
    <strong>{{ $invoice->calls_lawyer }}</strong> avocat local
    (coût interne : {{ number_format($invoice->total_cost, 2, ',', ' ') }} {{ $invoice->billing_currency }}).<br>
    Grâce à la mutualisation, ce coût ne vous est pas refacturé.
</div>

<div class="payment-box">
    <h3 style="margin: 0 0 10px 0; color: #2563eb;">Comment régler cette facture</h3>

    @if($invoice->stripe_hosted_url)
        <div style="margin-bottom: 12px;">
            <strong>Option 1 — Paiement en ligne (recommandé)</strong><br>
            <span style="color: #6b7280; font-size: 9pt;">Carte bancaire ou SEPA Direct Debit</span><br>
            <a href="{{ $invoice->stripe_hosted_url }}" style="color: #2563eb; word-break: break-all;">{{ $invoice->stripe_hosted_url }}</a>
        </div>
    @endif

    <div>
        <strong>Option 2 — Virement bancaire SEPA</strong><br>
        IBAN : FR76 XXXX XXXX XXXX XXXX XXXX XXX<br>
        BIC : XXXXXXXX<br>
        <strong>Référence à mentionner :</strong> {{ $invoice->invoice_number }}
    </div>

    <p style="margin-top: 15px; color: #b91c1c; font-size: 10pt;">
        ⚠️ Tout retard de paiement au-delà du {{ $invoice->due_date->format('d/m/Y') }} entraînera la suspension automatique des accès SOS-Call de vos clients.
    </p>
</div>

<div class="footer">
    SOS-Expat · Assistance juridique d'urgence · 24h/24 · 197 pays · 9 langues<br>
    partner-engine.sos-expat.com · contact@sos-expat.com
</div>

</body>
</html>
