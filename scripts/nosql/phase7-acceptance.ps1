[CmdletBinding()]
param([string] $EnvFile = '.env.nosql.local')

$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$envPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $EnvFile))
if (-not (Test-Path -LiteralPath $envPath)) { throw "Environment file not found: $envPath" }

Push-Location $repoRoot
try {
    & docker compose --env-file $envPath up -d mongodb redis cassandra cassandra-api neo4j app worker scheduler
    if ($LASTEXITCODE -ne 0) { throw 'Could not start the Phase 7 stack.' }

    # The persistent vendor volume may predate the current composer.lock.
    & docker compose --env-file $envPath exec -T app composer install --no-interaction --no-progress --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'Could not synchronize the application dependencies.' }

    & docker compose --env-file $envPath exec -T app php artisan config:clear | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not clear the application configuration cache.' }
    & docker compose --env-file $envPath exec -T app php artisan nosql:mongo-setup | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'MongoDB setup failed.' }
    & docker compose --env-file $envPath exec -T app php artisan nosql:neo4j-sync | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Neo4j synchronization failed.' }

    $testOutput = & docker compose --env-file $envPath exec -T app ./vendor/bin/phpunit 2>&1
    $testExit = $LASTEXITCODE
    $testPath = Join-Path $repoRoot 'docs\evidence\phase7-tests.txt'
    [System.IO.Directory]::CreateDirectory((Split-Path $testPath)) | Out-Null
    [System.IO.File]::WriteAllLines($testPath, [string[]] $testOutput)
    if ($testExit -ne 0) { throw "PHPUnit failed. See $testPath" }

    $redisOutput = & docker compose --env-file $envPath exec -T app php artisan nosql:redis-smoke 2>&1
    $redisExit = $LASTEXITCODE
    [System.IO.File]::WriteAllLines((Join-Path $repoRoot 'docs\evidence\redis-live-smoke.txt'), [string[]] $redisOutput)
    if ($redisExit -ne 0) { throw 'Live Redis smoke test failed.' }

    & powershell -ExecutionPolicy Bypass -File scripts/nosql/phase7-concurrency-test.ps1 -EnvFile $EnvFile
    if ($LASTEXITCODE -ne 0) { throw 'Concurrent seat-hold test failed.' }

    & docker compose --env-file $envPath exec -T app php artisan nosql:phase7-benchmark --iterations=30
    if ($LASTEXITCODE -ne 0) { throw 'Phase 7 benchmark failed.' }

    & powershell -ExecutionPolicy Bypass -File scripts/nosql/phase7-resilience-test.ps1 -EnvFile $EnvFile
    if ($LASTEXITCODE -ne 0) { throw 'NoSQL resilience test failed.' }

    & powershell -ExecutionPolicy Bypass -File scripts/nosql/phase7-restore-test.ps1 -EnvFile $EnvFile
    if ($LASTEXITCODE -ne 0) { throw 'Four-database restore test failed.' }

    Write-Host 'Phase 7 acceptance suite passed.'
}
finally {
    Pop-Location
}
