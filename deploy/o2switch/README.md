# Deploiement automatique o2switch

La branche `live` est testee par GitHub Actions. Apres reussite, GitHub publie
un paquet compile sur la branche technique `live-build`. Le serveur o2switch
lit cette branche avec une Deploy key en lecture seule et le cron deploie le
paquet sans compiler le projet.

## Depots du serveur

- `~/repos/septentrional-front`
- `~/repos/septentrional-api`

Chaque depot utilise sa propre cle SSH de deploiement, ajoutee dans
**GitHub > Settings > Deploy keys**, sans autorisation d'ecriture.

## Premier lancement

```bash
/bin/bash /home/nopr7308/.septentrional-deploy/pull.sh
```

Le premier lancement doit reussir avant d'activer le cron.

## Cron cPanel

Frequence : une fois par minute (`* * * * *`). Les deux cibles utilisent des
verrous separes et produisent des journaux separes.

```bash
DEPLOY_TARGET=frontend /bin/bash /home/nopr7308/.septentrional-deploy/pull.sh >> /home/nopr7308/.septentrional-deploy/frontend.log 2>&1
DEPLOY_TARGET=api /bin/bash /home/nopr7308/.septentrional-deploy/pull.sh >> /home/nopr7308/.septentrional-deploy/api.log 2>&1
```

Les sous-domaines doivent pointer vers :

- `front.septentrional.tg` : `/home/nopr7308/apps/septentrional-front/current`
- `api.septentrional.tg` : `/home/nopr7308/apps/septentrional-api/current/public`

## Retour arriere

Les anciennes releases restent sous `apps/*/releases`. Pour revenir au code
precedent, remplacer le lien symbolique `current` par la release voulue. Les
sauvegardes de base de donnees sont conservees dans
`apps/septentrional-api/backups`.
