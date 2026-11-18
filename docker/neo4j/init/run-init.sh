#!/bin/bash
set -euo pipefail

: "${NEO4J_PASSWORD:?NEO4J_PASSWORD is required}"

for attempt in $(seq 1 30); do
  if cypher-shell -a neo4j://neo4j:7687 -u neo4j -p "$NEO4J_PASSWORD" "RETURN 1;" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    echo "Neo4j authentication did not become ready" >&2
    exit 1
  fi
  sleep 4
done

cypher-shell -a neo4j://neo4j:7687 -u neo4j -p "$NEO4J_PASSWORD" --file /init/01-schema.cypher
echo "Neo4j constraints and seed graph are ready"
