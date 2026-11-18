[CmdletBinding()]
param(
    [string] $EnvFile = '.env.nosql.local',
    [string] $Destination = 'storage/backups/nosql'
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

$backupRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $Destination))
if (-not $backupRoot.StartsWith($repoRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Backup destination must stay inside the repository.'
}

$backupDir = Join-Path $backupRoot (Get-Date -Format 'yyyyMMdd-HHmmss')
[System.IO.Directory]::CreateDirectory($backupDir) | Out-Null

function Invoke-Compose {
    param([Parameter(Mandatory)][string[]] $Arguments)

    & docker compose --env-file $envPath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose command failed: $($Arguments -join ' ')"
    }
}

Push-Location $repoRoot
try {
    Write-Host 'Backing up MongoDB'
    Invoke-Compose @(
        'exec', '-T', 'mongodb', 'mongodump',
        '--username', $settings.MONGO_ROOT_USERNAME,
        '--password', $settings.MONGO_ROOT_PASSWORD,
        '--authenticationDatabase', 'admin',
        '--db', $settings.MONGO_DATABASE,
        '--archive=/tmp/mongodb.archive.gz', '--gzip'
    )
    Invoke-Compose @('cp', 'mongodb:/tmp/mongodb.archive.gz', (Join-Path $backupDir 'mongodb.archive.gz'))
    Invoke-Compose @('exec', '-T', 'mongodb', 'rm', '-f', '/tmp/mongodb.archive.gz')

    Write-Host 'Backing up Redis'
    Invoke-Compose @('exec', '-T', 'redis', 'redis-cli', '--no-auth-warning', '-a', $settings.REDIS_PASSWORD, 'SAVE')
    Invoke-Compose @('cp', 'redis:/data/dump.rdb', (Join-Path $backupDir 'redis-dump.rdb'))

    Write-Host 'Backing up Cassandra schema and demo rows'
    $schema = & docker compose --env-file $envPath exec -T cassandra cqlsh 127.0.0.1 9042 `
        -u $settings.CASSANDRA_APP_USERNAME -p $settings.CASSANDRA_APP_PASSWORD `
        -e "DESCRIBE KEYSPACE $($settings.CASSANDRA_KEYSPACE);"
    if ($LASTEXITCODE -ne 0) { throw 'Cassandra schema export failed.' }
    [System.IO.File]::WriteAllLines((Join-Path $backupDir 'cassandra-schema.cql'), [string[]] $schema)

    foreach ($table in @('user_activity_by_month', 'movie_events_by_day', 'audit_events_by_day')) {
        $containerPath = "/tmp/$table.csv"
        Invoke-Compose @(
            'exec', '-T', 'cassandra', 'cqlsh', '127.0.0.1', '9042',
            '-u', $settings.CASSANDRA_APP_USERNAME,
            '-p', $settings.CASSANDRA_APP_PASSWORD,
            '-e', "COPY $($settings.CASSANDRA_KEYSPACE).$table TO '$containerPath' WITH HEADER = TRUE;"
        )
        Invoke-Compose @('cp', "cassandra:$containerPath", (Join-Path $backupDir "$table.csv"))
        Invoke-Compose @('exec', '-T', 'cassandra', 'rm', '-f', $containerPath)
    }

    Write-Host 'Backing up Neo4j data volume with a short controlled stop'
    Invoke-Compose @('stop', 'neo4j')
    try {
        & docker run --rm `
            --mount 'type=volume,source=cinema_neo4j_data,target=/data,readonly' `
            --mount "type=bind,source=$backupDir,target=/backup" `
            alpine:3.22 sh -c 'tar -czf /backup/neo4j-data.tar.gz -C /data .'
        if ($LASTEXITCODE -ne 0) { throw 'Neo4j volume archive failed.' }
    }
    finally {
        Invoke-Compose @('start', 'neo4j')
    }

    $manifest = Get-ChildItem -LiteralPath $backupDir -File | Sort-Object Name | ForEach-Object {
        '{0} {1} bytes' -f $_.Name, $_.Length
    }
    [System.IO.File]::WriteAllLines((Join-Path $backupDir 'manifest.txt'), [string[]] $manifest)

    Write-Host "Backup completed: $backupDir"
}
finally {
    Pop-Location
}
