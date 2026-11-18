[CmdletBinding()]
param(
    [string] $EnvFile = '.env.nosql.local',
    [string] $PhpPath = ''
)

$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$envPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $EnvFile))

if (-not (Test-Path -LiteralPath $envPath)) {
    throw "Environment file not found: $envPath. Run scripts/nosql/init-env.ps1 first."
}

Get-Content -LiteralPath $envPath | ForEach-Object {
    if ($_ -match '^([^#=]+)=(.*)$') {
        [System.Environment]::SetEnvironmentVariable($matches[1].Trim(), $matches[2].Trim(), 'Process')
    }
}

$env:REDIS_HOST = '127.0.0.1'
$env:REDIS_PORT = '6379'
$env:REDIS_DB = '0'
$env:REDIS_CACHE_DB = '1'
$env:REDIS_CLIENT = 'predis'
$env:NOSQL_REDIS_CONNECTION = 'default'
$env:NOSQL_REDIS_CACHE_STORE = 'redis'
$env:NOSQL_REDIS_SEAT_HOLD_STORE = 'redis'
$env:NOSQL_REDIS_LEADERBOARD_STORE = 'redis'

if (-not $PhpPath) {
    $laragonPhp = Get-ChildItem -LiteralPath 'C:\laragon\bin\php' -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like 'php-8.3*' } |
        Sort-Object Name -Descending |
        ForEach-Object { Join-Path $_.FullName 'php.exe' } |
        Where-Object { Test-Path -LiteralPath $_ } |
        Select-Object -First 1
    $PhpPath = if ($laragonPhp) { $laragonPhp } else { 'php' }
}

Push-Location $repoRoot
try {
    & $PhpPath artisan nosql:redis-smoke
    if ($LASTEXITCODE -ne 0) {
        throw 'Redis phase 3 smoke tests failed.'
    }
}
finally {
    Pop-Location
}
