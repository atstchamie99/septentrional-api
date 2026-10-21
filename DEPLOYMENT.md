# Déploiement continu de l'API

Les tests tournent sur les branches `dev` et `live`. Un push sur `live` ne
déploie l'API que si les tests réussissent.

## Configuration GitHub unique

Créer un environnement GitHub nommé `live`, puis y définir :

### Secrets

- `DEPLOY_SSH_PRIVATE_KEY` : clé privée SSH dédiée à GitHub Actions ;
- `DEPLOY_SSH_KNOWN_HOSTS` : ligne `known_hosts` vérifiée du serveur o2switch.

### Variables

- `DEPLOY_HOST` : `hathor.o2switch.net` ;
- `DEPLOY_PORT` : `22` ;
- `DEPLOY_USER` : utilisateur cPanel/o2switch ;
- `DEPLOY_BASE_PATH` : `/home/UTILISATEUR/apps/septentrional-api` ;
- `PHP_BIN` : chemin de PHP 8.3 ou 8.4 sur o2switch, sinon `php` ;
- `COMPOSER_BIN` : chemin de Composer, sinon `composer`.

Dans cPanel, la racine de `api.septentrional.tg` doit être :

```text
/home/UTILISATEUR/apps/septentrional-api/current/public
```

Créer une seule fois le fichier suivant sur l'hébergement :

```text
/home/UTILISATEUR/apps/septentrional-api/shared/.env
```

Il doit contenir la configuration de production Laravel, notamment
`APP_ENV=production`, `APP_DEBUG=false`, la base MySQL, les URL du frontend et
de l'API, CORS, Sanctum, la messagerie et les clés applicatives. Il n'est jamais
copié dans Git.

Le certificat AutoSSL du sous-domaine doit être valide avant le premier
déploiement.

## Publier

```bash
git switch live
git merge --ff-only dev
git push origin live
git switch dev
```

## Retour arrière

Dans GitHub, ouvrir **Actions > Restaurer l'API live > Run workflow** et saisir
le SHA complet d'une ancienne release. Le workflow restaure le code ; une
migration de base de données destructive doit disposer de sa propre migration
inverse ou d'une sauvegarde MySQL.
