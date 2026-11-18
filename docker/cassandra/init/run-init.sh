#!/bin/bash
set -euo pipefail

: "${CASSANDRA_ADMIN_PASSWORD:?CASSANDRA_ADMIN_PASSWORD is required}"
: "${CASSANDRA_APP_USERNAME:?CASSANDRA_APP_USERNAME is required}"
: "${CASSANDRA_APP_PASSWORD:?CASSANDRA_APP_PASSWORD is required}"
: "${CASSANDRA_KEYSPACE:?CASSANDRA_KEYSPACE is required}"

if [[ ! "$CASSANDRA_APP_USERNAME" =~ ^[A-Za-z][A-Za-z0-9_]*$ ]]; then
  echo "CASSANDRA_APP_USERNAME contains unsupported characters" >&2
  exit 1
fi

if [[ ! "$CASSANDRA_KEYSPACE" =~ ^[A-Za-z][A-Za-z0-9_]*$ ]]; then
  echo "CASSANDRA_KEYSPACE contains unsupported characters" >&2
  exit 1
fi

if [[ ! "$CASSANDRA_ADMIN_PASSWORD" =~ ^[A-Za-z0-9._-]+$ ]] || [[ ! "$CASSANDRA_APP_PASSWORD" =~ ^[A-Za-z0-9._-]+$ ]]; then
  echo "Cassandra passwords may contain only letters, digits, dot, underscore and hyphen" >&2
  exit 1
fi

admin_password=""
for attempt in $(seq 1 30); do
  if cqlsh cassandra 9042 -u cassandra -p "$CASSANDRA_ADMIN_PASSWORD" -e "SELECT release_version FROM system.local;" >/dev/null 2>&1; then
    admin_password="$CASSANDRA_ADMIN_PASSWORD"
    break
  fi

  if cqlsh cassandra 9042 -u cassandra -p cassandra -e "SELECT release_version FROM system.local;" >/dev/null 2>&1; then
    admin_password="cassandra"
    break
  fi

  sleep 5
done

if [ -z "$admin_password" ]; then
  echo "Could not authenticate to Cassandra with the configured or bootstrap password" >&2
  exit 1
fi

for attempt in $(seq 1 30); do
  if cqlsh cassandra 9042 -u cassandra -p "$admin_password" -e "SELECT release_version FROM system.local;" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    echo "Cassandra authentication did not become ready" >&2
    exit 1
  fi
  sleep 5
done

sed \
  -e "s/__APP_USERNAME__/$CASSANDRA_APP_USERNAME/g" \
  -e "s/__APP_PASSWORD__/$CASSANDRA_APP_PASSWORD/g" \
  -e "s/__KEYSPACE__/$CASSANDRA_KEYSPACE/g" \
  -e "s/__ADMIN_PASSWORD__/$CASSANDRA_ADMIN_PASSWORD/g" \
  /init/schema.cql.template > /tmp/schema.cql

cqlsh cassandra 9042 -u cassandra -p "$admin_password" -f /tmp/schema.cql
echo "Cassandra schema and seed data are ready"
