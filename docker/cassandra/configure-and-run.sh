#!/bin/bash
set -euo pipefail

config_file="/etc/cassandra/cassandra.yaml"

sed -ri 's|^authenticator:.*|authenticator: PasswordAuthenticator|' "$config_file"
sed -ri 's|^authorizer:.*|authorizer: CassandraAuthorizer|' "$config_file"

exec /usr/local/bin/docker-entrypoint.sh "$@"
