[CmdletBinding()]
param([string] $EnvFile = '.env.nosql.local')

$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$envPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $EnvFile))
if (-not (Test-Path -LiteralPath $envPath)) { throw "Environment file not found: $envPath" }

$settings = @{}
Get-Content -LiteralPath $envPath | ForEach-Object {
    if ($_ -match '^([^#=]+)=(.*)$') { $settings[$matches[1].Trim()] = $matches[2].Trim() }
}

Push-Location $repoRoot
try {
    & powershell -ExecutionPolicy Bypass -File scripts/nosql/mongodb-phase4-test.ps1 -EnvFile $EnvFile
    if ($LASTEXITCODE -ne 0) { throw 'MongoDB backup/restore probe failed.' }

    $redisKey = 'nosql:phase7:restore:' + [guid]::NewGuid().ToString('N')
    $redisValue = 'phase7-backup-restore-ok'
    & docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD SET $redisKey $redisValue | Out-Null
    $backupValue = (& docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD GET $redisKey | Out-String).Trim()
    & docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD DEL $redisKey | Out-Null
    & docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD SET $redisKey $backupValue | Out-Null
    $restoredValue = (& docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD GET $redisKey | Out-String).Trim()
    & docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD DEL $redisKey | Out-Null
    if ($restoredValue -ne $redisValue) { throw 'Redis logical restore probe failed.' }

    & powershell -ExecutionPolicy Bypass -File scripts/nosql/cassandra-phase5-test.ps1 -EnvFile $EnvFile
    if ($LASTEXITCODE -ne 0) { throw 'Cassandra backup/restore probe failed.' }

    & powershell -ExecutionPolicy Bypass -File scripts/nosql/neo4j-phase6-test.ps1 -EnvFile $EnvFile
    if ($LASTEXITCODE -ne 0) { throw 'Neo4j backup/restore probe failed.' }

    $path = Join-Path $repoRoot 'docs\evidence\phase7-backup-restore.txt'
    [System.IO.Directory]::CreateDirectory((Split-Path $path)) | Out-Null
    [System.IO.File]::WriteAllLines($path, @(
        'Phase 7 logical backup/restore probes: PASS'
        'MongoDB: temporary database archive, drop, restore, count and TTL index verified'
        'Redis: probe value backed up, deleted, restored and compared'
        'Cassandra: probe table exported, truncated, restored and queried'
        'Neo4j: probe movie deleted, restored and graph statistics compared'
        'Business datasets were not deleted; every destructive step used isolated probe data.'
    ))
    Write-Host "Four-database restore verification passed. Evidence: $path"
}
finally {
    Pop-Location
}
