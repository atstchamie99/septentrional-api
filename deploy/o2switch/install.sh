#!/usr/bin/env bash
set -Eeuo pipefail

umask 077
deploy_root=${SEPTENTRIONAL_DEPLOY_ROOT:-$HOME/apps}
state_root="$HOME/.septentrional-deploy"
script_url='https://raw.githubusercontent.com/atstchamie99/septentrional-api/dev/deploy/o2switch/poll-and-deploy.sh'

mkdir -p \
  "$state_root" \
  "$HOME/repos" \
  "$deploy_root/septentrional-front/releases" \
  "$deploy_root/septentrional-api/releases" \
  "$deploy_root/septentrional-api/backups" \
  "$deploy_root/septentrional-api/shared/storage/app/public" \
  "$deploy_root/septentrional-api/shared/storage/framework/cache/data" \
  "$deploy_root/septentrional-api/shared/storage/framework/sessions" \
  "$deploy_root/septentrional-api/shared/storage/framework/views" \
  "$deploy_root/septentrional-api/shared/storage/logs"

# Apache doit pouvoir traverser les parents des deux racines web. Les secrets
# et sauvegardes conservent leurs permissions privees explicites.
chmod 711 \
  "$deploy_root" \
  "$deploy_root/septentrional-front" \
  "$deploy_root/septentrional-front/releases" \
  "$deploy_root/septentrional-api" \
  "$deploy_root/septentrional-api/releases" \
  "$deploy_root/septentrional-api/shared"
find "$deploy_root/septentrional-api/shared/storage" -type d -exec chmod 755 {} +

curl -fsSL "$script_url" -o "$state_root/pull.sh"
chmod 700 "$state_root" "$state_root/pull.sh" "$deploy_root/septentrional-api/backups"

cat <<MESSAGE

Le lanceur est installe dans :
  $state_root/pull.sh

Avant le premier deploiement :
1. Cloner les deux depots dans :
   $HOME/repos/septentrional-front
   $HOME/repos/septentrional-api
2. Configurer :
   $deploy_root/septentrional-api/shared/.env
3. Lancer manuellement :
   /bin/bash $state_root/pull.sh

Le cron sera ajoute seulement apres la reussite de ce premier lancement.
MESSAGE
