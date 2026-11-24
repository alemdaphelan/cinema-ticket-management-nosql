[CmdletBinding()]
param(
    [string] $EnvFile = '.env.nosql.local',
    [switch] $RebuildApi
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$envPath = (Resolve-Path (Join-Path $root $EnvFile)).Path
$evidenceDir = Join-Path $root 'docs/evidence'
$evidencePath = Join-Path $evidenceDir 'cassandra-phase5-cqlsh.txt'
$phpCandidates = @(Get-ChildItem 'C:\laragon\bin\php\php-*\php.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending)
$phpExecutable = if ($phpCandidates.Count -gt 0) { $phpCandidates[0].FullName } else { (Get-Command php -ErrorAction Stop).Source }

function Get-EnvValue([string] $Name) {
    $line = Get-Content -LiteralPath $envPath | Where-Object { $_ -match "^$([regex]::Escape($Name))=" } | Select-Object -Last 1
    if (-not $line) { throw "Missing $Name in the selected environment file." }
    return ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

function Invoke-Cql([string] $Statement) {
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $result = & docker compose --env-file $envPath exec -T cassandra cqlsh 127.0.0.1 9042 `
        -u $script:CassandraUser -p $script:CassandraPassword -e $Statement 2>&1
    $exitCode = $LASTEXITCODE
    $ErrorActionPreference = $previousErrorAction
    if ($exitCode -ne 0) { throw "CQL verification failed: $($result -join ' ')" }
    return ($result | Where-Object { $_ -notmatch '^Warning:|^Recommendation:|RemoteException' })
}

Push-Location $root
try {
    $script:CassandraUser = Get-EnvValue 'CASSANDRA_APP_USERNAME'
    $script:CassandraPassword = Get-EnvValue 'CASSANDRA_APP_PASSWORD'
    $keyspace = Get-EnvValue 'CASSANDRA_KEYSPACE'
    $probeId = [guid]::NewGuid().ToString()
    $backupFile = '/tmp/cassandra-phase5-backup.csv'

    docker compose --env-file $envPath up -d cassandra | Out-Null
    docker compose --env-file $envPath up --force-recreate --no-deps cassandra-init | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Cassandra schema initialization failed.' }
    if ($RebuildApi) {
        docker compose --env-file $envPath up -d --build cassandra-api | Out-Null
    } else {
        docker compose --env-file $envPath up -d --no-build cassandra-api | Out-Null
    }
    if ($LASTEXITCODE -ne 0) { throw 'Cassandra API startup failed.' }

    $deadline = (Get-Date).AddMinutes(2)
    do {
        $health = docker inspect --format '{{.State.Health.Status}}' cinema_cassandra_api 2>$null
        if ($health -eq 'healthy') { break }
        Start-Sleep -Seconds 3
    } while ((Get-Date) -lt $deadline)
    if ($health -ne 'healthy') { throw 'Cassandra API did not become healthy.' }

    $previousDriver = $env:NOSQL_CASSANDRA_DRIVER
    $previousApi = $env:CASSANDRA_API_URL
    $env:NOSQL_CASSANDRA_DRIVER = 'http'
    $env:CASSANDRA_API_URL = 'http://127.0.0.1:9043'
    try {
        & $phpExecutable artisan nosql:cassandra-smoke
        if ($LASTEXITCODE -ne 0) { throw 'Laravel Cassandra smoke test failed.' }
    } finally {
        $env:NOSQL_CASSANDRA_DRIVER = $previousDriver
        $env:CASSANDRA_API_URL = $previousApi
    }

    $schema = Invoke-Cql "SELECT keyspace_name, table_name, default_time_to_live, compaction FROM system_schema.tables WHERE keyspace_name = '$keyspace';"
    $replication = Invoke-Cql "SELECT keyspace_name, replication FROM system_schema.keyspaces WHERE keyspace_name = '$keyspace';"
    # ActivityOccurred stores UTC timestamps and the bridge derives partitions in UTC.
    $utcProbeDate = [DateTime]::UtcNow
    $userQuery = Invoke-Cql "CONSISTENCY LOCAL_QUORUM; SELECT event_type, occurred_at FROM $keyspace.user_activity_by_month WHERE user_id = 1 AND month = '$($utcProbeDate.ToString('yyyy-MM'))' LIMIT 5;"
    $movieQuery = Invoke-Cql "CONSISTENCY LOCAL_QUORUM; SELECT event_type, occurred_at FROM $keyspace.movie_events_by_day WHERE movie_id = 1 AND event_date = '$($utcProbeDate.ToString('yyyy-MM-dd'))' LIMIT 5;"

    Invoke-Cql "CREATE TABLE IF NOT EXISTS $keyspace.phase5_backup_probe (probe_id uuid PRIMARY KEY, value text);" | Out-Null
    Invoke-Cql "INSERT INTO $keyspace.phase5_backup_probe (probe_id, value) VALUES ($probeId, 'backup-restore-ok');" | Out-Null
    Invoke-Cql "COPY $keyspace.phase5_backup_probe TO '$backupFile' WITH HEADER = TRUE;" | Out-Null
    Invoke-Cql "TRUNCATE $keyspace.phase5_backup_probe;" | Out-Null
    Invoke-Cql "COPY $keyspace.phase5_backup_probe FROM '$backupFile' WITH HEADER = TRUE;" | Out-Null
    $restore = Invoke-Cql "SELECT probe_id, value FROM $keyspace.phase5_backup_probe WHERE probe_id = $probeId;"
    if (($restore -join "`n") -notmatch 'backup-restore-ok') { throw 'Cassandra logical restore verification failed.' }
    Invoke-Cql "DROP TABLE $keyspace.phase5_backup_probe;" | Out-Null
    & docker compose --env-file $envPath exec -T cassandra rm -f $backupFile | Out-Null

    New-Item -ItemType Directory -Force -Path $evidenceDir | Out-Null
    @(
        'CASSANDRA PHASE 5 VERIFICATION'
        "Generated: $((Get-Date).ToString('s'))"
        'Queries below use full partition keys and contain no ALLOW FILTERING.'
        ''
        'KEYSPACE REPLICATION'
        $replication
        ''
        'TABLE TTL AND COMPACTION'
        $schema
        ''
        'USER/MONTH PARTITION QUERY (LOCAL_QUORUM)'
        $userQuery
        ''
        'MOVIE/DAY PARTITION QUERY (LOCAL_QUORUM)'
        $movieQuery
        ''
        'LOGICAL BACKUP/RESTORE PROBE'
        $restore
    ) | Set-Content -LiteralPath $evidencePath -Encoding UTF8

    Write-Host "Cassandra Phase 5 verification passed. Evidence: $evidencePath"
} finally {
    Pop-Location
}
