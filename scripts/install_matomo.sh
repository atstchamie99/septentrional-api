#!/usr/bin/env bash
# =============================================================================
# install_matomo.sh — Installer Matomo sur le serveur Septentrional (API)
#
# Usage :
#   chmod +x scripts/install_matomo.sh
#   sudo bash scripts/install_matomo.sh
#
# Pré-requis :
#   - Nginx + PHP 8.1+ + MySQL/MariaDB déjà installés (même stack que Laravel)
#   - Exécuté en tant que root ou avec sudo
#
# Ce script :
#   1. Télécharge Matomo et le place dans /var/www/matomo
#   2. Crée la base de données MySQL dédiée
#   3. Génère le vhost Nginx sur analytics.septentrional.tg (ou sous-chemin)
#   4. Configure les permissions
#   5. Installe un cron pour les rapports Matomo
# =============================================================================

set -euo pipefail

# ─── Variables — À ADAPTER ───────────────────────────────────────────────────
MATOMO_VERSION="5.1.1"
MATOMO_DIR="/var/www/matomo"
MATOMO_DOMAIN="analytics.septentrional.tg"          # Sous-domaine dédié
MATOMO_DB_NAME="matomo"
MATOMO_DB_USER="matomo_user"
MATOMO_DB_PASS="$(openssl rand -base64 24)"     # Mot de passe aléatoire
MYSQL_ROOT_PASS=""                               # Laisser vide si socket auth
PHP_FPM_SOCKET="/run/php/php8.2-fpm.sock"       # Ajuster selon votre version PHP
NGINX_CONF="/etc/nginx/sites-available/matomo"
WEB_USER="www-data"                              # Utilisateur Nginx/PHP-FPM
# ─────────────────────────────────────────────────────────────────────────────

echo ""
echo "════════════════════════════════════════════════════════"
echo "  Installation Matomo ${MATOMO_VERSION} — Septentrional"
echo "════════════════════════════════════════════════════════"
echo ""

# ── 1. Dépendances PHP supplémentaires ───────────────────────────────────────
echo "→ Vérification des extensions PHP…"
PHP_BIN=$(which php)
MISSING=()

for ext in gd curl mbstring xml json zip opcache pdo_mysql; do
    $PHP_BIN -m | grep -qi "^${ext}$" || MISSING+=("php-${ext}")
done

if [ ${#MISSING[@]} -gt 0 ]; then
    echo "  → Installation : ${MISSING[*]}"
    apt-get install -y "${MISSING[@]}" 2>/dev/null || true
fi

# ── 2. Téléchargement de Matomo ──────────────────────────────────────────────
echo "→ Téléchargement de Matomo ${MATOMO_VERSION}…"
TMP_ZIP="/tmp/matomo-${MATOMO_VERSION}.zip"

if [ ! -f "$TMP_ZIP" ]; then
    curl -sL "https://builds.matomo.org/matomo-${MATOMO_VERSION}.zip" -o "$TMP_ZIP"
fi

echo "→ Extraction vers ${MATOMO_DIR}…"
mkdir -p /var/www
unzip -q "$TMP_ZIP" -d /var/www/
[ -d /var/www/matomo ] && rm -rf /var/www/matomo
mv /var/www/matomo /var/www/matomo 2>/dev/null || true
# Matomo extrait dans /var/www/matomo/ directement
ls /var/www/ | grep -i matomo | head -1 | xargs -I{} mv /var/www/{} "$MATOMO_DIR" 2>/dev/null || true

# ── 3. Permissions ───────────────────────────────────────────────────────────
echo "→ Configuration des permissions…"
chown -R "${WEB_USER}:${WEB_USER}" "$MATOMO_DIR"
find "$MATOMO_DIR" -type d -exec chmod 755 {} \;
find "$MATOMO_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "${MATOMO_DIR}/tmp"   2>/dev/null || true
chmod -R 775 "${MATOMO_DIR}/config" 2>/dev/null || true

# ── 4. Base de données MySQL ─────────────────────────────────────────────────
echo "→ Création de la base de données Matomo…"

MYSQL_CMD="mysql"
[ -n "$MYSQL_ROOT_PASS" ] && MYSQL_CMD="mysql -u root -p${MYSQL_ROOT_PASS}"

$MYSQL_CMD <<SQL
CREATE DATABASE IF NOT EXISTS \`${MATOMO_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MATOMO_DB_USER}'@'localhost' IDENTIFIED BY '${MATOMO_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${MATOMO_DB_NAME}\`.* TO '${MATOMO_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo ""
echo "  ┌─────────────────────────────────────────────┐"
echo "  │  INFOS DE CONNEXION MATOMO (à sauvegarder)  │"
echo "  │  DB Name : ${MATOMO_DB_NAME}                        │"
echo "  │  DB User : ${MATOMO_DB_USER}                   │"
echo "  │  DB Pass : ${MATOMO_DB_PASS}  │"
echo "  └─────────────────────────────────────────────┘"
echo ""

# Sauvegarder les credentials dans un fichier protégé
cat > /root/.matomo_db_credentials <<CREDS
MATOMO_DB_NAME=${MATOMO_DB_NAME}
MATOMO_DB_USER=${MATOMO_DB_USER}
MATOMO_DB_PASS=${MATOMO_DB_PASS}
CREDS
chmod 600 /root/.matomo_db_credentials
echo "  (Sauvegardé dans /root/.matomo_db_credentials)"

# ── 5. Vhost Nginx ───────────────────────────────────────────────────────────
echo "→ Création du vhost Nginx pour ${MATOMO_DOMAIN}…"

cat > "$NGINX_CONF" <<NGINX
# ──────────────────────────────────────────────
# Matomo Analytics — ${MATOMO_DOMAIN}
# ──────────────────────────────────────────────
server {
    listen 80;
    listen [::]:80;
    server_name ${MATOMO_DOMAIN};

    # HTTPS redirect (décommenter après certbot)
    # return 301 https://\$host\$request_uri;

    root ${MATOMO_DIR};
    index index.php;

    # Sécurité de base
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy strict-origin-when-cross-origin;

    # Interdire l'accès aux fichiers sensibles
    location ~ ^/(config|tmp|core|lang|libs/bower_components|misc/cron) {
        deny all;
        return 403;
    }

    location ~ \.(txt|log|json)\$ {
        deny all;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_FPM_SOCKET};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
    }

    # Cache statiques
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Matomo tracker — désactiver les logs d'accès pour les hits de tracking
    location = /matomo.php {
        access_log off;
        fastcgi_pass unix:${PHP_FPM_SOCKET};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}

# HTTPS (décommenter après : certbot --nginx -d ${MATOMO_DOMAIN})
# server {
#     listen 443 ssl http2;
#     listen [::]:443 ssl http2;
#     server_name ${MATOMO_DOMAIN};
#     ssl_certificate     /etc/letsencrypt/live/${MATOMO_DOMAIN}/fullchain.pem;
#     ssl_certificate_key /etc/letsencrypt/live/${MATOMO_DOMAIN}/privkey.pem;
#     include /etc/letsencrypt/options-ssl-nginx.conf;
#     ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
#     root ${MATOMO_DIR};
#     index index.php;
#     ... (même contenu que ci-dessus)
# }
NGINX

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/matomo 2>/dev/null || true
nginx -t && systemctl reload nginx

# ── 6. Cron Matomo (rapports + archivage) ────────────────────────────────────
echo "→ Installation du cron Matomo…"

CRON_LINE="5 * * * * ${WEB_USER} /usr/bin/php ${MATOMO_DIR}/console core:archive --url=http://${MATOMO_DOMAIN} > /var/log/matomo-archive.log 2>&1"
CRON_FILE="/etc/cron.d/matomo"

echo "$CRON_LINE" > "$CRON_FILE"
chmod 644 "$CRON_FILE"

# ── 7. Résumé final ──────────────────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════════"
echo "  ✅  Matomo installé avec succès !"
echo "════════════════════════════════════════════════════════"
echo ""
echo "  📌 Étapes suivantes :"
echo ""
echo "  1. Pointer votre DNS : ${MATOMO_DOMAIN} → IP de ce serveur"
echo ""
echo "  2. Obtenir un certificat SSL :"
echo "     sudo apt install certbot python3-certbot-nginx"
echo "     sudo certbot --nginx -d ${MATOMO_DOMAIN}"
echo ""
echo "  3. Terminer l'installation dans le navigateur :"
echo "     http://${MATOMO_DOMAIN}"
echo "     (Renseignez les credentials DB ci-dessus)"
echo ""
echo "  4. Après l'assistant web, récupérez votre Site ID"
echo "     et mettez à jour le frontend :"
echo "     VITE_MATOMO_URL=https://${MATOMO_DOMAIN}/"
echo "     VITE_MATOMO_SITE_ID=<votre_site_id>"
echo ""
echo "  Credentials DB sauvegardés : /root/.matomo_db_credentials"
echo ""
