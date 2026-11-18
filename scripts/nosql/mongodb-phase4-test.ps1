[CmdletBinding()]
param(
    [string] $EnvFile = '.env.nosql.local'
)

$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$envPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $EnvFile))

if (-not (Test-Path -LiteralPath $envPath)) {
    throw "Environment file not found: $envPath."
}

$settings = @{}
Get-Content -LiteralPath $envPath | ForEach-Object {
    if ($_ -match '^([^#=]+)=(.*)$') {
        $settings[$matches[1].Trim()] = $matches[2].Trim()
    }
}

$php = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
if (-not (Test-Path -LiteralPath $php)) {
    throw "PHP 8.3 executable not found: $php"
}

$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$smokeDatabase = "cinema_content_phase4_smoke_$stamp"
$containerArchive = "/tmp/$smokeDatabase.archive.gz"
$backupRoot = Join-Path $repoRoot 'storage\backups\nosql'
[System.IO.Directory]::CreateDirectory($backupRoot) | Out-Null
$hostArchive = Join-Path $backupRoot "$smokeDatabase.archive.gz"

function Invoke-Compose {
    param([Parameter(Mandatory)][string[]] $Arguments)

    & docker compose --env-file $envPath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose command failed: $($Arguments -join ' ')"
    }
}

function Invoke-MongoScript {
    param([Parameter(Mandatory)][string] $Script)

    Invoke-Compose @(
        'exec', '-T', 'mongodb', 'mongosh', '--quiet',
        '--username', $settings.MONGO_ROOT_USERNAME,
        '--password', $settings.MONGO_ROOT_PASSWORD,
        '--authenticationDatabase', 'admin',
        '--eval', $Script
    )
}

Push-Location $repoRoot
try {
    Invoke-Compose @('up', '-d', 'mongodb')
    $healthy = $false
    for ($attempt = 1; $attempt -le 60; $attempt++) {
        $status = (& docker inspect --format '{{.State.Health.Status}}' cinema_mongodb 2>$null | Out-String).Trim()
        if ($status -eq 'healthy') {
            $healthy = $true
            break
        }
        Start-Sleep -Seconds 2
    }
    if (-not $healthy) {
        throw 'MongoDB did not become healthy in time.'
    }

    $env:NOSQL_MONGODB_DRIVER = 'mongodb'
    $env:NOSQL_MONGODB_CONNECTION = 'mongodb'
    $env:MONGO_DATABASE = $smokeDatabase
    $mongoHostPort = if ($settings.MONGO_HOST_PORT) { $settings.MONGO_HOST_PORT } else { '27018' }
    $env:MONGODB_URI = "mongodb://$($settings.MONGO_ROOT_USERNAME):$($settings.MONGO_ROOT_PASSWORD)@127.0.0.1:$mongoHostPort/$smokeDatabase`?authSource=admin"

    & $php artisan nosql:mongo-smoke
    if ($LASTEXITCODE -ne 0) {
        throw 'Application MongoDB smoke test failed.'
    }

    $probe = @"
const d = db.getSiblingDB('$smokeDatabase');
const now = new Date();
d.movie_reviews.insertOne({
  movie_id: NumberLong('9999001'), user_id: NumberLong('9999002'), order_id: null,
  rating: NumberInt(10), content: 'backup restore probe', tags: ['backup'],
  status: 'published', source: 'phase4-backup-probe', watched_at: null,
  created_at: now, updated_at: now
});
"@
    Invoke-MongoScript $probe

    Invoke-Compose @(
        'exec', '-T', 'mongodb', 'mongodump',
        '--username', $settings.MONGO_ROOT_USERNAME,
        '--password', $settings.MONGO_ROOT_PASSWORD,
        '--authenticationDatabase', 'admin',
        '--db', $smokeDatabase,
        "--archive=$containerArchive", '--gzip'
    )
    Invoke-Compose @('cp', "mongodb:$containerArchive", $hostArchive)

    Invoke-MongoScript "db.getSiblingDB('$smokeDatabase').dropDatabase();"
    Invoke-Compose @(
        'exec', '-T', 'mongodb', 'mongorestore',
        '--username', $settings.MONGO_ROOT_USERNAME,
        '--password', $settings.MONGO_ROOT_PASSWORD,
        '--authenticationDatabase', 'admin',
        "--archive=$containerArchive", '--gzip'
    )

    $verify = @"
const d = db.getSiblingDB('$smokeDatabase');
const restored = d.movie_reviews.countDocuments({source: 'phase4-backup-probe'});
const ttl = d.chat_sessions.getIndexes().some((i) => i.name === 'ttl_chat_sessions' && Number(i.expireAfterSeconds) === 0);
if (restored !== 1 || !ttl) quit(2);
print('MongoDB backup restore PASS');
"@
    Invoke-MongoScript $verify
    Write-Host "Phase 4 MongoDB checks passed. Backup artifact: $hostArchive"
}
finally {
    try {
        Invoke-MongoScript "db.getSiblingDB('$smokeDatabase').dropDatabase();"
        Invoke-Compose @('exec', '-T', 'mongodb', 'rm', '-f', $containerArchive)
    }
    catch {
        Write-Warning "Could not clean the temporary smoke database: $($_.Exception.Message)"
    }
    Pop-Location
}
