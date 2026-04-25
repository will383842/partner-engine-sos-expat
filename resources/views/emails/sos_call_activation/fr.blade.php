<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre accès SOS-Call est activé</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f7; color: #1d1d1f;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f7; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                🆘 SOS-Call activé
                            </h1>
                            <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 15px;">
                                Votre accès juridique d'urgence 24h/24
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #1d1d1f;">
                                Bonjour {{ $first_name ?: 'cher client' }},
                            </p>

                            <p style="margin: 0 0 25px 0; font-size: 16px; line-height: 1.6; color: #1d1d1f;">
                                Dans le cadre de votre contrat avec <strong>{{ $partner_name }}</strong>, vous bénéficiez d'un accès <strong>gratuit</strong> au service SOS-Call de SOS-Expat.
                            </p>

                            {{-- Code card --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 12px; margin: 30px 0;">
                                <tr>
                                    <td style="padding: 30px; text-align: center;">
                                        <p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">
                                            Votre code d'accès personnel
                                        </p>
                                        <p style="margin: 0; font-size: 28px; font-weight: 700; color: #1f2937; letter-spacing: 3px; font-family: 'SF Mono', Menlo, Monaco, monospace;">
                                            {{ $sos_call_code }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- Identity recap card --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 8px; margin: 0 0 30px 0;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #78350f;">
                                            📋 Vos coordonnées enregistrées par {{ $partner_name }}
                                        </p>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 14px; color: #1d1d1f;">
                                            <tr>
                                                <td style="padding: 4px 0; width: 40%; color: #6b7280;">Nom</td>
                                                <td style="padding: 4px 0; font-weight: 600;">{{ $full_name ?: '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; color: #6b7280;">Email</td>
                                                <td style="padding: 4px 0; font-weight: 600;">{{ $email }}</td>
                                            </tr>
                                            @if($phone)
                                            <tr>
                                                <td style="padding: 4px 0; color: #6b7280;">Téléphone</td>
                                                <td style="padding: 4px 0; font-weight: 600;">{{ $phone }}</td>
                                            </tr>
                                            @endif
                                            @if($country)
                                            <tr>
                                                <td style="padding: 4px 0; color: #6b7280;">Pays</td>
                                                <td style="padding: 4px 0; font-weight: 600;">{{ $country }}</td>
                                            </tr>
                                            @endif
                                        </table>
                                        <p style="margin: 12px 0 0 0; font-size: 12px; color: #92400e; line-height: 1.5;">
                                            Si une de ces informations est incorrecte, contactez votre référent chez <strong>{{ $partner_name }}</strong> pour la corriger — sinon vous risquez de ne pas pouvoir vous identifier en cas de perte du code.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 30px 0 15px 0; font-size: 20px; font-weight: 700; color: #1d1d1f;">
                                🆘 En cas de problème à l'étranger :
                            </h2>

                            <ol style="margin: 0 0 25px 0; padding-left: 20px; font-size: 15px; line-height: 1.8; color: #1d1d1f;">
                                <li>Rendez-vous sur <a href="{{ $sos_call_url }}" style="color: #2563eb; text-decoration: none; font-weight: 600;">{{ str_replace('https://', '', $sos_call_url) }}</a></li>
                                <li>Entrez votre code d'accès personnel ci-dessus</li>
                                <li>Choisissez entre Expert Expat ou Avocat Local</li>
                                <li>En moins de 5 minutes, un professionnel vous appelle</li>
                            </ol>

                            {{-- Services --}}
                            <h2 style="margin: 30px 0 15px 0; font-size: 20px; font-weight: 700; color: #1d1d1f;">
                                Services disponibles :
                            </h2>

                            @if($call_types_allowed === 'both' || $call_types_allowed === 'expat_only')
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f0f9ff; border-radius: 8px; margin-bottom: 10px;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <strong style="color: #0369a1;">👤 Expert Expat</strong><br>
                                        <span style="color: #4b5563; font-size: 14px;">Démarches administratives, visa, paperasse locale</span>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            @if($call_types_allowed === 'both' || $call_types_allowed === 'lawyer_only')
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fef3f2; border-radius: 8px; margin-bottom: 10px;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <strong style="color: #b91c1c;">⚖️ Avocat Local</strong><br>
                                        <span style="color: #4b5563; font-size: 14px;">Arrestation, accident, litige, urgence juridique</span>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            {{-- Key facts --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
                                <tr>
                                    <td style="padding: 10px 0; font-size: 14px; color: #4b5563;">
                                        ✓ Disponible 24h/24, 7j/7<br>
                                        ✓ 197 pays couverts<br>
                                        ✓ 9 langues disponibles<br>
                                        ✓ Gratuit — inclus dans votre contrat avec {{ $partner_name }}
                                        @if($expires_at)
                                            <br>✓ Valable jusqu'au {{ $expires_at }}
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            {{-- CTA --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 40px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $dashboard_url }}" style="display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 12px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                                            Accéder à mon espace
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280; line-height: 1.6;">
                                <strong>Conservez ce code précieusement.</strong> Il est unique et personnel.<br>
                                En cas de perte, vous pouvez aussi vous identifier avec votre téléphone et email.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; text-align: center; font-size: 12px; color: #6b7280;">
                            <p style="margin: 0;">
                                SOS-Expat — Assistance juridique d'urgence<br>
                                <a href="https://sos-expat.com" style="color: #6b7280; text-decoration: none;">sos-expat.com</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
