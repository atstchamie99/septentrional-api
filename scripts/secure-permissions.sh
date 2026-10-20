#!/bin/bash

# Script pour sécuriser les permissions des fichiers sensibles dans Laravel
# Exécutez ce script après chaque déploiement ou mise à jour.

set -e

echo "🔒 Application des permissions de sécurité..."

# 1. Fichiers de configuration (lecture seule pour le groupe/autres)
echo "📝 Sécurisation des fichiers de configuration..."
find config/ -type f -name "*.php" -exec chmod 600 {} \;

# 2. Fichiers de migration (lecture seule)
echo "🗃️ Sécurisation des fichiers de migration..."
find database/migrations/ -type f -name "*.php" -exec chmod 600 {} \;

# 3. Fichiers de seed (lecture seule)
echo "🌱 Sécurisation des fichiers de seed..."
find database/seeders/ -type f -name "*.php" -exec chmod 600 {} \;

# 4. Fichiers de test (lecture seule)
echo "🧪 Sécurisation des fichiers de test..."
find tests/ -type f -name "*.php" -exec chmod 600 {} \;

# 5. Fichiers de documentation (lecture seule)
echo "📖 Sécurisation des fichiers de documentation..."
find . -type f \( -name "*.md" -o -name "*.txt" \) ! -path "./.git/*" -exec chmod 600 {} \;

# 6. Fichier .env (lecture seule pour le groupe/autres)
echo "🔑 Sécurisation du fichier .env..."
if [ -f .env ]; then
    chmod 600 .env
fi

# 7. Fichiers de configuration des routes (lecture seule)
echo "🛣️ Sécurisation des fichiers de routes..."
find routes/ -type f -name "*.php" -exec chmod 600 {} \;

# 8. Fichiers de contrôleurs (lecture seule)
echo "🎛️ Sécurisation des fichiers de contrôleurs..."
find app/Http/Controllers/ -type f -name "*.php" -exec chmod 600 {} \;

# 9. Fichiers de modèles (lecture seule)
echo "🏗️ Sécurisation des fichiers de modèles..."
find app/Models/ -type f -name "*.php" -exec chmod 600 {} \;

# 10. Fichiers de middleware (lecture seule)
echo "🚪 Sécurisation des fichiers de middleware..."
find app/Http/Middleware/ -type f -name "*.php" -exec chmod 600 {} \;

# 11. Fichiers de ressources (lecture seule)
echo "🎨 Sécurisation des fichiers de ressources..."
find app/Http/Resources/ -type f -name "*.php" -exec chmod 600 {} \;

# 12. Fichiers de requêtes (lecture seule)
echo "📋 Sécurisation des fichiers de requêtes..."
find app/Http/Requests/ -type f -name "*.php" -exec chmod 600 {} \;

# 13. Fichiers de politiques (lecture seule)
echo "🛡️ Sécurisation des fichiers de politiques..."
find app/Policies/ -type f -name "*.php" -exec chmod 600 {} \;

# 14. Fichiers de services (lecture seule)
echo "🔧 Sécurisation des fichiers de services..."
find app/Services/ -type f -name "*.php" -exec chmod 600 {} \;

# 15. Fichiers de support (lecture seule)
echo "🤝 Sécurisation des fichiers de support..."
find app/Support/ -type f -name "*.php" -exec chmod 600 {} \;

# 16. Fichiers de traits (lecture seule)
echo "🧬 Sécurisation des fichiers de traits..."
find app/Traits/ -type f -name "*.php" -exec chmod 600 {} \;

# 17. Fichiers de providers (lecture seule)
echo "📦 Sécurisation des fichiers de providers..."
find app/Providers/ -type f -name "*.php" -exec chmod 600 {} \;

# 18. Fichiers de bootstrap (lecture seule)
echo "👟 Sécurisation des fichiers de bootstrap..."
find bootstrap/ -type f -name "*.php" -exec chmod 600 {} \;

# 19. Fichier artisan (exécutable)
echo "🛠️ Sécurisation du fichier artisan..."
chmod 755 artisan

# 20. Dossier storage (permissions spécifiques)
echo "💾 Configuration des permissions du dossier storage..."
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

echo "✅ Toutes les permissions ont été appliquées avec succès !"
echo ""
echo "💡 Conseils supplémentaires :"
echo "   - Exécutez ce script après chaque déploiement."
echo "   - Ne commitez jamais de fichiers .env ou de clés secrètes dans Git."
echo "   - Utilisez 'chown -R www-data:www-data .' pour définir le propriétaire (en production)."
