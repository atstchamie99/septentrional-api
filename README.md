# Septentrional frontend

Interface React/Vite du site public et de l'administration de Septentrional. La structure éditoriale et les modules existants sont conservés.

## Développement

```bash
npm install
npm run dev
```

L'API Laravel doit tourner sur `http://localhost:8000`. Le proxy Vite couvre `/api/v2` et `/storage`. Configurer les variables ignorées par Git dans `.env.development` :

```dotenv
VITE_API_URL=/api/v2
VITE_API_KEY=sept_localdev.not-a-secret-local-development-only
```

Cette clé publique correspond à `ApiKeySeeder`, réservé au développement. Les informations de connexion à la base MySQL restent exclusivement côté API.

## Identité visuelle

Le bleu principal fourni est **`#2C9CF2`** (`sept-blue`). Les variantes `#1267A8` (`sept-blue-strong`) et `#084B83` (`sept-blue-dark`) donnent des textes, boutons et fonds contrastés. Les couleurs sont centralisées dans `tailwind.config.js` et `src/index.css`. Le logo reste à traiter séparément.

Les catégories multiples d'un article importé sont conservées et modifiables dans son formulaire d'administration. Les anciennes URL WordPress peuvent être résolues via l'API. Les profils sociaux affichés proviennent des paramètres du site, avec les liens propres à Septentrional.

## Vérifications

```bash
npm run type-check
npm run build
npm test -- --runInBand
```

Le build de production exige une clé API configurée dans `.env.production`. Pour le proxy SEO, renseigner aussi les variables serveur documentées dans le README de l'API.
# septentrional-api
