# Phase 2 — Commandes à exécuter sur le VPS (ordre strict)

Lancer après que le CI/CD GitHub Actions ait terminé (regarde dans https://github.com/will383842/partner-engine-sos-expat/actions).

---

## Commande 1 — Vérifier que le déploiement s'est bien passé (~30 s)

```bash
ssh root@95.216.179.163
cd /opt/partner-engine

# Vérifier le dernier commit reçu
git log --oneline -1

# Vérifier que tous les containers tournent
docker compose ps | head -10

# Vérifier les migrations
docker compose exec pe-app php artisan migrate:status | tail -10
```

Tu dois voir :
- Dernier commit : `feat(sos-call): B2B monthly flat-fee system (7 sprints)...`
- Containers `pe-app`, `pe-queue`, `pe-scheduler`, `pe-nginx`, `pe-postgres`, `pe-redis` → tous `Up`
- 7 dernières migrations marquées `[Ran]` (noms commencent par `2026_04_24_`)

Si une migration n'est pas `[Ran]`, force-la :

```bash
docker compose exec pe-app php artisan migrate --force
```

---

## Commande 2 — Installer SSL + activer les 3 vhosts (~10 min)

```bash
# Copier les 3 vhosts vers Nginx
sudo cp /opt/partner-engine/deploy/nginx-admin.sos-expat.com.conf          /etc/nginx/sites-available/
sudo cp /opt/partner-engine/deploy/nginx-sos-call.sos-expat.com.conf       /etc/nginx/sites-available/
sudo cp /opt/partner-engine/deploy/nginx-partner-engine.sos-expat.com.conf /etc/nginx/sites-available/

# Lancer le script SSL (certbot + activation vhosts)
sudo bash /opt/partner-engine/deploy/install-ssl.sh
```

Le script :
1. Crée un challenge HTTP-01 temporaire
2. Demande 3 certificats à Let's Encrypt (1 par sous-domaine)
3. Active les 3 vhosts
4. Reload Nginx

**Attention** : si certbot échoue avec "DNS challenge failed", vérifie que les records Cloudflare sont bien **grey cloud** (pas orange).

---

## Commande 3 — Vérifier que tout marche (~1 min)

```bash
# Test les 3 sous-domaines
curl -I https://partner-engine.sos-expat.com/up
curl -I https://sos-call.sos-expat.com/
curl -I https://admin.sos-expat.com/

# Lancer le smoke test complet
bash /opt/partner-engine/deploy/smoke-test-production.sh
```

Tout doit être `HTTP/2 200` ou `HTTP/2 302` (pour admin qui redirige vers /admin/login).

---

## Commande 4 — Créer ton premier admin Filament (~1 min)

```bash
docker compose exec pe-app php artisan make:filament-user
```

On te demande interactivement :
- **Name** : ton nom
- **Email** : ton email admin
- **Password** : mot de passe fort (minimum 12 caractères)

Puis tu te connectes sur : **https://admin.sos-expat.com/admin/login**

---

## Commande 5 — Test webhook Stripe (~30 s)

```bash
bash /opt/partner-engine/deploy/test-stripe-webhook.sh
```

Doit afficher 3 tests PASS :
- Endpoint reachable
- Signature rejection working
- (Optional) Stripe CLI available

---

## Commande 6 — Monitoring en direct des logs (~continuer)

Dans une fenêtre séparée (pour surveiller) :

```bash
docker compose logs -f pe-app | grep -v "health"
```

Tu verras en temps réel toutes les requêtes (hors healthchecks).

---

## Rollback d'urgence (si ça merde)

```bash
# Rollback git + migrations
cd /opt/partner-engine
git log --oneline | head -5
git reset --hard cafd52a        # commit stable précédent
docker compose exec pe-app php artisan migrate:rollback --step=7
docker compose restart pe-app pe-queue pe-scheduler

# Désactiver SOS-Call au niveau DB
docker compose exec pe-postgres psql -U partner -d partner_engine -c "UPDATE agreements SET sos_call_active = FALSE;"
```

---

## Ordre final pour ce soir

1. ✅ Commande 1 : vérif déploiement
2. ✅ Commande 2 : SSL + vhosts (critique)
3. ✅ Commande 3 : smoke test
4. ✅ Commande 4 : admin Filament
5. ✅ Commande 5 : test webhook
6. Vérifier Stripe Dashboard : webhook montre 0 échec sur les prochains events
7. Vérifier Cloudflare Pages : SPA rebuild terminé
8. Tester en navigateur : https://sos-call.sos-expat.com/ et https://admin.sos-expat.com/admin/login
