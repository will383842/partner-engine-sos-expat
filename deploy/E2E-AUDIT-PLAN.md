# Audit E2E production — 22 scénarios

## Architecture vérifiée

```
[Browser] ─HTTPS──▶ [Cloudflare DNS (grey cloud)]
                             │
                             ▼
                    [VPS Hetzner 95.216.179.163]
                    ├─ Host Nginx (SSL termination)
                    │    ├─ admin.sos-expat.com          → 127.0.0.1:8083 (pe-nginx)
                    │    ├─ sos-call.sos-expat.com       → 127.0.0.1:8083
                    │    └─ partner-engine.sos-expat.com → 127.0.0.1:8083
                    │
                    ▼
                  [pe-nginx] ──fastcgi──▶ [pe-app] (Laravel PHP-FPM)
                                                │
                                                ├──▶ [pe-postgres] partner_engine
                                                ├──▶ [pe-redis] sessions/queue/cache
                                                │
                                                └──▶ [Firebase Functions]
                                                         us-central1:
                                                         - checkSosCallCode
                                                         - triggerSosCallFromWeb
                                                         - releaseProviderPayments
                                                         europe-west1:
                                                         - createAndScheduleCall
                                                         europe-west3:
                                                         - consolidatedOnCallCompleted
```

## Scénarios à tester — 22 cas

### Partie A — Infrastructure (6 checks, faits)
- [x] A1 — DNS résolution 3 sous-domaines
- [x] A2 — Certificats SSL 3 domaines
- [x] A3 — Laravel /up health
- [x] A4 — API health detailed
- [x] A5 — Filament admin accessible
- [x] A6 — 5 Firebase functions déployées

### Partie B — Administration Filament (6 scénarios)
- [ ] B1 — Login admin → accès dashboard
- [ ] B2 — Widget stats overview (6 cards)
- [ ] B3 — Création partenaire wizard 5 étapes (small : 1.50€, lawyer_only)
- [ ] B4 — Création partenaire wizard (large : 5€, both, hierarchy cabinet/région)
- [ ] B5 — Génération clé API partenaire (avec copie unique)
- [ ] B6 — Filtrage subscribers par partenaire/cabinet/région

### Partie C — Flux subscriber (5 scénarios)
- [ ] C1 — /sos-call → code invalide → message "not found"
- [ ] C2 — /sos-call → code valide → "access_granted" → pick provider
- [ ] C3 — /sos-call → code expiré → message "expired"
- [ ] C4 — /sos-call → partner inactive → message "agreement_inactive"
- [ ] C5 — /mon-acces/login → magic link → dashboard subscriber

### Partie D — Dashboard partenaire React (4 scénarios)
- [ ] D1 — /partner/tableau-de-bord → section SOS-Call conditionnelle
- [ ] D2 — /partner/factures → liste factures + download PDF
- [ ] D3 — /partner/activite-sos-call → 6 sections (KPIs, timeline, hierarchy, top 20, calls, export CSV)
- [ ] D4 — /partner/abonnes → colonne code SOS-Call copiable

### Partie E — Flux appel E2E (3 scénarios critiques)
- [ ] E1 — Client payant classique (univers A, zéro régression)
- [ ] E2 — Subscriber avec code via /sos-call → appel gratuit → countdown 240s
- [ ] E3 — Client dans CallCheckout → coche "J'ai un code" → validation → skip Stripe

### Partie F — Commissions et holds (4 scénarios critiques)
- [ ] F1 — Appel payant A → onCallCompleted crée commissions 5 rôles
- [ ] F2 — Appel SOS-Call B → onCallCompleted SKIP toutes commissions
- [ ] F3 — Appel SOS-Call B → payment.status = "pending_partner_invoice" + availableFromDate +60j
- [ ] F4 — Invoice Stripe paid webhook → release holds → payment.status = "captured_sos_call_free"

---

## Ordre d'exécution recommandé

1. **Partie A** : ✅ DÉJÀ VALIDÉE (14/15 tests smoke automatiques)
2. **Partie B1-B3** : toi, manuel dans navigateur (~10 min)
3. **Partie C** : toi, manuel dans navigateur (~10 min)
4. **Partie D** : toi, manuel dans navigateur (~10 min)
5. **Partie E** : toi, nécessite de vrais numéros de téléphone pour Twilio (~15 min)
6. **Partie F** : moi, via tests automatiques PHPUnit en production (déjà 310 tests passent en local)

Total : **~45 min** de tests manuels bien cadrés.
