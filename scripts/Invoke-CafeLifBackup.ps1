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
& gh workflow run 'backup-simply.yml' --repo $Repository
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub-workflowet kunne ikke startes.'
}

$run = $null
for ($attempt = 0; $attempt -lt 10 -and $null -eq $run; $attempt++) {
    Start-Sleep -Seconds 2
    $runsJson = (& gh run list --repo $Repository --workflow 'backup-simply.yml' --event workflow_dispatch --limit 20 --json 'databaseId,status,conclusion,url,createdAt')
    if ($LASTEXITCODE -ne 0) {
        throw 'GitHub Actions-kørslerne kunne ikke læses.'
    }

    $run = $runsJson |
        ConvertFrom-Json |
        Where-Object { [DateTimeOffset]$_.createdAt -ge $dispatchStartedAt.AddSeconds(-5) } |
        Sort-Object -Property createdAt -Descending |
        Select-Object -First 1
}

if ($null -eq $run) {
    throw 'Den netop startede GitHub-workflow-kørsel kunne ikke findes inden for 20 sekunder.'
}

Write-Host "Workflow: $($run.url)"

if ($Wait) {
    & gh run watch ([string]$run.databaseId) --repo $Repository --exit-status
    if ($LASTEXITCODE -ne 0) {
        throw 'Backup-workflowet sluttede med fejl. Åbn workflow-linket og se den fejlede kontrol.'
    }

    Write-Host 'Backup og krypteret GitHub Release er gennemført.' -ForegroundColor Green
}
else {
    Write-Host 'Workflowet kører i GitHub. Brug -Wait næste gang for at vente på resultatet.' -ForegroundColor Yellow
}
