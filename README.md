# Café LIF – Test-kode og krypteret natbackup

Dette private repository indeholder backupautomatiseringen og en deploybar kopi af Test-sitet i `test/`. Livefiler, SQL-dumps, kundedata, `config.local.php`, mailkonfiguration og private nøgler må aldrig ligge læsbart i Git-historikken.

## Test-site

Mappen `test/` svarer til `/public_html/test` på Simply og indeholder den aktuelle bestillingskurv, kundens valg om e-mailbekræftelse og mailens ordreindhold. Den indeholder ingen lokale konfigurationsfiler eller kundedata.

## Backupmodel

- GitHub Actions er planlagt til hver nat kl. `03:00 Europe/Copenhagen` og kan også startes manuelt.
- Produktion og test hentes og dumpes separat.
- MySQL-dumps bruger `--single-transaction`, `--quick`, `--skip-lock-tables` og `--no-tablespaces`.
- Hvert miljø pakkes separat og krypteres med `age`, før det uploades som en privat GitHub Release.
- `SHA256SUMS` følger hver release.
- De publicerede releasefiler downloades igen og SHA-256-verificeres, før backupen markeres som gennemført.
- De nyeste 30 komplette backupreleases bevares.
- Efter hver kørsel sender en separat status-job en mail med succes eller fejl og konkrete trinstatusser.

Ved en kontrolleret fejlmail-test kan workflowet startes manuelt med inputtet `simulate_failure=true`. Testen stopper før filer og databaser hentes og ændrer ingen backupdata.
- Repositoryets Git-historik indeholder ikke et læsbart spejl af livekoden.

## Nødvendig GitHub-konfiguration

Repository Variables:

- `BACKUP_AGE_RECIPIENT`
- `BACKUP_RETENTION_COUNT`
- `SIMPLY_SSH_HOST`, `SIMPLY_SSH_PORT`, `SIMPLY_SSH_USER`
- `PROD_REMOTE_PATH`, `TEST_REMOTE_PATH`
- `PROD_DB_HOST`, `PROD_DB_PORT`, `PROD_DB_NAME`, `PROD_DB_USER`
- `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_NAME`, `TEST_DB_USER`
- `BACKUP_SMTP_HOST`, `BACKUP_SMTP_PORT`, `BACKUP_FROM_EMAIL`
- `BACKUP_NOTIFICATION_EMAIL`

GitHub Actions skal bruge `smtp.simply.com` på port `587` med STARTTLS. `websmtp.simply.com` virker kun fra Simplys egne webservere og må ikke bruges af GitHub-runneren.

Repository Secrets:

- `SIMPLY_SSH_PRIVATE_KEY`
- `SIMPLY_SSH_KNOWN_HOSTS`
- `PROD_DB_PASSWORD`
- `TEST_DB_PASSWORD`
- `BACKUP_SMTP_USERNAME`
- `BACKUP_SMTP_PASSWORD`

Den private `age`-identitet må kun opbevares lokalt og i en separat password manager. GitHub får kun den offentlige modtagernøgle.

## Manuel kørsel og restore-test

```powershell
.\scripts\Invoke-CafeLifBackup.ps1 -Repository h4xtor/cafelif-private -Wait
.\scripts\Get-CafeLifBackup.ps1 -Environment All -Repository h4xtor/cafelif-private
```

Restore-scriptet kontrollerer release-checksummer, dekrypterer, afviser usikre arkivstier og validerer filantal samt databasehash mod hvert snapshot-manifest. Det skriver kun til en ny lokal destinationsmappe og gendanner aldrig over et website eller en database.
