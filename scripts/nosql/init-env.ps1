[CmdletBinding()]
param(
    [switch] $Force
)

$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$envPath = Join-Path $repoRoot '.env.nosql.local'

if ((Test-Path -LiteralPath $envPath) -and -not $Force) {
    $existingLines = Get-Content -LiteralPath $envPath
    $appKeyLine = $existingLines | Where-Object { $_ -match '^APP_KEY=' } | Select-Object -First 1

    if ([string]::IsNullOrWhiteSpace(($appKeyLine -replace '^APP_KEY=', ''))) {
        $keyBytes = New-Object byte[] 32
        $keyGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        try {
            $keyGenerator.GetBytes($keyBytes)
        }
        finally {
            $keyGenerator.Dispose()
        }

        Add-Content -LiteralPath $envPath -Value "APP_KEY=base64:$([Convert]::ToBase64String($keyBytes))" -Encoding utf8
        Write-Host "Added Laravel APP_KEY to: $envPath"
        exit 0
    }

    Write-Host "NoSQL environment already exists: $envPath"
    exit 0
}

function New-RandomPassword {
    $bytes = New-Object byte[] 24
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }
    return ([BitConverter]::ToString($bytes) -replace '-', '').ToLowerInvariant()
}

function New-LaravelAppKey {
    $bytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }
    return "base64:$([Convert]::ToBase64String($bytes))"
}

$lines = @(
    "APP_KEY=$(New-LaravelAppKey)"
    'MONGO_ROOT_USERNAME=cinema_admin'
    "MONGO_ROOT_PASSWORD=$(New-RandomPassword)"
    'MONGO_DATABASE=cinema_content'
    'MONGO_HOST_PORT=27018'
    'MONGO_APP_USERNAME=cinema_app'
    "MONGO_APP_PASSWORD=$(New-RandomPassword)"
    "REDIS_PASSWORD=$(New-RandomPassword)"
    "CASSANDRA_ADMIN_PASSWORD=$(New-RandomPassword)"
    'CASSANDRA_APP_USERNAME=cinema_app'
    "CASSANDRA_APP_PASSWORD=$(New-RandomPassword)"
    'CASSANDRA_KEYSPACE=cinema_analytics'
    "NEO4J_PASSWORD=$(New-RandomPassword)"
)

[System.IO.File]::WriteAllText(
    $envPath,
    ($lines -join [Environment]::NewLine) + [Environment]::NewLine,
    [System.Text.UTF8Encoding]::new($false)
)

Write-Host "Created local NoSQL credentials: $envPath"
Write-Host 'The file is ignored by Git. Keep it private and do not commit it.'
