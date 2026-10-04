#!/usr/bin/env bash
# smoke-image.sh — smoke test de l'image de production de l'API (issue #139).
# Classe de bug visée : « tout est vert en local, l'image de prod diffère »
# (extension PHP absente, process qui ne démarre pas, file Messenger sans consommateur).
#
# Construit l'image, puis rejoue ce que Fly exécutera : les commandes de `fly.toml`
# (release_command, process app, process worker) sont LUES dans le fichier, pas recopiées.
#
# Usage : ./scripts/smoke-image.sh            (Docker requis ; ~3 min avec le build)
#         SKIP_BUILD=1 IMAGE=mon-image ./scripts/smoke-image.sh

set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

IMAGE=${IMAGE:-cordeau-api:smoke}
NET=cordeau-smoke-$$
PG=$NET-pg
APP=$NET-app
WORKER=$NET-worker
FAILURES=0

ok() { echo "✅ $1"; }
fail() {
  echo "❌ $1" >&2
  FAILURES=$((FAILURES + 1))
}

cleanup() {
  docker rm -f "$APP" "$WORKER" "$PG" >/dev/null 2>&1 || true
  docker network rm "$NET" >/dev/null 2>&1 || true
}
trap cleanup EXIT

[ "${SKIP_BUILD:-}" = "1" ] || docker build --quiet -t "$IMAGE" apps/api >/dev/null

# --- Lecture de fly.toml : une commande par ligne, découpée comme Fly le fait ---
fly_command() { # $1 = release | <nom de process>
  python3 - "$1" <<'EOF'
import shlex, sys, tomllib

with open("apps/api/fly.toml", "rb") as f:
    config = tomllib.load(f)
key = sys.argv[1]
value = config["deploy"]["release_command"] if key == "release" else config["processes"][key]
print("\n".join(shlex.split(value)))
EOF
}
read_command() { # $1 = nom du tableau à remplir, $2 = clé de fly_command
  local name=$1 line
  eval "$name=()"
  while IFS= read -r line; do
    eval "$name+=(\"\$line\")"
  done < <(fly_command "$2")
}
read_command RELEASE release
read_command APP_CMD app
read_command WORKER_CMD worker

# --- 1. Extensions PHP : tout ce que la config et le code déclarent doit être chargé ---
NEEDED=$(
  python3 - <<'EOF'
import json, re

composer = json.load(open("apps/api/composer.json"))
exts = {k[4:] for k in composer["require"] if k.startswith("ext-")}
dockerfile = open("apps/api/Dockerfile").read()
block = re.search(r"install-php-extensions((?:[ \t]*\\\n[ \t]*\S+)+)", dockerfile)
if block:
    exts |= set(re.findall(r"\S+", block.group(1).replace("\\", " ")))
print(" ".join(sorted(exts)))
EOF
)
# bcmath est absente de l'image de base ; si le code l'utilise, elle doit être installée.
if grep -rqE '\b(bcadd|bcsub|bcmul|bcdiv|bcmod|bcpow|bccomp|bcscale|bcsqrt)\(|BcMath\\' apps/api/src; then
  NEEDED="$NEEDED bcmath"
fi
# shellcheck disable=SC2086
MISSING=$(docker run --rm "$IMAGE" php -r 'foreach (array_slice($argv, 1) as $e) { if (!extension_loaded($e === "opcache" ? "Zend OPcache" : $e)) { echo $e, " "; } }' $NEEDED)
if [ -z "$MISSING" ]; then
  ok "extensions PHP chargées : $NEEDED"
else
  fail "extensions PHP absentes de l'image : $MISSING"
fi

if docker run --rm "$IMAGE" composer check-platform-reqs --no-dev >/dev/null 2>&1; then
  ok "composer check-platform-reqs (dépendances) dans l'image"
else
  fail "composer check-platform-reqs échoue dans l'image : $(docker run --rm "$IMAGE" composer check-platform-reqs --no-dev 2>&1 | grep -iE 'missing|failed' | head -5)"
fi

# --- Base de données et environnement de prod minimal ---
docker network create "$NET" >/dev/null
docker run -d --name "$PG" --network "$NET" \
  -e POSTGRES_USER=cordeau -e POSTGRES_PASSWORD=cordeau -e POSTGRES_DB=cordeau \
  postgres:18-alpine >/dev/null
# Test TCP : pendant l'initialisation, le serveur temporaire n'écoute que sur le socket unix.
for _ in $(seq 1 60); do
  docker exec "$PG" pg_isready -h 127.0.0.1 -U cordeau >/dev/null 2>&1 && break
  sleep 1
done

ENV_ARGS=(
  -e APP_ENV=prod
  -e APP_SECRET=smoke-test-secret
  -e "DATABASE_URL=postgresql://cordeau:cordeau@$PG:5432/cordeau?serverVersion=18&charset=utf8"
  -e "MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0"
)

# --- 2. release_command : exécutée telle que Fly la lance (remplace le CMD, garde l'ENTRYPOINT) ---
if RELEASE_OUT=$(docker run --rm --network "$NET" "${ENV_ARGS[@]}" "$IMAGE" "${RELEASE[@]}" 2>&1); then
  ok "release_command de fly.toml"
else
  fail "release_command de fly.toml échoue : $(printf '%s' "$RELEASE_OUT" | tail -8)"
fi
TABLE=$(docker exec "$PG" psql -U cordeau -tAc "select to_regclass('public.messenger_messages')" 2>/dev/null || true)
if [ -n "$TABLE" ]; then
  ok "table messenger_messages créée par la release_command"
else
  fail "table messenger_messages absente après la release_command (le worker ne pourrait pas consommer)"
fi

# --- 3. Process app : le noyau démarre en prod et /health répond ---
docker run -d --name "$APP" --network "$NET" -p 127.0.0.1::80 "${ENV_ARGS[@]}" "$IMAGE" "${APP_CMD[@]}" >/dev/null
PORT=$(docker port "$APP" 80/tcp | head -1 | sed 's/.*://')
STATUS=000
for _ in $(seq 1 60); do
  STATUS=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/health" || true)
  [ "$STATUS" = "200" ] && break
  sleep 1
done
if [ "$STATUS" = "200" ]; then
  ok "process app : /health répond 200 (noyau prod + base joignable)"
else
  fail "process app : /health répond $STATUS. Logs : $(docker logs "$APP" 2>&1 | tail -8)"
fi
PROTECTED=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/api/chantiers" || true)
if [ "$PROTECTED" = "401" ]; then
  ok "process app : /api/chantiers répond 401 sans jeton"
else
  fail "process app : /api/chantiers répond $PROTECTED au lieu de 401"
fi

# --- 4. Messenger : handlers câblés et worker qui consomme la file ---
HANDLERS=$(docker run --rm --network "$NET" "${ENV_ARGS[@]}" "$IMAGE" php bin/console debug:messenger 2>&1 || true)
for MESSAGE in GenerateThumbnailMessage DeleteR2ObjectMessage; do
  if printf '%s' "$HANDLERS" | grep -q "$MESSAGE"; then
    ok "handler câblé pour $MESSAGE"
  else
    fail "aucun handler câblé pour $MESSAGE dans l'image"
  fi
done

docker run -d --name "$WORKER" --network "$NET" "${ENV_ARGS[@]}" "$IMAGE" "${WORKER_CMD[@]}" >/dev/null
CONSUMING=no
for _ in $(seq 1 30); do
  if docker logs "$WORKER" 2>&1 | grep -q 'Consuming messages from transport'; then
    CONSUMING=yes
    break
  fi
  [ "$(docker inspect -f '{{.State.Running}}' "$WORKER")" = "true" ] || break
  sleep 1
done
if [ "$CONSUMING" = "yes" ] && [ "$(docker inspect -f '{{.State.Running}}' "$WORKER")" = "true" ]; then
  ok "process worker : consomme le transport async"
else
  fail "process worker ne consomme pas. Logs : $(docker logs "$WORKER" 2>&1 | tail -8)"
fi

if [ "$FAILURES" -gt 0 ]; then
  echo "smoke-image : $FAILURES échec(s)." >&2
  exit 1
fi
echo "smoke-image : tout est OK"
