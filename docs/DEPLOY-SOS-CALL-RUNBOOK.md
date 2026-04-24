# SOS-Call B2B — Runbook de déploiement production

**Durée totale** : ~30 minutes (+ 15 min d'attente certbot)
**Risque** : moyen, réversible à chaque étape
**Prérequis** : accès SSH au VPS Hetzner, accès Cloudflare DNS, accès Firebase CLI, accès Stripe Dashboard

---

## ⚠️ IMPORTANT — VPS PARTAGÉ

Le VPS Hetzner `95.216.179.163` (`mail-server`, CPX, 4 GB RAM, 2 vCPU) héberge déjà :
- **Telegram Engine** (conteneurs `tg-app`, `tg-queue`, `tg-scheduler`, `tg-nginx`, `tg-postgres`, `tg-redis`) — NE PAS TOUCHER
- **Partner Engine** (conteneurs `pe-app`, `pe-queue`, `pe-scheduler`, `pe-nginx`, `pe-postgres`, `pe-redis`) — notre cible
- **Serveur mail** éventuel (Postfix/Dovecot) — NE PAS TOUCHER

### Règles de sécurité pour ce déploiement

1. **Tout changement concerne UNIQUEMENT `/opt/partner-engine`** — jamais `/opt/engine-telegram`
2. Les nouveaux vhosts Nginx sont scopés par `server_name` → aucune collision possible
3. **Avant de lancer quoi que ce soit, exécuter le pre-flight check** :

```bash
ssh root@95.216.179.163
bash /opt/partner-engine/deploy/pre-flight-check.sh
```

Ce script ne modifie RIEN — il vérifie juste ressources, ports, vhosts existants, collisions potentielles, état de la DB et des secrets.

4. Si la RAM libre est <500 MB, activer un swap temporaire AVANT Phase 5 :
```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
# (à désactiver après migration sur CPX22)
```

5. **Migration future CPX22** : pour passer au nouveau VPS, transférer uniquement `/opt/partner-engine` + dump PostgreSQL `partner_engine`. Les vhosts Nginx sont portables.

---

## Phase 0 — Préparation (hors-ligne, ~5 min)

### 0.1 Générer les secrets partagés

```bash
# Sur ta machine locale :
ENGINE_API_KEY=$(openssl rand -hex 32)
echo "ENGINE_API_KEY (copier dans .env prod + Firebase secret PARTNER_ENGINE_API_KEY):"
echo "$ENGINE_API_KEY"
```

**Note-le précieusement** — la même valeur sera utilisée côté Partner Engine (`.env`) et côté Firebase (`PARTNER_ENGINE_API_KEY`).

### 0.2 Récupérer les secrets Stripe

- Dashboard Stripe → Developers → API keys → copier `sk_live_...` → **STRIPE_SECRET**
- Dashboard Stripe → Developers → Webhooks → **Add endpoint**
  - URL : `https://partner-engine.sos-expat.com/api/webhooks/stripe/invoice-events`
  - Events à cocher : `invoice.paid`, `invoice.payment_failed`, `invoice.finalized`
  - Après création → **Reveal signing secret** → copier `whsec_...` → **STRIPE_WEBHOOK_SECRET**

---

## Phase 1 — DNS (Cloudflare, ~2 min)

Crée 3 records A (tous pointant vers le VPS Hetzner `95.216.179.163`) :

```
Type: A    Name: admin          Target: 95.216.179.163   Proxy: DNS only (grey cloud)
Type: A    Name: sos-call       Target: 95.216.179.163   Proxy: DNS only (grey cloud)
Type: A    Name: partner-engine Target: 95.216.179.163   Proxy: DNS only (grey cloud)
```

**IMPORTANT** : désactive le proxy Cloudflare (grey cloud) pour ces 3 records — certbot a besoin d'un accès HTTP direct pour valider. Tu pourras réactiver après si tu veux.

Vérifier propagation :
```bash
dig +short admin.sos-expat.com
dig +short sos-call.sos-expat.com
dig +short partner-engine.sos-expat.com
# Doit retourner : 95.216.179.163
```

---

## Phase 2 — Secrets serveur (~3 min)

### 2.1 Ajouter les nouvelles variables au `.env` production

```bash
ssh root@95.216.179.163
cd /opt/partner-engine
nano .env
```

Ajouter (remplacer les placeholders par tes vraies valeurs) :

```env
# SOS-Call B2B (2026-04-24)
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
FIREBASE_PARTNER_BRIDGE_URL=https://us-central1-sos-urgently-ac307.cloudfunctions.net
FIREBASE_PARTNER_BRIDGE_API_KEY=${ENGINE_API_KEY}
SOS_CALL_PUBLIC_URL=https://sos-call.sos-expat.com
ADMIN_PUBLIC_URL=https://admin.sos-expat.com

# Vérifier que ENGINE_API_KEY existe déjà ; sinon l'ajouter :
ENGINE_API_KEY=<valeur générée en phase 0.1>
```

### 2.2 Ajouter les secrets Firebase

```bash
# Sur ta machine locale (pas sur le VPS) :
cd sos/firebase/functions
firebase use sos-urgently-ac307

echo "https://partner-engine.sos-expat.com" | firebase functions:secrets:set PARTNER_ENGINE_URL
echo "<valeur ENGINE_API_KEY de phase 0.1>" | firebase functions:secrets:set PARTNER_ENGINE_API_KEY
```

---

## Phase 3 — SSL + Nginx (~10 min, sur VPS)

### 3.1 Copier les vhosts

```bash
ssh root@95.216.179.163
cd /opt/partner-engine
git pull origin main  # (après le push de ton code — voir Phase 5)

# Copier les 3 configs vers Nginx
sudo cp deploy/nginx-admin.sos-expat.com.conf          /etc/nginx/sites-available/
sudo cp deploy/nginx-sos-call.sos-expat.com.conf       /etc/nginx/sites-available/
sudo cp deploy/nginx-partner-engine.sos-expat.com.conf /etc/nginx/sites-available/
```

### 3.2 Obtenir les certificats SSL + activer les vhosts

```bash
sudo bash /opt/partner-engine/deploy/install-ssl.sh
```

Le script :
1. Crée un vhost temporaire pour le challenge ACME
2. Appelle certbot pour les 3 domaines
3. Active les 3 vhosts production
4. Reload Nginx

Vérifier :
```bash
curl -I https://admin.sos-expat.com
curl -I https://sos-call.sos-expat.com
curl -I https://partner-engine.sos-expat.com/up
```

---

## Phase 4 — Déploiement Firebase Functions (~5 min)

```bash
# Sur ta machine locale :
cd sos/firebase/functions
firebase login
firebase use sos-urgently-ac307
bash scripts/deploy-sos-call.sh
```

Le script :
1. Vérifie que les secrets sont bien en place
2. Build TypeScript
3. Run les tests Jest pertinents
4. Déploie **seulement** les 13 fonctions SOS-Call (pas tout le reste)
5. Vérifie la déploiement via `firebase functions:list`

---

## Phase 5 — Déploiement Partner Engine (~3 min)

### 5.1 Push du code (depuis ta machine locale)

```bash
# Repo Partner Engine
cd partner_engine_sos_expat
git push origin main
# → CI/CD GitHub Actions déclenche auto-deploy sur Hetzner
```

### 5.2 Surveiller le CI (GitHub Actions)

Ouvre : https://github.com/will383842/partner-engine-sos-expat/actions

Attend que le workflow "Deploy" passe au vert (~2 min).

### 5.3 Vérifier sur le VPS

```bash
ssh root@95.216.179.163
cd /opt/partner-engine

# Vérifier que les migrations ont tourné
docker compose exec pe-app php artisan migrate:status | tail -15
# Doit montrer 7 nouvelles migrations "Ran" (2026_04_24_000001 à 000007)

# Vérifier les routes
docker compose exec pe-app php artisan route:list | grep sos-call | wc -l
# Doit être >= 9

# Créer un admin Filament (une seule fois)
docker compose exec pe-app php artisan make:filament-user
# Nom: Ton nom
# Email: ton email
# Password: ...
```

---

## Phase 6 — Déploiement SPA React (~2 min)

```bash
# Sur ta machine locale, dans le repo sos-expat-project :
git push origin main
# → Cloudflare Pages auto-deploy (~1-2 min)
```

Vérifier : https://www.sos-expat.com/partner/tableau-de-bord (login partenaire) → section "SOS-Call ce mois" doit apparaître pour les partenaires `sos_call_active=true`.

---

## Phase 7 — Smoke tests (~2 min)

```bash
# Depuis ta machine locale
bash /path/to/partner_engine_sos_expat/deploy/smoke-test-production.sh
```

Doit tout passer au vert.

---

## Phase 8 — Test de bout-en-bout (~5 min)

### 8.1 Créer un partenaire test via Filament

1. `https://admin.sos-expat.com/admin/login` → connexion
2. Menu Partenaires → Nouveau partenaire
3. Wizard 8 étapes :
   - Nom : "Test Partner"
   - Firebase ID : `test_partner_1`
   - SOS-Call activé : ✅
   - billing_rate : 3.00
   - Devise : EUR
4. Créer

### 8.2 Créer un subscriber test

1. Menu Clients → Nouveau
2. Partenaire : Test Partner
3. Email : test@sos-expat.com
4. Téléphone : ton vrai numéro
5. Créer → **tu reçois un email avec le code SOS-Call**

### 8.3 Tester le flux subscriber

1. `https://sos-call.sos-expat.com/` → tape le code
2. "Accès confirmé" → clique Expert ou Avocat
3. Tape ton téléphone
4. Le compte à rebours démarre → dans 4 min un prestataire t'appelle

### 8.4 Générer une facture test

```bash
ssh root@95.216.179.163
cd /opt/partner-engine
docker compose exec pe-app php artisan invoices:generate-monthly --period=$(date +%Y-%m) --agreement=<id_du_partenaire_test>
```

→ Vérifier dans Filament → Facturation SOS-Call que la facture apparaît.

### 8.5 Simuler paiement Stripe

Dashboard Stripe (test mode) → Invoice → Pay. Webhook doit déclencher :
- `PartnerInvoice.status = paid`
- `ReleaseProviderPaymentsOnInvoicePaid` job → holds libérés côté Firestore

---

## Phase 9 — Rollback d'urgence

Si quelque chose casse en prod :

### Rollback Partner Engine
```bash
ssh root@95.216.179.163
cd /opt/partner-engine
git log --oneline | head -5  # repérer le commit stable précédent
git reset --hard <sha_stable>
docker compose restart pe-app pe-queue pe-scheduler
```

### Rollback migrations
```bash
docker compose exec pe-app php artisan migrate:rollback --step=7
# Ne fait que les 7 dernières migrations (les nouvelles)
```

### Rollback Firebase
```bash
# Lister les versions
firebase functions:log --only triggerSosCallFromWeb

# Rollback manuellement : redéployer la version d'avant depuis un tag git
git checkout <tag_stable>
cd sos/firebase/functions
firebase deploy --only functions:triggerSosCallFromWeb
```

### Désactiver temporairement SOS-Call
```sql
-- En cas de problème sérieux : désactiver le flag pour tous les partenaires
UPDATE agreements SET sos_call_active = FALSE;
```

Les partenaires payants (univers A) continuent à tourner normalement, seuls les subscribers SOS-Call perdent l'accès (mais pas de charge erronée).

---

## Ordre strict à respecter

```
Phase 0 (prep)
   ↓
Phase 1 (DNS — attendre propagation, ~2 min)
   ↓
Phase 2 (secrets .env + Firebase)
   ↓
Phase 3 (SSL + Nginx sur VPS)
   ↓
Phase 4 (Firebase Functions — DOIT être AVANT push Partner Engine)
   ↓
Phase 5 (push Partner Engine → migrations + auto-deploy)
   ↓
Phase 6 (push SPA → auto-deploy Cloudflare)
   ↓
Phase 7 (smoke tests)
   ↓
Phase 8 (test E2E avec partenaire réel)
```

**Ne pas sauter d'étape** — chaque phase dépend de la précédente.

---

## Check-list post-déploiement (24h)

- [ ] Logs Laravel clean (`docker compose logs -f pe-app | grep ERROR`)
- [ ] Logs Firebase clean (`firebase functions:log`)
- [ ] UptimeRobot configuré sur les 3 domaines
- [ ] 1er partenaire test configuré + 1 appel réussi
- [ ] Batch provider payments mis à jour pour lire `captured_sos_call_free`
- [ ] Email envoyé à l'équipe avec les URLs + credentials de démo
