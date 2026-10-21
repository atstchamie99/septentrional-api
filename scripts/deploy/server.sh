#!/usr/bin/env bash
# bash server.sh APP_BASE ARCHIVE PHP_BIN HEALTH_URL REVISION BACKUP_SCRIPT
set -Eeuo pipefail

app_base=${1:?Absolute application base directory required}
archive=${2:?Release archive required}
php_bin=${3:-php}
health_url=${4:?HTTPS health URL required}
revision=${5:?Revision required}
backup_script=${6:?Database backup script required}

[[ "$app_base" == /* && "$app_base" != / ]]
[[ "$revision" =~ ^[a-f0-9]{40}$ ]]
for binary in tar curl flock "$php_bin"; do command -v "$binary" >/dev/null; done
test -f "$archive"
test -f "$backup_script"
test -s "$app_base/shared/.env"

mkdir -p "$app_base/releases" "$app_base/backups" \
  "$app_base/shared/storage/app/public" \
  "$app_base/shared/storage/framework/cache/data" \
  "$app_base/shared/storage/framework/sessions" \
  "$app_base/shared/storage/framework/views" \
  "$app_base/shared/storage/logs"
app_base=$(cd "$app_base" && pwd -P)
chmod 700 "$app_base/backups"
exec 9>"$app_base/deploy.lock"
flock -n 9 || { echo 'Another API deployment is running.' >&2; exit 1; }

release="$app_base/releases/$revision"
backup="$app_base/backups/$(date -u +%Y%m%dT%H%M%SZ)-$revision"
previous=''
if [[ -L "$app_base/current" ]]; then
  previous=$(readlink -f "$app_base/current")
fi

rm -rf "$release"
mkdir -p "$release" "$backup"
chmod 700 "$backup"
umask 022
tar -xzf "$archive" -C "$release"
test -f "$release/artisan"
test -f "$release/vendor/autoload.php"
ln -sfn "$app_base/shared/.env" "$release/.env"
rm -rf "$release/storage"
ln -sfn "$app_base/shared/storage" "$release/storage"
chmod -R u+rwX "$app_base/shared/storage" "$release/bootstrap/cache"

(cd "$release" && "$php_bin" artisan about --only=environment >/dev/null)
"$php_bin" "$backup_script" "$release" "$backup"
test -s "$backup/database.sql" || test -s "$backup/database.sqlite"

if [[ -n "$previous" && -f "$previous/artisan" ]]; then
  (cd "$previous" && "$php_bin" artisan down --retry=60) || true
fi

ln -sfn "$release" "$app_base/current.next"
mv -Tf "$app_base/current.next" "$app_base/current"

rollback() {
  if [[ -n "$previous" && -d "$previous" ]]; then
    ln -sfn "$previous" "$app_base/current.next"
    mv -Tf "$app_base/current.next" "$app_base/current"
    (cd "$previous" && "$php_bin" artisan up) || true
  fi
}
trap 'rollback' ERR

(
  cd "$release"
  "$php_bin" artisan optimize:clear
  "$php_bin" artisan migrate --force
  "$php_bin" artisan storage:link || true
  "$php_bin" artisan config:cache
  "$php_bin" artisan view:cache
  "$php_bin" artisan queue:restart || true
  "$php_bin" artisan up
)

curl --fail --silent --show-error --connect-timeout 10 --max-time 30 \
  --retry 3 --retry-delay 5 "$health_url" >/dev/null
trap - ERR
printf '%s\n' "$revision" > "$app_base/current-revision"
echo "API deployment verified: $revision"
