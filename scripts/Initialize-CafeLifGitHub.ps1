[CmdletBinding()]
param(
    [string]$RepositoryName = 'cafelif-private'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))

function Assert-Command {
    param([Parameter(Mandatory)][string]$Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Det krævede program '$Name' blev ikke fundet i PATH."
    }
}

function Read-RequiredValue {
    param(
        [Parameter(Mandatory)][string]$Prompt,
        [string]$Default
    )

    while ($true) {
        $suffix = if ([string]::IsNullOrWhiteSpace($Default)) { '' } else { " [$Default]" }
        $value = Read-Host "$Prompt$suffix"
        if ([string]::IsNullOrWhiteSpace($value)) {
            $value = $Default
        }

        if (-not [string]::IsNullOrWhiteSpace($value)) {
            return $value.Trim()
        }

        Write-Warning 'Værdien må ikke være tom.'
    }
}

function Read-RequiredSecureValue {
    param([Parameter(Mandatory)][string]$Prompt)

    while ($true) {
        $secure = Read-Host $Prompt -AsSecureString
        if ($secure.Length -gt 0) {
            return $secure
        }

        Write-Warning 'Værdien må ikke være tom.'
    }
}

function ConvertTo-PlainText {
    param([Parameter(Mandatory)][System.Security.SecureString]$SecureValue)

    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Confirm-Explicit {
    param([Parameter(Mandatory)][string]$Prompt)

    $answer = Read-Host "$Prompt Skriv JA for at fortsætte"
    return $answer -ceq 'JA'
}

function Invoke-Checked {
    param(
        [Parameter(Mandatory)][string]$Command,
        [Parameter(Mandatory)][string[]]$Arguments,
        [Parameter(Mandatory)][string]$FailureMessage
    )

    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & $Command @Arguments
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($exitCode -ne 0) {
        throw $FailureMessage
    }
}

function Protect-PrivateFile {
    param([Parameter(Mandatory)][string]$Path)

    if ($env:OS -ne 'Windows_NT' -or -not (Get-Command 'icacls.exe' -ErrorAction SilentlyContinue)) {
        return
    }

    $grant = "${env:USERNAME}:F"
    & icacls.exe $Path /inheritance:r /grant:r $grant | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Windows-rettighederne kunne ikke begrænses for den private fil: $Path"
    }
}

function Assert-RepositorySafeToCommit {
    param([Parameter(Mandatory)][string]$Root)

    $candidates = @(& git ls-files --cached --others --exclude-standard)
    if ($LASTEXITCODE -ne 0) {
        throw 'Git kunne ikke opbygge listen til secret-kontrol.'
    }

    $sensitiveNamePattern = '(?i)(^|/)(config(?:\.[^/]+)?\.local\.php|config\.mail\.local\.php|\.env(?:\..*)?|age-key\.txt|.*_ed25519(?:\.pub)?|.*\.(?:sql|pem|key|ppk|age))$'
    $ageSecretMarker = 'AGE-' + 'SECRET-KEY-'
    $secretContentPattern = '(?i)(BEGIN (?:RSA |OPENSSH |EC |AGE )?PRIVATE KEY|' +
        [regex]::Escape($ageSecretMarker) +
        '|gh[pousr]_[A-Za-z0-9_]{20,}|(?:password|passwd|secret|token|api[_-]?key)\s*[:=]\s*["''][^"'']{8,}["''])'

    foreach ($relativePath in $candidates) {
        $normalized = $relativePath.Replace('\', '/')
        if ($normalized -match $sensitiveNamePattern) {
            throw "En følsom fil må ikke committes: $relativePath"
        }

        $fullPath = Join-Path $Root $relativePath
        if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
            continue
        }

        $file = Get-Item -LiteralPath $fullPath
        if ($file.Length -gt 5MB) {
            continue
        }

        $extension = $file.Extension.ToLowerInvariant()
        if ($extension -notin @('.ps1', '.yml', '.yaml', '.json', '.md', '.txt', '.gitignore')) {
            continue
        }

        $content = Get-Content -LiteralPath $fullPath -Raw -ErrorAction Stop
        if ($content -match $secretContentPattern) {
            throw "Secret-kontrollen fandt muligt følsomt indhold i: $relativePath"
        }
    }
}

function Set-GitHubVariable {
    param(
        [Parameter(Mandatory)][string]$Repository,
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][string]$Value
    )

    Invoke-Checked -Command 'gh' -Arguments @(
        'variable', 'set', $Name, '--repo', $Repository, '--body', $Value
    ) -FailureMessage "GitHub Variable '$Name' kunne ikke gemmes."
}

function Set-GitHubSecretText {
    param(
        [Parameter(Mandatory)][string]$Repository,
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][string]$Value
    )

    $Value | & gh secret set $Name --repo $Repository
    if ($LASTEXITCODE -ne 0) {
        throw "GitHub Secret '$Name' kunne ikke gemmes."
    }
}

Assert-Command -Name 'git'
Assert-Command -Name 'gh'
Assert-Command -Name 'age-keygen'
Assert-Command -Name 'ssh-keygen'
Assert-Command -Name 'ssh-keyscan'

& gh auth status
if ($LASTEXITCODE -ne 0) {
    Write-Host 'GitHub CLI skal logges ind.' -ForegroundColor Yellow
    Invoke-Checked -Command 'gh' -Arguments @('auth', 'login') -FailureMessage 'GitHub-login fejlede.'
}

$owner = (& gh api user --jq '.login').Trim()
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($owner)) {
    throw 'Det aktuelle GitHub-brugernavn kunne ikke hentes.'
}

$repository = "$owner/$RepositoryName"
Write-Host "Målrepository: $repository" -ForegroundColor Cyan

Push-Location $ProjectRoot
try {
    if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot '.git'))) {
        Invoke-Checked -Command 'git' -Arguments @('init', '-b', 'main') -FailureMessage 'Git-repository kunne ikke initialiseres.'
    }

    $gitName = (& git config user.name 2>$null)
    if ([string]::IsNullOrWhiteSpace($gitName)) {
        $gitName = Read-RequiredValue -Prompt 'Git commit-navn' -Default 'Lennart Jensen'
        Invoke-Checked -Command 'git' -Arguments @('config', 'user.name', $gitName) -FailureMessage 'Git-navn kunne ikke gemmes.'
    }

    $gitEmail = (& git config user.email 2>$null)
    if ([string]::IsNullOrWhiteSpace($gitEmail)) {
        $gitEmail = Read-RequiredValue -Prompt 'Git commit-email' -Default 'admin@lense.dk'
        Invoke-Checked -Command 'git' -Arguments @('config', 'user.email', $gitEmail) -FailureMessage 'Git-email kunne ikke gemmes.'
    }

    $commitCountText = ((& git rev-list --count --all 2>$null) -join '').Trim()
    if ($LASTEXITCODE -ne 0) {
        throw 'Git kunne ikke kontrollere, om repositoryet allerede har commits.'
    }

    [long]$commitCount = 0
    if (-not [long]::TryParse($commitCountText, [ref]$commitCount)) {
        throw "Git returnerede et ugyldigt antal commits: '$commitCountText'."
    }

    if ($commitCount -eq 0) {
        Assert-RepositorySafeToCommit -Root $ProjectRoot
        Invoke-Checked -Command 'git' -Arguments @('add', '--all') -FailureMessage 'Projektfilerne kunne ikke stages.'
        Invoke-Checked -Command 'git' -Arguments @(
            'commit', '-m', 'chore: initialize Cafe LIF private repository'
        ) -FailureMessage 'Det første commit kunne ikke oprettes.'
    }

    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & gh repo view $repository --json 'nameWithOwner,visibility' 2>$null | Out-Null
        $repositoryExists = $LASTEXITCODE -eq 0
    }
    finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if (-not $repositoryExists) {
        if (-not (Confirm-Explicit -Prompt "Opret og push det PRIVATE repository ${repository}?")) {
            throw 'Repository-oprettelsen blev afbrudt.'
        }

        Invoke-Checked -Command 'gh' -Arguments @(
            'repo', 'create', $repository, '--private', '--source', $ProjectRoot,
            '--remote', 'origin', '--push'
        ) -FailureMessage 'Det private GitHub-repository kunne ikke oprettes.'

        Invoke-Checked -Command 'gh' -Arguments @(
            'repo', 'edit', $repository, '--default-branch', 'main'
        ) -FailureMessage 'Main kunne ikke sættes som standardbranch.'
    }
    else {
        $visibility = (& gh repo view $repository --json visibility --jq '.visibility').Trim()
        if ($visibility -ne 'PRIVATE') {
            throw "Repository $repository er ikke privat. Opsætningen stopper."
        }

        $remoteNames = @(& git remote)
        if ($LASTEXITCODE -ne 0) {
            throw 'Git remotes kunne ikke kontrolleres.'
        }

        if ($remoteNames -notcontains 'origin') {
            Invoke-Checked -Command 'git' -Arguments @(
                'remote', 'add', 'origin', "https://github.com/$repository.git"
            ) -FailureMessage 'Git remote origin kunne ikke tilføjes.'
        }

        if (-not (Confirm-Explicit -Prompt "Push den lokale main-branch til ${repository}?")) {
            throw 'Main skal pushes, før backupopsætningen kan fortsætte.'
        }

        Assert-RepositorySafeToCommit -Root $ProjectRoot
        Invoke-Checked -Command 'git' -Arguments @(
            'push', '--set-upstream', 'origin', 'main'
        ) -FailureMessage 'Main-branch kunne ikke pushes.'

        Invoke-Checked -Command 'gh' -Arguments @(
            'repo', 'edit', $repository, '--default-branch', 'main'
        ) -FailureMessage 'Main kunne ikke sættes som standardbranch.'
    }
}
finally {
    Pop-Location
}

$ageDirectory = Join-Path $env:USERPROFILE '.config\cafelif'
$ageIdentityPath = Join-Path $ageDirectory 'age-key.txt'
if (-not (Test-Path -LiteralPath $ageIdentityPath -PathType Leaf)) {
    New-Item -ItemType Directory -Path $ageDirectory -Force | Out-Null
    Invoke-Checked -Command 'age-keygen' -Arguments @(
        '-o', $ageIdentityPath
    ) -FailureMessage 'age-nøgleparret kunne ikke oprettes.'
}
Protect-PrivateFile -Path $ageIdentityPath

$ageRecipient = (& age-keygen -y $ageIdentityPath).Trim()
if ($LASTEXITCODE -ne 0 -or -not $ageRecipient.StartsWith('age1')) {
    throw 'Den offentlige age-modtagernøgle kunne ikke udledes.'
}

Write-Host ''
Write-Warning "Gem den private age-nøgle i en password manager: $ageIdentityPath"
Write-Warning 'Uden denne nøgle kan de krypterede GitHub-backups ikke gendannes.'

$sshDirectory = Join-Path $env:USERPROFILE '.ssh'
$sshPrivateKeyPath = Join-Path $sshDirectory 'cafelif_github_backup_ed25519'
$sshPublicKeyPath = "$sshPrivateKeyPath.pub"

if (-not (Test-Path -LiteralPath $sshPrivateKeyPath -PathType Leaf)) {
    New-Item -ItemType Directory -Path $sshDirectory -Force | Out-Null
    Write-Host 'SSH-keygen spørger nu efter passphrase. Tryk Enter to gange, fordi GitHub Actions-nøglen skal være dedikeret og ikke-interaktiv.' -ForegroundColor Yellow
    Invoke-Checked -Command 'ssh-keygen' -Arguments @(
        '-t', 'ed25519', '-a', '100', '-f', $sshPrivateKeyPath,
        '-C', 'cafelif-github-backup'
    ) -FailureMessage 'Den dedikerede SSH-nøgle kunne ikke oprettes.'
}
Protect-PrivateFile -Path $sshPrivateKeyPath

Write-Host ''
Write-Host 'Tilføj denne OFFENTLIGE nøgle hos Simply:' -ForegroundColor Cyan
Get-Content -LiteralPath $sshPublicKeyPath
Write-Host ''

$sshHost = Read-RequiredValue -Prompt 'Simply SSH-host'
$sshPort = Read-RequiredValue -Prompt 'Simply SSH-port' -Default '22'
$sshUser = Read-RequiredValue -Prompt 'Simply SSH-bruger'
$prodRemotePath = Read-RequiredValue -Prompt 'Absolut remote sti til produktionens public_html'
$defaultTestRemotePath = $prodRemotePath.TrimEnd('/') + '/test'
$testRemotePath = Read-RequiredValue -Prompt 'Absolut remote sti til testmiljøet' -Default $defaultTestRemotePath

foreach ($path in @($prodRemotePath, $testRemotePath)) {
    if ($path -notmatch '^/[A-Za-z0-9._/-]+$') {
        throw "Remote-stien indeholder ikke-tilladte tegn: $path"
    }
}
if ($prodRemotePath.TrimEnd('/') -ceq $testRemotePath.TrimEnd('/')) {
    throw 'Produktion og test må ikke bruge samme remote sti.'
}

$knownHosts = (& ssh-keyscan -p $sshPort $sshHost 2>$null) -join [Environment]::NewLine
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($knownHosts)) {
    throw 'SSH-hostnøglen kunne ikke hentes. Kontrollér host, port og netværk.'
}

$knownHostsTemp = Join-Path ([System.IO.Path]::GetTempPath()) "cafelif-known-hosts-$([guid]::NewGuid().ToString('N')).txt"
try {
    [System.IO.File]::WriteAllText($knownHostsTemp, $knownHosts, [Text.UTF8Encoding]::new($false))
    Write-Host ''
    Write-Host 'SSH-fingeraftryk, som skal verificeres mod Simply:' -ForegroundColor Cyan
    & ssh-keygen -lf $knownHostsTemp
    if ($LASTEXITCODE -ne 0) {
        throw 'SSH-fingeraftrykket kunne ikke vises.'
    }

    if (-not (Confirm-Explicit -Prompt 'Er fingeraftrykket verificeret som Simply-serverens korrekte nøgle?')) {
        throw 'Opsætningen stoppede før lagring af SSH-adgang.'
    }
}
finally {
    if (Test-Path -LiteralPath $knownHostsTemp) {
        Remove-Item -LiteralPath $knownHostsTemp -Force
    }
}

$prodDbHost = Read-RequiredValue -Prompt 'Produktionsdatabase-host'
$prodDbPort = Read-RequiredValue -Prompt 'Produktionsdatabase-port' -Default '3306'
$prodDbName = Read-RequiredValue -Prompt 'Produktionsdatabasens navn' -Default 'cafelif_dk_db'
$prodDbUser = Read-RequiredValue -Prompt 'Produktionsdatabase-bruger'
$prodDbPasswordSecure = Read-RequiredSecureValue -Prompt 'Produktionsdatabase-adgangskode'

$testDbHost = Read-RequiredValue -Prompt 'Testdatabase-host' -Default $prodDbHost
$testDbPort = Read-RequiredValue -Prompt 'Testdatabase-port' -Default $prodDbPort
$testDbName = Read-RequiredValue -Prompt 'Testdatabasens navn' -Default 'cafelif_dk_db_cafelif_test'
$testDbUser = Read-RequiredValue -Prompt 'Testdatabase-bruger'
$testDbPasswordSecure = Read-RequiredSecureValue -Prompt 'Testdatabase-adgangskode'

if ($prodDbName -cne 'cafelif_dk_db' -or $testDbName -cne 'cafelif_dk_db_cafelif_test') {
    throw 'Databasenavnene skal være cafelif_dk_db og cafelif_dk_db_cafelif_test.'
}

$variables = [ordered]@{
    BACKUP_AGE_RECIPIENT = $ageRecipient
    BACKUP_RETENTION_COUNT = '30'
    SIMPLY_SSH_HOST = $sshHost
    SIMPLY_SSH_PORT = $sshPort
    SIMPLY_SSH_USER = $sshUser
    PROD_REMOTE_PATH = $prodRemotePath
    TEST_REMOTE_PATH = $testRemotePath
    PROD_DB_HOST = $prodDbHost
    PROD_DB_PORT = $prodDbPort
    PROD_DB_NAME = $prodDbName
    PROD_DB_USER = $prodDbUser
    TEST_DB_HOST = $testDbHost
    TEST_DB_PORT = $testDbPort
    TEST_DB_NAME = $testDbName
    TEST_DB_USER = $testDbUser
}

foreach ($entry in $variables.GetEnumerator()) {
    Set-GitHubVariable -Repository $repository -Name $entry.Key -Value $entry.Value
}

$sshPrivateKey = Get-Content -LiteralPath $sshPrivateKeyPath -Raw
$prodDbPassword = ConvertTo-PlainText -SecureValue $prodDbPasswordSecure
$testDbPassword = ConvertTo-PlainText -SecureValue $testDbPasswordSecure

try {
    Set-GitHubSecretText -Repository $repository -Name 'SIMPLY_SSH_PRIVATE_KEY' -Value $sshPrivateKey
    Set-GitHubSecretText -Repository $repository -Name 'SIMPLY_SSH_KNOWN_HOSTS' -Value $knownHosts
    Set-GitHubSecretText -Repository $repository -Name 'PROD_DB_PASSWORD' -Value $prodDbPassword
    Set-GitHubSecretText -Repository $repository -Name 'TEST_DB_PASSWORD' -Value $testDbPassword
}
finally {
    $sshPrivateKey = $null
    $prodDbPassword = $null
    $testDbPassword = $null
    $prodDbPasswordSecure.Dispose()
    $testDbPasswordSecure.Dispose()
}

Write-Host ''
Write-Host "Det private repository er konfigureret: https://github.com/$repository" -ForegroundColor Green
Write-Host "Private age-nøgle: $ageIdentityPath" -ForegroundColor Yellow
Write-Host "Offentlig Simply SSH-nøgle: $sshPublicKeyPath" -ForegroundColor Yellow

if (Confirm-Explicit -Prompt 'Er SSH-nøglen registreret hos Simply, og skal den første backup startes nu?') {
    Invoke-Checked -Command 'gh' -Arguments @(
        'workflow', 'run', 'backup-simply.yml', '--repo', $repository
    ) -FailureMessage 'Den første GitHub-backup kunne ikke startes.'

    Write-Host 'Backupworkflowet er startet. Se status under GitHub Actions.' -ForegroundColor Green
}
