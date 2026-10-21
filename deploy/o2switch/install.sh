#!/usr/bin/env bash
set -Eeuo pipefail

umask 077

DEPLOY_ROOT="${SEPTENTRIONAL_DEPLOY_ROOT:-$HOME/apps}"
DEPLOYER_ROOT="$DEPLOY_ROOT/septentrional-deployer"
CONFIG_ROOT="$HOME/.config/septentrional"
CONFIG_FILE="$CONFIG_ROOT/deploy.env"
SCRIPT_URL="https://raw.githubusercontent.com/atstchamie99/septentrional-api/dev/deploy/o2switch/poll-and-deploy.sh"
CRON_MARKER="septentrional-poll-and-deploy"

mkdir -p \
  "$DEPLOYER_ROOT/logs" \
  "$DEPLOYER_ROOT/repositories" \
  "$DEPLOYER_ROOT/state" \
  "$DEPLOY_ROOT/septentrional-front/releases" \
  "$DEPLOY_ROOT/septentrional-api/releases" \
  "$DEPLOY_ROOT/septentrional-api/shared/storage/app/public" \
  "$DEPLOY_ROOT/septentrional-api/shared/storage/framework/cache/data" \
  "$DEPLOY_ROOT/septentrional-api/shared/storage/framework/sessions" \
  "$DEPLOY_ROOT/septentrional-api/shared/storage/framework/views" \
  "$DEPLOY_ROOT/septentrional-api/shared/storage/logs" \
  "$CONFIG_ROOT"

curl -fsSL "$SCRIPT_URL" -o "$DEPLOYER_ROOT/poll-and-deploy.sh"
chmod 700 "$DEPLOYER_ROOT/poll-and-deploy.sh"

if [[ ! -f "$CONFIG_FILE" ]]; then
  read -r -s -p "Clé API publique utilisée par le frontend: " vite_api_key
  printf '\n'
  [[ -n "$vite_api_key" ]] || {
    echo "La clé ne peut pas être vide." >&2
    exit 1
  }
  php_bin='php'
  if [[ -x /opt/alt/php84/usr/bin/php ]]; then
    php_bin='/opt/alt/php84/usr/bin/php'
  elif [[ -x /opt/alt/php83/usr/bin/php ]]; then
    php_bin='/opt/alt/php83/usr/bin/php'
  fi
  {
    printf 'VITE_API_KEY=%q\n' "$vite_api_key"
    printf 'VITE_API_URL=%q\n' 'https://api.septentrional.tg/api/v2'
    printf 'PHP_BIN=%q\n' "$php_bin"
    printf 'COMPOSER_BIN=%q\n' 'composer'
    printf 'NODE_BIN_DIR=%q\n' '/opt/alt/alt-nodejs20/root/usr/bin'
  } >"$CONFIG_FILE"
  chmod 600 "$CONFIG_FILE"
fi

api_env="$DEPLOY_ROOT/septentrional-api/shared/.env"
if [[ ! -f "$api_env" ]]; then
  curl -fsSL \
    'https://raw.githubusercontent.com/atstchamie99/septentrional-api/dev/.env.example' \
    -o "$api_env"
  chmod 600 "$api_env"
fi

cron_command="*/5 * * * * $DEPLOYER_ROOT/poll-and-deploy.sh # $CRON_MARKER"
{
  crontab -l 2>/dev/null | grep -v "$CRON_MARKER" || true
  printf '%s\n' "$cron_command"
} | crontab -

cat <<MESSAGE

Installation terminée.

1. Configure l'API dans :
   $api_env

2. Configure les racines des sous-domaines dans cPanel :
   front.septentrional.tg -> $DEPLOY_ROOT/septentrional-front/current
   api.septentrional.tg   -> $DEPLOY_ROOT/septentrional-api/current/public

3. Le cron vérifie GitHub toutes les 5 minutes.
   Journal : $DEPLOYER_ROOT/logs/deploy.log

Le déploiement commencera seulement après la création des branches live et
la réussite de leurs workflows GitHub Actions.
MESSAGE
