#!/usr/bin/env bash
# Run from a private directory outside both document roots.
set -Eeuo pipefail
trap 'code=$?; echo "Deployment stopped (exit $code, line $LINENO)." >&2; exit "$code"' ERR

export PATH="/usr/local/cpanel/3rdparty/lib/path-bin:/usr/local/bin:/usr/bin:/bin:$PATH"

deploy_root=${SEPTENTRIONAL_DEPLOY_ROOT:-$HOME/apps}
state_root=${STATE_ROOT:-$HOME/.septentrional-deploy}
front_repo=${FRONT_REPO_DIR:-$HOME/repos/septentrional-front}
api_repo=${API_REPO_DIR:-$HOME/repos/septentrional-api}
php_bin=${PHP_BIN:-}
deploy_target=${DEPLOY_TARGET:-all}
if [[ -z "$php_bin" ]]; then
  if [[ -x /opt/alt/php84/usr/bin/php ]]; then
    php_bin=/opt/alt/php84/usr/bin/php
  elif [[ -x /opt/alt/php83/usr/bin/php ]]; then
    php_bin=/opt/alt/php83/usr/bin/php
  else
    php_bin=/usr/local/bin/php
  fi
fi

umask 077
mkdir -p "$state_root"
chmod 700 "$state_root"
case "$deploy_target" in
  all|frontend|api) ;;
  *) echo "Unknown DEPLOY_TARGET: $deploy_target" >&2; exit 2 ;;
esac
exec 8>"$state_root/pull-$deploy_target.lock"
flock -n 8 || exit 0

deploy_release() {
  local name=$1
  local repo_dir=$2
  local app_base=$3
  local health_url=$4
  local kind=$5
  local app_state="$state_root/$name"
  local release revision work_dir

  mkdir -p "$app_state"
  chmod 700 "$app_state"

  if ! git -C "$repo_dir" fetch --quiet --depth=1 origin \
    +refs/heads/live-build:refs/remotes/origin/live-build; then
    echo "$name: live-build is not available yet." >&2
    return 1
  fi
  if ! release=$(git -C "$repo_dir" rev-parse --verify refs/remotes/origin/live-build); then
    echo "$name: unable to resolve live-build." >&2
    return 1
  fi
  if ! revision=$(git -C "$repo_dir" show "$release:revision"); then
    echo "$name: live-build does not contain a revision." >&2
    return 1
  fi
  if [[ ! "$revision" =~ ^[a-f0-9]{40}$ ]]; then
    echo "$name: invalid live-build revision." >&2
    return 1
  fi

  if [[ -f "$app_state/current-revision" ]] \
    && [[ "$(<"$app_state/current-revision")" == "$revision" ]]; then
    return 0
  fi
  if [[ -f "$app_state/failed-revision" ]] \
    && [[ "$(<"$app_state/failed-revision")" == "$revision" ]]; then
    echo "$name: deployment $revision already failed; remove $app_state/failed-revision to retry." >&2
    return 1
  fi

  work_dir=$(mktemp -d "$app_state/release.XXXXXX")
  if [[ "$kind" == api ]]; then
    for file in release.tar.gz server.sh backup-database.php SHA256SUMS; do
      if ! git -C "$repo_dir" show "$release:$file" > "$work_dir/$file"; then
        echo "$name: live-build is missing $file." >&2
        rm -rf "$work_dir"
        return 1
      fi
    done
  else
    for file in release.tar.gz server.sh SHA256SUMS; do
      if ! git -C "$repo_dir" show "$release:$file" > "$work_dir/$file"; then
        echo "$name: live-build is missing $file." >&2
        rm -rf "$work_dir"
        return 1
      fi
    done
  fi

  if ! (cd "$work_dir" && sha256sum --check SHA256SUMS); then
    echo "$name: live-build checksum verification failed." >&2
    rm -rf "$work_dir"
    return 1
  fi
  echo "$(date -u +%FT%TZ) Deploying $name $revision"

  if [[ "$kind" == api ]]; then
    if ! bash "$work_dir/server.sh" "$app_base" "$work_dir/release.tar.gz" \
      "$php_bin" "$health_url" "$revision" "$work_dir/backup-database.php"; then
      printf '%s\n' "$revision" > "$app_state/failed-revision"
      rm -rf "$work_dir"
      return 1
    fi
  elif ! bash "$work_dir/server.sh" "$app_base" "$work_dir/release.tar.gz" \
    "$health_url" "$revision"; then
    printf '%s\n' "$revision" > "$app_state/failed-revision"
    rm -rf "$work_dir"
    return 1
  fi

  printf '%s\n' "$revision" > "$app_state/current-revision"
  rm -f "$app_state/failed-revision"
  rm -rf "$work_dir"
  echo "$(date -u +%FT%TZ) Deployment complete: $name $revision"
}

# Do not call deploy_release in an ||/if condition: Bash would disable
# errexit throughout the function and could record a failed fetch as success.
if [[ "$deploy_target" == all || "$deploy_target" == frontend ]]; then
  deploy_release frontend "$front_repo" "$deploy_root/septentrional-front" \
    'https://front.septentrional.tg/' frontend
fi
if [[ "$deploy_target" == all || "$deploy_target" == api ]]; then
  deploy_release api "$api_repo" "$deploy_root/septentrional-api" \
    'https://api.septentrional.tg/api/v2/health' api
fi
