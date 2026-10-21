# Déploiement o2switch par cron

Le serveur o2switch consulte toutes les cinq minutes les branches `live` des
dépôts publics. Lorsqu'un nouveau commit est disponible, il interroge l'API
publique GitHub Actions. Le commit est déployé uniquement si son workflow est
terminé avec succès.

Cette architecture ne requiert aucune connexion SSH entrante, aucun jeton
cPanel et aucun secret GitHub sur l'hébergement.

## Installation unique sur o2switch

Depuis le terminal cPanel :

```bash
curl -fsSL \
  https://raw.githubusercontent.com/atstchamie99/septentrional-api/dev/deploy/o2switch/install.sh \
  -o /tmp/install-septentrional.sh
bash /tmp/install-septentrional.sh
```

L'installateur demande la clé API publique incorporée au build du frontend,
prépare les dossiers et ajoute le cron sans supprimer les autres tâches.

Il reste ensuite à remplir :

```text
/home/nopr7308/apps/septentrional-api/shared/.env
```

Racines documentaires cPanel :

```text
front.septentrional.tg -> /home/nopr7308/apps/septentrional-front/current
api.septentrional.tg   -> /home/nopr7308/apps/septentrional-api/current/public
```

Le journal de déploiement est disponible dans :

```text
/home/nopr7308/apps/septentrional-deployer/logs/deploy.log
```
