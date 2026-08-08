[CmdletBinding()]
param(
    [string]$Repository,
    [switch]$Wait
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

Assert-Command -Name 'gh'
Assert-Command -Name 'git'

if ([string]::IsNullOrWhiteSpace($Repository)) {
    $Repository = Get-RepositoryFromRemote
}

& gh auth status
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub CLI er ikke logget ind. Kør: gh auth login'
}

Write-Host "Starter en frisk, read-only Simply-backup i $Repository ..." -ForegroundColor Cyan
$dispatchStartedAt = [DateTimeOffset]::UtcNow
$existingRunIds = @{}
$existingRunsJson = (& gh run list --repo $Repository --workflow 'backup-simply.yml' --event workflow_dispatch --limit 20 --json 'databaseId')
if ($LASTEXITCODE -ne 0) {
    throw 'Eksisterende GitHub Actions-kørsler kunne ikke læses før start.'
}

$existingRuns = $existingRunsJson | ConvertFrom-Json
foreach ($existingRun in @($existingRuns)) {
    $existingRunIds[[string]$existingRun.databaseId] = $true
}

& gh workflow run 'backup-simply.yml' --repo $Repository
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub-workflowet kunne ikke startes.'
}

$run = $null
$discoveryDeadline = [DateTimeOffset]::UtcNow.AddMinutes(2)
do {
    Start-Sleep -Seconds 2
    $runsJson = (& gh run list --repo $Repository --workflow 'backup-simply.yml' --event workflow_dispatch --limit 20 --json 'databaseId,status,conclusion,url,createdAt')
    if ($LASTEXITCODE -ne 0) {
        Write-Warning 'GitHub Actions-kørslerne kunne ikke læses endnu; prøver igen.'
        continue
    }

    # Windows PowerShell 5.1 sender en JSON-array som ét Object[] fra
    # ConvertFrom-Json. Gem resultatet først, så pipelinen enumererer runs.
    $runs = $runsJson | ConvertFrom-Json
    $run = @($runs) |
        Where-Object {
            -not $existingRunIds.ContainsKey([string]$_.databaseId) -and
            [DateTimeOffset]($_.createdAt) -ge $dispatchStartedAt.AddSeconds(-5)
        } |
        Sort-Object -Property createdAt -Descending |
        Select-Object -First 1
} while ($null -eq $run -and [DateTimeOffset]::UtcNow -lt $discoveryDeadline)

if ($null -eq $run) {
    throw 'Den netop startede GitHub-workflow-kørsel kunne ikke findes inden for 2 minutter. Kontroller repositoryets Actions-side.'
}

Write-Host "Workflow: $($run.url)"

if ($Wait) {
    & gh run watch ([string]$run.databaseId) --repo $Repository --exit-status
    if ($LASTEXITCODE -ne 0) {
        Write-Host ''
        Write-Host 'Fejlede loglinjer fra GitHub Actions:' -ForegroundColor Red
        & gh run view ([string]$run.databaseId) --repo $Repository --log-failed
        throw "Backup-workflowet sluttede med fejl: $($run.url)"
    }

    Write-Host 'Backup og krypteret GitHub Release er gennemført.' -ForegroundColor Green
}
else {
    Write-Host 'Workflowet kører i GitHub. Brug -Wait næste gang for at vente på resultatet.' -ForegroundColor Yellow
}
