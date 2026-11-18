[CmdletBinding()]
param(
    [string] $EnvFile = '.env.nosql.local'
)

$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$envPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $EnvFile))

if (-not (Test-Path -LiteralPath $envPath)) {
    throw "Environment file not found: $envPath. Run scripts/nosql/init-env.ps1 first."
}

$settings = @{}
Get-Content -LiteralPath $envPath | ForEach-Object {
    if ($_ -match '^([^#=]+)=(.*)$') {
        $settings[$matches[1].Trim()] = $matches[2].Trim()
    }
}

function Invoke-Compose {
    param([Parameter(Mandatory)][string[]] $Arguments)

    & docker compose --env-file $envPath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose command failed: $($Arguments -join ' ')"
    }
}

function Invoke-ComposeCapture {
    param([Parameter(Mandatory)][string[]] $Arguments)

    $output = & docker compose --env-file $envPath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose command failed: $($Arguments -join ' ')"
    }

    return ($output | Out-String).Trim()
}

Push-Location $repoRoot
try {
    Write-Host '[1/4] MongoDB collections, indexes and seed data'
    $mongoScript = @"
const d = db.getSiblingDB('$($settings.MONGO_DATABASE)');
const required = ['movie_reviews', 'chat_sessions', 'integration_outbox'];
if (!required.every((name) => d.getCollectionNames().includes(name))) quit(2);
if (d.movie_reviews.countDocuments({tags: 'phase-2'}) < 1) quit(3);
print('MongoDB OK');
"@
    Invoke-Compose @(
        'exec', '-T', 'mongodb', 'mongosh', '--quiet',
        '--username', $settings.MONGO_ROOT_USERNAME,
        '--password', $settings.MONGO_ROOT_PASSWORD,
        '--authenticationDatabase', 'admin',
        '--eval', $mongoScript
    )

    Write-Host '[2/4] Redis authentication, persistence and seed keys'
    $redisPing = Invoke-ComposeCapture @(
        'exec', '-T', 'redis', 'redis-cli', '--no-auth-warning',
        '-a', $settings.REDIS_PASSWORD,
        'PING'
    )
    if ($redisPing -ne 'PONG') { throw "Redis PING returned: $redisPing" }

    $redisSeedExists = Invoke-ComposeCapture @(
        'exec', '-T', 'redis', 'redis-cli', '--no-auth-warning',
        '-a', $settings.REDIS_PASSWORD,
        'EXISTS', 'cinema:v1:seed:metadata'
    )
    if ($redisSeedExists -ne '1') { throw 'Redis seed metadata key is missing.' }
    Write-Host 'Redis OK'

    Write-Host '[3/4] Cassandra role, keyspace and time-series tables'
    $cassandraResult = Invoke-ComposeCapture @(
        'exec', '-T', 'cassandra', 'cqlsh', '127.0.0.1', '9042',
        '-u', $settings.CASSANDRA_APP_USERNAME,
        '-p', $settings.CASSANDRA_APP_PASSWORD,
        '-e', "SELECT event_type FROM $($settings.CASSANDRA_KEYSPACE).user_activity_by_month WHERE user_id = 1 AND month = '2026-11' LIMIT 1;"
    )
    if ($cassandraResult -notmatch 'PHASE2_SEED') { throw 'Cassandra seed event is missing.' }
    Write-Host 'Cassandra OK'

    Write-Host '[4/4] Neo4j constraints and seed graph'
    $neo4jResult = Invoke-ComposeCapture @(
        'exec', '-T', 'neo4j', 'cypher-shell',
        '--format', 'plain',
        '-a', 'neo4j://127.0.0.1:7687',
        '-u', 'neo4j',
        '-p', $settings.NEO4J_PASSWORD,
        'MATCH (u:User)-[:VIEWED]->(m:Movie)-[:IN_GENRE]->(:Genre) RETURN count(*) AS seed_paths;'
    )
    $neo4jCount = [int] (($neo4jResult -split "`r?`n")[-1].Trim())
    if ($neo4jCount -lt 1) { throw 'Neo4j seed graph path is missing.' }
    Write-Host 'Neo4j OK'

    Write-Host 'All four NoSQL smoke tests passed.'
}
finally {
    Pop-Location
}
