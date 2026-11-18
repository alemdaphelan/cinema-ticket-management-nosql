[CmdletBinding()]
param([string] $EnvFile = '.env.nosql.local')

$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$envPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $EnvFile))
if (-not (Test-Path -LiteralPath $envPath)) { throw "Environment file not found: $envPath" }

function Wait-ServiceHealthy([string] $container, [int] $timeoutSeconds = 180) {
    $deadline = (Get-Date).AddSeconds($timeoutSeconds)
    do {
        $health = (& docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' $container 2>$null | Out-String).Trim()
        if ($health -in @('healthy', 'running')) { return }
        Start-Sleep -Seconds 3
    } while ((Get-Date) -lt $deadline)
    throw "$container did not become healthy within $timeoutSeconds seconds."
}

$results = [System.Collections.Generic.List[string]]::new()
Push-Location $repoRoot
try {
    foreach ($probe in @(
        @{ Service = 'redis'; Container = 'cinema_redis' },
        @{ Service = 'mongodb'; Container = 'cinema_mongodb' },
        @{ Service = 'cassandra-api'; Container = 'cinema_cassandra_api' },
        @{ Service = 'neo4j'; Container = 'cinema_neo4j' }
    )) {
        & docker compose --env-file $envPath stop $probe.Service | Out-Null
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000/api/test-db' -TimeoutSec 20
            if ($response.StatusCode -ne 200) { throw "Core API returned $($response.StatusCode)." }
            $results.Add("$($probe.Service): core MySQL API remained HTTP 200")
        }
        finally {
            & docker compose --env-file $envPath start $probe.Service | Out-Null
            Wait-ServiceHealthy $probe.Container
        }
    }

    $results.Add('Payment fallback is covered by PhaseSevenAcceptanceTest with Redis unavailable and queued Cassandra/Neo4j projections.')
    $path = Join-Path $repoRoot 'docs\evidence\phase7-resilience.txt'
    [System.IO.Directory]::CreateDirectory((Split-Path $path)) | Out-Null
    [System.IO.File]::WriteAllLines($path, @('Phase 7 NoSQL failure probes: PASS') + $results)
    Write-Host "Resilience verification passed. Evidence: $path"
}
finally {
    Pop-Location
}
