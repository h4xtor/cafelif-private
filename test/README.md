# Café LIF – Test-site

Denne mappe spejler den deploybare kode i Simply-mappen `/public_html/test`.

## Bestilling og e-mailbekræftelse

- `index.php` viser menuen og bestillingskurven.
- `app.js` holder kurven, antal og beløb opdateret i browseren.
- `style.css` indeholder kurvens responsive layout og den faste sendeknap.
- `actions/order.php` validerer og gemmer en bestilling samt sender bekræftelse, når kunden har valgt e-mail.
- `includes/mailer.php` sender med Simply SMTP fra `noreply@cafelif.dk`; lokale SMTP-oplysninger ligger kun i `config.mail.local.php` på serveren.
- `includes/order_confirmation.php` bygger automatisk mail og manuel gensendelse fra den gemte ordre og dens varelinjer. På Test bruges kun den konfigurerede testmodtager.
- `admin/orders.php` viser bestillinger og om kunden har valgt e-mailbekræftelse.

Admin viser kundens oprindelige Ja/Nej med fed tekst. Find en ordre via ordrenummer, navn, mobil eller e-mail i Aktive eller Historik, og brug Send/Gensend bekræftelse i ordredetaljerne. Hvis kunden oprindeligt fravalgte mail, kræves en markering af, at kunden efterfølgende har bedt om den. Seneste afsendelsestid og fejl vises; gensendelse er låst i 60 sekunder efter en vellykket afsendelse. En fejlet gensendelse bevarer tidspunktet for seneste vellykkede afsendelse.

Valideret på Test 27.09.2026: ordre LIF-260927-33913D med automatisk mail og efterfølgende gensendelse, ordre LIF-260927-D77707 med mail fravalgt og efterfølgende manuel mail. SMTP accepterede afsendelserne. Ordrenummersøgning, fed Ja/Nej og spærring af hurtig gensendelse er kontrolleret i browseren. Modtagelse i indbakken kræver separat bekræftelse fra testmodtageren.
- `database/migrations/20260926_order_confirmation_email.sql` tilføjer felterne til e-mailbekræftelse.

## Lokale filer, som bevidst ikke er med

`config.local.php`, `config.mail.local.php`, databasedumps, `storage/` og `uploads/` indeholder miljødata eller kundedata og skal aldrig commit’es. Opret dem på Simply ud fra de eksisterende serverfiler, når Test installeres på ny.

## Kontrol efter ændringer

1. Åbn `https://cafelif.dk/test/#menu` og læg en vare i kurven.
2. Bekræft at pris, antal og sendeknap opdateres.
3. Gennemfør kun en testbestilling med testdata og bekræft, at admin viser kundens valg om e-mail.
4. Kontroller den modtagne mail fra `noreply@cafelif.dk`.
