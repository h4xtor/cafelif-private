[CmdletBinding()]
param(
    [string]$RepositoryName = 'cafelif-private'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Refresh-ProcessPath {
    $machinePath = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
    $env:Path = @($machinePath, $userPath) -join [IO.Path]::PathSeparator
}

function Install-WingetPackage {
    param(
        [Parameter(Mandatory)][string]$Command,
        [Parameter(Mandatory)][string]$PackageId,
        [Parameter(Mandatory)][string]$DisplayName
    )

    if (Get-Command $Command -ErrorAction SilentlyContinue) {
        Write-Host "$DisplayName er allerede installeret." -ForegroundColor DarkGreen
        return
    }

    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        throw "'$DisplayName' mangler, og winget blev ikke fundet. Installer App Installer fra Microsoft Store og kør filen igen."
    }

    Write-Host "Installerer $DisplayName ..." -ForegroundColor Cyan
    & winget install --id $PackageId --exact --accept-package-agreements --accept-source-agreements --silent
    if ($LASTEXITCODE -ne 0) {
        throw "Installationen af $DisplayName fejlede med exitkode $LASTEXITCODE."
    }

    Refresh-ProcessPath
    if (-not (Get-Command $Command -ErrorAction SilentlyContinue)) {
        throw "$DisplayName blev installeret, men kommandoen '$Command' er endnu ikke i PATH. Luk PowerShell, åbn mappen igen og kør Start-CafeLifSetup.ps1 på ny."
    }
}

try {
    Write-Host ''
    Write-Host 'Café LIF – sikker GitHub- og natbackupopsætning' -ForegroundColor Cyan
    Write-Host 'Denne fil ændrer ikke hjemmesiden og gendanner ikke databaser.' -ForegroundColor DarkGray
    Write-Host ''

    Set-Location -LiteralPath $PSScriptRoot
    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force

    Install-WingetPackage -Command 'git' -PackageId 'Git.Git' -DisplayName 'Git'
    Install-WingetPackage -Command 'gh' -PackageId 'GitHub.cli' -DisplayName 'GitHub CLI'
    Install-WingetPackage -Command 'age-keygen' -PackageId 'FiloSottile.age' -DisplayName 'age'

    foreach ($command in @('ssh-keygen', 'ssh-keyscan')) {
        if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
            throw "Windows OpenSSH Client mangler kommandoen '$command'. Installer 'OpenSSH Client' under Indstillinger > System > Valgfrie funktioner og kør filen igen."
        }
    }

    $initializer = Join-Path $PSScriptRoot 'scripts\Initialize-CafeLifGitHub.ps1'
    if (-not (Test-Path -LiteralPath $initializer -PathType Leaf)) {
        throw "Opsætningsfilen blev ikke fundet: $initializer. ZIP-filen skal udpakkes helt, før denne fil køres."
    }

    & $initializer -RepositoryName $RepositoryName
    if (-not $?) {
        throw 'Café LIF-opsætningen sluttede med en fejl.'
    }

    Write-Host ''
    Write-Host 'Opsætningen er færdig. Følg nu kontroltrinnene i GUIDE-FULD.md.' -ForegroundColor Green
}
catch {
    Write-Host ''
    Write-Host "FEJL: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host 'Intet restore- eller sletteflow er kørt.' -ForegroundColor Yellow
    Read-Host 'Tryk Enter for at lukke'
    exit 1
}

