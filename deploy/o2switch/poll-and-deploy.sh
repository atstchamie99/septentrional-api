#!/usr/bin/env bash
set -Eeuo pipefail

umask 027

DEPLOY_ROOT="${SEPTENTRIONAL_DEPLOY_ROOT:-$HOME/apps}"
DEPLOYER_ROOT="$DEPLOY_ROOT/septentrional-deployer"
REPOSITORIES_ROOT="$DEPLOYER_ROOT/repositories"
STATE_ROOT="$DEPLOYER_ROOT/state"
LOG_ROOT="$DEPLOYER_ROOT/logs"
CONFIG_FILE="${SEPTENTRIONAL_DEPLOY_CONFIG:-$HOME/.config/septentrional/deploy.env}"

FRONT_REPOSITORY="atstchamie99/septentrional-front"
API_REPOSITORY="atstchamie99/septentrional-api"
FRONT_WORKFLOW="deploy-live.yml"
API_WORKFLOW="ci-backend.yml"

FRONT_GIT="$REPOSITORIES_ROOT/frontend"
API_GIT="$REPOSITORIES_ROOT/api"
FRONT_BASE="$DEPLOY_ROOT/septentrional-front"
API_BASE="$DEPLOY_ROOT/septentrional-api"

mkdir -p "$REPOSITORIES_ROOT" "$STATE_ROOT" "$LOG_ROOT"
exec >>"$LOG_ROOT/deploy.log" 2>&1

exec 9>"$DEPLOYER_ROOT/deploy.lock"
flock -n 9 || exit 0

log() {
  printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

for command_name in git curl php tar flock; do
  command -v "$command_name" >/dev/null 2>&1 || {
    log "Commande absente: $command_name"
    exit 1
  }
done

if [[ ! -r "$CONFIG_FILE" ]]; then
  log "Configuration absente: $CONFIG_FILE"
  exit 1
fi

# shellcheck disable=SC1090
source "$CONFIG_FILE"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NODE_BIN_DIR="${NODE_BIN_DIR:-/opt/alt/alt-nodejs20/root/usr/bin}"
VITE_API_URL="${VITE_API_URL:-https://api.septentrional.tg/api/v2}"

ensure_repository() {
  local repository="$1"
  local directory="$2"
  local url="https://github.com/$repository.git"

  if [[ ! -d "$directory/.git" ]]; then
    if [[ -e "$directory" ]]; then
      log "Le chemin existe sans être un dépôt Git: $directory"
      return 1
    fi
    log "Clonage initial de $repository"
    git clone --quiet --filter=blob:none --no-checkout "$url" "$directory"
  fi

  git -C "$directory" remote set-url origin "$url"
  git -C "$directory" fetch --quiet --prune origin \
    '+refs/heads/live:refs/remotes/origin/live'
}

live_sha() {
  git -C "$1" rev-parse --verify refs/remotes/origin/live 2>/dev/null || true
}

workflow_succeeded() {
  local repository="$1"
  local workflow="$2"
  local sha="$3"
  local payload

  payload="$(curl -fsS --max-time 30 \
    -H 'Accept: application/vnd.github+json' \
    -H 'X-GitHub-Api-Version: 2022-11-28' \
    "https://api.github.com/repos/$repository/actions/workflows/$workflow/runs?branch=live&event=push&head_sha=$sha&per_page=10")" || return 1

  printf '%s' "$payload" | php -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    $sha = $argv[1];
    if (!is_array($data) || !isset($data["workflow_runs"])) {
        exit(2);
    }
    foreach ($data["workflow_runs"] as $run) {
        if (($run["head_sha"] ?? "") !== $sha) {
            continue;
        }
        exit(
            ($run["status"] ?? "") === "completed"
            && ($run["conclusion"] ?? "") === "success" ? 0 : 3
        );
    }
    exit(4);
  ' "$sha"
}

activate_release() {
  local base="$1"
  local release="$2"
  ln -sfn "$release" "$base/current.next"
  mv -Tf "$base/current.next" "$base/current"
}

deploy_api() {
  local sha="$1"
  local release="$API_BASE/releases/$sha"
  local previous=""
  local composer_path

  [[ "$sha" =~ ^[0-9a-f]{40}$ ]] || return 1
  [[ -s "$API_BASE/shared/.env" ]] || {
    log "Déploiement API suspendu: $API_BASE/shared/.env est absent ou vide"
    return 1
  }

  mkdir -p \
    "$API_BASE/releases" \
    "$API_BASE/shared/storage/app/public" \
    "$API_BASE/shared/storage/framework/cache/data" \
    "$API_BASE/shared/storage/framework/sessions" \
    "$API_BASE/shared/storage/framework/views" \
    "$API_BASE/shared/storage/logs"

  rm -rf "$release"
  mkdir -p "$release"
  git -C "$API_GIT" archive "$sha" | tar -x -C "$release"

  ln -sfn "$API_BASE/shared/.env" "$release/.env"
  ln -sfn "$API_BASE/shared/storage" "$release/storage"
  chmod -R u+rwX "$API_BASE/shared/storage" "$release/bootstrap/cache"

  composer_path="$(command -v "$COMPOSER_BIN")"
  (
    cd "$release"
    "$PHP_BIN" "$composer_path" install \
      --no-dev --prefer-dist --no-interaction --no-progress \
      --optimize-autoloader
    "$PHP_BIN" artisan optimize:clear
    "$PHP_BIN" artisan migrate --force
    "$PHP_BIN" artisan storage:link || true
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan view:cache
  )

  if [[ -L "$API_BASE/current" ]]; then
    previous="$(readlink -f "$API_BASE/current")"
  fi

  if [[ -n "$previous" && -f "$previous/artisan" ]]; then
    (cd "$previous" && "$PHP_BIN" artisan down --retry=60) || true
  fi

  activate_release "$API_BASE" "$release"
  (cd "$release" && "$PHP_BIN" artisan queue:restart) || true
  (cd "$release" && "$PHP_BIN" artisan up) || true

  if ! curl -fsS --max-time 30 --retry 3 --retry-delay 3 \
    https://api.septentrional.tg/api/v2/health >/dev/null; then
    if [[ -n "$previous" && -d "$previous" ]]; then
      activate_release "$API_BASE" "$previous"
      (cd "$previous" && "$PHP_BIN" artisan up) || true
    fi
    log "Échec du contrôle API; code précédent restauré"
    return 1
  fi

  printf '%s\n' "$sha" >"$STATE_ROOT/api.sha"
  log "API déployée: $sha"
}

deploy_frontend() {
  local sha="$1"
  local build="$FRONT_BASE/build-$sha"
  local release="$FRONT_BASE/releases/$sha"
  local previous=""

  [[ "$sha" =~ ^[0-9a-f]{40}$ ]] || return 1
  [[ -n "${VITE_API_KEY:-}" ]] || {
    log "Déploiement frontend suspendu: VITE_API_KEY est absent de $CONFIG_FILE"
    return 1
  }
  [[ -x "$NODE_BIN_DIR/node" && -x "$NODE_BIN_DIR/npm" ]] || {
    log "Node.js/npm absents de $NODE_BIN_DIR"
    return 1
  }

  mkdir -p "$FRONT_BASE/releases"
  rm -rf "$build" "$release"
  mkdir -p "$build" "$release"
  git -C "$FRONT_GIT" archive "$sha" | tar -x -C "$build"

  (
    cd "$build"
    export PATH="$NODE_BIN_DIR:$PATH"
    export VITE_API_URL VITE_API_KEY
    npm ci --no-audit --no-fund
    npm run build
  )

  cp -a "$build/dist/." "$release/"
  rm -rf "$build"

  if [[ -L "$FRONT_BASE/current" ]]; then
    previous="$(readlink -f "$FRONT_BASE/current")"
  fi

  activate_release "$FRONT_BASE" "$release"

  if ! curl -fsS --max-time 30 --retry 3 --retry-delay 3 \
    https://front.septentrional.tg/ >/dev/null; then
    if [[ -n "$previous" && -d "$previous" ]]; then
      activate_release "$FRONT_BASE" "$previous"
    fi
    log "Échec du contrôle frontend; release précédente restaurée"
    return 1
  fi

  printf '%s\n' "$sha" >"$STATE_ROOT/frontend.sha"
  log "Frontend déployé: $sha"
}

poll_and_deploy() {
  local name="$1"
  local repository="$2"
  local workflow="$3"
  local git_directory="$4"
  local state_file="$5"
  local deploy_function="$6"
  local sha deployed_sha=""

  ensure_repository "$repository" "$git_directory" || return 1
  sha="$(live_sha "$git_directory")"
  [[ -n "$sha" ]] || {
    log "$name: la branche live n'existe pas encore"
    return 0
  }

  if [[ -f "$state_file" ]]; then
    deployed_sha="$(<"$state_file")"
  fi
  [[ "$sha" != "$deployed_sha" ]] || return 0

  if ! workflow_succeeded "$repository" "$workflow" "$sha"; then
    log "$name: commit $sha en attente d'un GitHub Actions réussi"
    return 0
  fi

  log "$name: déploiement du commit validé $sha"
  "$deploy_function" "$sha"
}

log "Vérification des branches live"
poll_and_deploy "API" "$API_REPOSITORY" "$API_WORKFLOW" \
  "$API_GIT" "$STATE_ROOT/api.sha" deploy_api
poll_and_deploy "Frontend" "$FRONT_REPOSITORY" "$FRONT_WORKFLOW" \
  "$FRONT_GIT" "$STATE_ROOT/frontend.sha" deploy_frontend
log "Vérification terminée"
