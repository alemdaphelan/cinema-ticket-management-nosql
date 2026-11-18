[CmdletBinding()]
param(
    [string] $EnvFile = '.env.nosql.local'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$envPath = (Resolve-Path (Join-Path $root $EnvFile)).Path
$evidenceDir = Join-Path $root 'docs/evidence'
$profilePath = Join-Path $evidenceDir 'neo4j-phase6-profile.txt'

function Get-EnvValue([string] $Name) {
    $line = Get-Content -LiteralPath $envPath | Where-Object { $_ -match "^$([regex]::Escape($Name))=" } | Select-Object -Last 1
    if (-not $line) { throw "Missing $Name in the selected environment file." }
    return ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

function Invoke-Cypher([string] $Statement) {
    $result = & docker exec -e NEO4J_PASSWORD=$script:Neo4jPassword cinema_neo4j cypher-shell `
        -a neo4j://127.0.0.1:7687 -u neo4j -p $script:Neo4jPassword $Statement 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Cypher verification failed: $($result -join ' ')" }
    return $result
}

Push-Location $root
try {
    $script:Neo4jPassword = Get-EnvValue 'NEO4J_PASSWORD'
    docker compose --env-file $envPath up -d neo4j | Out-Null
    docker compose --env-file $envPath up --force-recreate --no-deps neo4j-init | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Neo4j schema initialization failed.' }
    docker compose --env-file $envPath up -d app worker | Out-Null

    $sync = & docker exec cinema_nosql_app php artisan nosql:neo4j-sync 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Neo4j reconciliation failed: $($sync -join ' ')" }
    $smoke = & docker exec cinema_nosql_app php artisan nosql:neo4j-smoke 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Neo4j smoke test failed: $($smoke -join ' ')" }

    $constraints = Invoke-Cypher 'SHOW CONSTRAINTS YIELD name, type, labelsOrTypes, properties RETURN name, type, labelsOrTypes, properties ORDER BY name;'
    $counts = Invoke-Cypher "MATCH (n) OPTIONAL MATCH ()-[r]->() RETURN count(DISTINCT CASE WHEN n:User THEN n END) AS users, count(DISTINCT CASE WHEN n:Movie THEN n END) AS movies, count(DISTINCT CASE WHEN n:Genre THEN n END) AS genres, count(DISTINCT r) AS relationships;"
    $similarProfile = Invoke-Cypher "PROFILE MATCH (source:Movie {movie_id: 960001})-[:IN_GENRE]->(genre:Genre)<-[:IN_GENRE]-(candidate:Movie) WHERE candidate.movie_id <> source.movie_id AND candidate.status <> 'stopped' RETURN candidate.movie_id, count(DISTINCT genre) AS shared_genres ORDER BY shared_genres DESC LIMIT 8;"
    $personalizedProfile = Invoke-Cypher "PROFILE MATCH (user:User {user_id: 960001})-[:BOOKED|VIEWED|RATED]->(history:Movie)-[:IN_GENRE]->(genre:Genre)<-[:IN_GENRE]-(candidate:Movie) WHERE candidate.status <> 'stopped' AND NOT (user)-[:BOOKED]->(candidate) RETURN candidate.movie_id, count(DISTINCT genre) AS affinity ORDER BY affinity DESC LIMIT 10;"

    New-Item -ItemType Directory -Force -Path $evidenceDir | Out-Null
    @(
        'NEO4J PHASE 6 VERIFICATION'
        "Generated: $((Get-Date).ToString('s'))"
        ''
        'CONSTRAINTS'
        $constraints
        ''
        'NODE/RELATIONSHIP COUNTS'
        $counts
        ''
        'RECONCILIATION'
        $sync
        ''
        'SIMILAR MOVIES PROFILE'
        $similarProfile
        ''
        'PERSONALIZED RECOMMENDATION PROFILE'
        $personalizedProfile
        ''
        'SMOKE + IDEMPOTENCY + COLD START + LOGICAL BACKUP/RESTORE'
        $smoke
    ) | Set-Content -LiteralPath $profilePath -Encoding UTF8

    Write-Host "Neo4j Phase 6 verification passed. Evidence: $profilePath"
} finally {
    Pop-Location
}
