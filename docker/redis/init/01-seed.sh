#!/bin/sh
set -eu

if [ -z "${REDIS_PASSWORD:-}" ]; then
  echo "REDIS_PASSWORD is required" >&2
  exit 1
fi

redis_cli() {
  redis-cli -h redis --no-auth-warning -a "$REDIS_PASSWORD" "$@"
}

redis_cli SET cinema:v1:seed:status ready EX 86400 >/dev/null
redis_cli ZADD cinema:v1:leaderboard:movies:seed NX 10 movie:1 7 movie:2 >/dev/null
redis_cli HSET cinema:v1:seed:metadata phase 2 initialized_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >/dev/null

echo "Redis seed data is ready"
