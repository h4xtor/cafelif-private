[CmdletBinding()]
param(
    [ValidateSet('Production', 'Test', 'All')]
    [string]$Environment = 'All',

    [string]$Repository,

    [string]$Tag,

    [string]$AgeIdentityPath = (Join-Path $env:USERPROFILE '.config\cafelif\age-key.txt'),

    [string]$Destination = (Join-Path (Get-Location) 'retrieved-backups')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Assert-Command {
    param([Parameter(Mandatory)][string]$Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Det krævede program '$Name' blev ikke fundet i PATH."
    }
}

function Get-RepositoryFromRemote {
    $remote = (& git remote get-url origin 2>$null)
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($remote)) {
        throw 'Repository kunne ikke udledes. Angiv -Repository owner/repo.'
    }

    $value = $remote.Trim()
    if ($value -match 'github\.com[:/](?<repo>[^/]+/[^/]+?)(?:\.git)?$') {
        return $Matches.repo
    }

    throw "GitHub-repository kunne ikke udledes fra origin: $value"
}

function Assert-EmptyOrMissingDirectory {
    param([Parameter(Mandatory)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    $firstItem = Get-ChildItem -LiteralPath $Path -Force | Select-Object -First 1
    if ($null -ne $firstItem) {
        throw "Målmappen er ikke tom: $Path"
    }
}

function Assert-SafeTarArchive {
    param([Parameter(Mandatory)][string]$Path)

    $entries = @(& tar -tzf $Path)
    if ($LASTEXITCODE -ne 0 -or $entries.Count -eq 0) {
        throw "Backup-arkivet kunne ikke listes eller er tomt: $Path"
    }

    foreach ($entry in $entries) {
        $normalized = ([string]$entry).Replace('\', '/')
        if ([string]::IsNullOrWhiteSpace($normalized) -or
            $normalized.StartsWith('/') -or
            $normalized -match '^[A-Za-z]:' -or
            $normalized -match '(^|/)\.\.(/|$)') {
            throw "Backup-arkivet indeholder en usikker sti: $entry"
        }
    }
}

function Assert-RestoredSnapshot {
    param(
        [Parameter(Mandatory)][string]$Path,
        [Parameter(Mandatory)][string]$EnvironmentName
    )

    foreach ($relativePath in @('site\index.php', 'site\admin', 'site\uploads', 'database.sql', 'snapshot-manifest.json')) {
        if (-not (Test-Path -LiteralPath (Join-Path $Path $relativePath))) {
            throw "Den udpakkede $EnvironmentName-backup mangler: $relativePath"
        }
    }

    $databasePath = Join-Path $Path 'database.sql'
    $databaseFile = Get-Item -LiteralPath $databasePath
    if ($databaseFile.Length -le 0) {
        throw "Databasedumpet i $EnvironmentName-backuppen er tomt."
    }

    try {
        $manifest = Get-Content -LiteralPath (Join-Path $Path 'snapshot-manifest.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    }
    catch {
        throw "Snapshot-manifestet i $EnvironmentName-backuppen er ugyldigt."
    }

    $actualDatabaseHash = (Get-FileHash -LiteralPath $databasePath -Algorithm SHA256).Hash.ToLowerInvariant()
    $expectedDatabaseHash = ([string]$manifest.database_sha256).ToLowerInvariant()
    $siteFileCount = @(Get-ChildItem -LiteralPath (Join-Path $Path 'site') -File -Force -Recurse).Count

    if ([string]$manifest.environment -cne $EnvironmentName -or
        [long]$manifest.database_bytes -ne $databaseFile.Length -or
        [long]$manifest.file_count -ne $siteFileCount -or
        $expectedDatabaseHash -notmatch '^[0-9a-f]{64}$' -or
        $actualDatabaseHash -cne $expectedDatabaseHash) {
        throw "Snapshot-manifestet matcher ikke den udpakkede $EnvironmentName-backup."
    }
}

Assert-Command -Name 'gh'
Assert-Command -Name 'git'
Assert-Command -Name 'age'
Assert-Command -Name 'tar'

if ([string]::IsNullOrWhiteSpace($Repository)) {
    $Repository = Get-RepositoryFromRemote
}

if (-not (Test-Path -LiteralPath $AgeIdentityPath -PathType Leaf)) {
    throw "Den private age-nøgle blev ikke fundet: $AgeIdentityPath"
}

& gh auth status
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub CLI er ikke logget ind. Kør: gh auth login'
}

if ([string]::IsNullOrWhiteSpace($Tag)) {
    $releaseJson = (& gh release list --repo $Repository --limit 100 --json 'tagName,createdAt')
    if ($LASTEXITCODE -ne 0) {
        throw 'GitHub Releases kunne ikke læses.'
    }

    $release = $releaseJson |
        ConvertFrom-Json |
        Where-Object { $_.tagName -like 'backup-*' } |
        Sort-Object -Property createdAt -Descending |
        Select-Object -First 1

    if ($null -eq $release) {
        throw 'Der findes ingen Café LIF-backuprelease endnu.'
    }

    $Tag = $release.tagName
}

$targetRoot = Join-Path ([System.IO.Path]::GetFullPath($Destination)) $Tag
Assert-EmptyOrMissingDirectory -Path $targetRoot
New-Item -ItemType Directory -Path $targetRoot -Force | Out-Null

$patterns = switch ($Environment) {
    'Production' { @('cafelif-production-*.age', 'SHA256SUMS') }
    'Test' { @('cafelif-test-*.age', 'SHA256SUMS') }
    'All' { @('cafelif-production-*.age', 'cafelif-test-*.age', 'SHA256SUMS') }
}

foreach ($pattern in $patterns) {
    & gh release download $Tag --repo $Repository --pattern $pattern --dir $targetRoot
    if ($LASTEXITCODE -ne 0) {
        throw "Backupfilen med mønster '$pattern' kunne ikke hentes."
    }
}

$checksumPath = Join-Path $targetRoot 'SHA256SUMS'
if (-not (Test-Path -LiteralPath $checksumPath -PathType Leaf)) {
    throw 'SHA256SUMS mangler i backupreleasen.'
}

$checksumLines = Get-Content -LiteralPath $checksumPath
$encryptedFiles = Get-ChildItem -LiteralPath $targetRoot -Filter '*.age' -File
$expectedEncryptedFileCount = if ($Environment -eq 'All') { 2 } else { 1 }
if ($encryptedFiles.Count -ne $expectedEncryptedFileCount) {
    throw "Backupreleasen indeholder $($encryptedFiles.Count) krypterede miljøfiler; forventede $expectedEncryptedFileCount."
}

foreach ($file in $encryptedFiles) {
    $matchingLine = $checksumLines |
        Where-Object { $_ -match "^[0-9a-fA-F]{64}\s+\*?$([regex]::Escape($file.Name))$" } |
        Select-Object -First 1

    if ($null -eq $matchingLine) {
        throw "Checksum mangler for $($file.Name)."
    }

    $expected = ($matchingLine -split '\s+')[0].ToUpperInvariant()
    $actual = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToUpperInvariant()
    if ($actual -ne $expected) {
        throw "SHA-256-kontrol fejlede for $($file.Name)."
    }
}

foreach ($file in $encryptedFiles) {
    $environmentName = if ($file.Name -like 'cafelif-production-*') { 'production' } else { 'test' }
    $environmentTarget = Join-Path $targetRoot $environmentName
    Assert-EmptyOrMissingDirectory -Path $environmentTarget
    New-Item -ItemType Directory -Path $environmentTarget -Force | Out-Null

    $temporaryTar = Join-Path $targetRoot "$environmentName.tar.gz"
    try {
        & age --decrypt --identity $AgeIdentityPath --output $temporaryTar $file.FullName
        if ($LASTEXITCODE -ne 0) {
            throw "Dekryptering fejlede for $($file.Name)."
        }

        Assert-SafeTarArchive -Path $temporaryTar
        & tar -xzf $temporaryTar -C $environmentTarget
        if ($LASTEXITCODE -ne 0) {
            throw "Udpakning fejlede for $($file.Name)."
        }

        Assert-RestoredSnapshot -Path $environmentTarget -EnvironmentName $environmentName
    }
    finally {
        if (Test-Path -LiteralPath $temporaryTar -PathType Leaf) {
            Remove-Item -LiteralPath $temporaryTar -Force
        }
    }
}

Write-Host "Backup $Tag er verificeret og udpakket i:" -ForegroundColor Green
Write-Host $targetRoot
Write-Warning 'Den udpakkede mappe indeholder livekonfiguration og persondata. Commit eller del den aldrig.'
