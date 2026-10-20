# Septentrional API

API Laravel de Septentrional, reposant sur la structure éditoriale existante. Les modules articles, émissions, podcasts, vidéos, communiqués, programmation, commentaires, newsletter et administration restent disponibles.

## Configuration locale

La cible Laravel est `SeptentrionalTest`. La source WordPress est la base distincte `septentrional`, avec le préfixe `wp4e_`. Renseigner les identifiants dans `.env` uniquement. Le compte MySQL et les comptes de connexion au CMS sont distincts.

```bash
composer install
cp .env.example .env # uniquement lors d'une nouvelle installation
php artisan key:generate
php artisan migrate
php artisan db:seed --class=ApiKeySeeder
php artisan serve
```

Le frontend en développement utilise `/api/v2` via son proxy Vite et la clé publique de développement `sept_localdev.not-a-secret-local-development-only`. Ne pas lancer `ApiKeySeeder` en production.

## Import WordPress

```bash
php artisan wordpress:import --dry-run
php artisan wordpress:import --batch=100
```

L'inventaire ne modifie pas la cible. L'import lit uniquement WordPress et écrit dans la connexion Laravel par défaut. Les bases source et cible doivent être différentes. Un verrou empêche deux imports simultanés. Les opérations sont validées par élément et une relance reprend les éléments restants sans modifier les contenus déjà importés. Conserver `wordpress_imports` avec les données : cette table porte les correspondances et protège contre les doublons. Éviter de déplacer la source ou de changer son URL `home` entre deux imports.

| WordPress | Cible Laravel |
| --- | --- |
| `wp4e_users` | Auteurs `users`, désactivés, mots de passe aléatoires |
| Catégories et hiérarchie | `categories` et `parent_id` |
| Étiquettes | `etiquettes` et `article_etiquette` |
| Articles `post` | `articles`, dates UTC, vues, image principale, extrait, HTML nettoyé et SEO |
| Catégorie principale Rank Math / Yoast | `articles.categorie_id` |
| Toutes les catégories d'un article | `article_categorie` |
| Images JPEG, PNG, WebP et GIF | `media_library`, source externe |
| Pages | `pages`, visibilité, SEO et date de publication |
| Livres `3d-flip-book` / `dflip` | `pages`, liens PDF et métadonnées de reprise |
| Galerie YouTube | Page contenant la playlist, sans exécuter le plugin |
| Commentaires ordinaires | `commentaires`, modération et réponses |
| Réglages éditoriaux et réseaux sociaux | `parametres` |
| Identifiants et permaliens historiques | `wordpress_imports` |

Les révisions, caches, statistiques de plugins, menus de thème, secrets, mots de passe et tables de sécurité WordPress ne sont pas copiés. Les contenus dans la corbeille et les brouillons automatiques sont exclus. Les articles privés ou protégés par mot de passe restent des brouillons. Les publications futures restent masquées jusqu'à leur date. Le HTML WordPress d'origine est conservé dans les métadonnées internes d'import pour faciliter une reprise, sans être exposé par l'API.

La migration ne copie pas les fichiers physiques : les images et les PDF continuent à utiliser `https://www.septentrional.tg/wp-content/uploads/`. **Conserver les uploads WordPress avant toute bascule du domaine.** Une copie des fichiers et l'adaptation de leurs chemins constituent une étape distincte. Les livres sans PDF exploitable sont signalés dans le rapport et doivent être contrôlés manuellement. Aucun code de shortcode ou de plugin n'est exécuté.

Les slugs des articles et des catégories sont préservés. En cas de collision avec une entrée locale, un suffixe stable est créé. `/api/v2/legacy/resolve?path=/ancienne/url/` renvoie uniquement une destination interne dont le contenu est public. Le frontend reprend ces chemins et le proxy PHP émet une redirection 301 pour les anciens permaliens datés et les catégories. Les anciennes valeurs `_wp_old_slug` sont conservées pour une reprise ultérieure ; les anciennes URL basées sur `?p=` et les anciens changements de date ne sont pas résolus automatiquement.

Pour le proxy SEO PHP du frontend, configurer sur l'hébergement :

- `SEPTENTRIONAL_API_URL` : URL complète vers `/api/v2/seo` ;
- `SEPTENTRIONAL_SITE_URL` : URL du nouveau site ;
- `SEPTENTRIONAL_API_KEY` : clé publique d'identification de l'application.

Les comptes importés doivent être activés et leurs rôles attribués explicitement avant utilisation du CMS. Les anciens mots de passe WordPress ne permettent pas de s'y connecter.

## Vérifications

```bash
php vendor/bin/phpunit tests/Unit/WordpressContentTest.php
php artisan wordpress:import --dry-run
php scripts/check-env-example.php
php scripts/test-wordpress-import.php # après l’import dans SeptentrionalTest
```

Les tests Feature historiques utilisent SQLite et nécessitent `pdo_sqlite`. Ne jamais exécuter `migrate:fresh` contre la base WordPress. L'import initial a été vérifié dans `SeptentrionalTest`, sans écriture dans la source.

## Flash infos

Le bandeau affiche en priorité les flash infos actifs du CMS. Lorsqu’aucun flash actif et public n’est disponible, il présente automatiquement les cinq derniers articles publiés, chacun avec son lien. Les brouillons et publications futures sont exclus. Le cache est invalidé lors des modifications d’articles ou de flash infos.
