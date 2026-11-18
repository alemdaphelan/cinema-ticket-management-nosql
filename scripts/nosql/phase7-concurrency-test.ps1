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

$key = 'nosql:phase7:concurrency:' + [guid]::NewGuid().ToString('N')
$jobs = @('owner-a', 'owner-b') | ForEach-Object {
    Start-Job -ArgumentList $settings.REDIS_PASSWORD, $key, $_ -ScriptBlock {
        param($password, $probeKey, $owner)
        $result = & docker exec cinema_redis redis-cli --no-auth-warning -a $password SET $probeKey $owner NX EX 60
        [pscustomobject]@{ Owner = $owner; Result = ($result | Out-String).Trim() }
    }
}

try {
    $results = $jobs | Wait-Job | Receive-Job
    $winners = @($results | Where-Object Result -eq 'OK')
    if ($winners.Count -ne 1) { throw "Expected exactly one concurrent winner, received $($winners.Count)." }

    $storedOwner = (& docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD GET $key | Out-String).Trim()
    if ($storedOwner -ne $winners[0].Owner) { throw 'Redis winner does not match the stored owner.' }

    $evidence = @(
        'Phase 7 concurrent seat-hold probe: PASS'
        "winner=$storedOwner"
        'requests=2'
        'successful_holds=1'
        'conflicts=1'
        'atomic_primitive=SET NX EX'
    )
    $path = Join-Path $repoRoot 'docs\evidence\phase7-concurrency.txt'
    [System.IO.Directory]::CreateDirectory((Split-Path $path)) | Out-Null
    [System.IO.File]::WriteAllLines($path, $evidence)
    Write-Host "Concurrent hold verification passed. Evidence: $path"
}
finally {
    $jobs | Remove-Job -Force -ErrorAction SilentlyContinue
    & docker exec cinema_redis redis-cli --no-auth-warning -a $settings.REDIS_PASSWORD DEL $key | Out-Null
}
