# Café LIF Dev snapshot, 2026-09-26

Base: read-only `C:\CafeLIF\prod\public_html260926` (195 files). `C:\CafeLIF\dev` was empty and was initialized from this base; no prior Dev configs existed to preserve. The nested historical `test/` files were retained unchanged. The local `C:\CafeLIF\test` folder was empty before staging.

Changes: opt-in mail confirmation checkbox (checked by default), dynamic email requirement, server validation from the older Test order endpoint, post-commit customer mail using the existing PHPMailer adapter, optional admin mail, test recipient guard, mail state columns and log, and small cart/success-state polish. Existing products, variants, meeting item, status lookup and admin UI remain in place. No live deployment or database change was made.

Deployment order for Test only:
1. Back up Test files and Test database.
2. Run `database/migrations/20260926_order_confirmation_email.sql` on `cafelif_dk_db_cafelif_test` manually and verify columns.
3. Test mail goes to `test_recipient` from its private `config.mail.local.php`; if absent, it goes to the validated SMTP `from_email` belonging to Café LIF. Optionally add `admin_email` for admin notification. Do not put credentials in Git.
4. Copy the changed files in `changed-files.txt` from Dev to the corresponding Test paths. Ensure `storage/logs/` is writable and `storage/.htaccess` is deployed. Keep Test's existing `config.local.php` and `config.mail.local.php` unchanged except for the test recipient key.
5. Execute `test-results.txt` manual cases at `https://cafelif.dk/test`.

The local Dev copy inherited Prod's private configs during initialization. Do not run it against the live database or upload those configs to Test. This snapshot excludes both configs, `.env`, dumps, runtime logs and private keys.

PHP 8.4 CLI in WSL passed lint for all four requested PHP files. Live Test execution remains required before Test approval. The email template is plain structured content with an HTML escaped rendering. Mail is sent only after DB commit; failed mail does not roll back the order. Prod later requires the same migration and a deliberate deployment decision.



## Test deployment 2026-09-26
Prod SQL and pre-refresh Test SQL were backed up locally under `C:\CafeLIF\_db-backups` and intentionally excluded from GitHub. Prod SQL was imported into Test only; the idempotent migration succeeded there (12 tables, 5 new mail columns, 83 imported orders, 2 admin accounts). A 22-file safe runtime overlay from Dev was uploaded and extracted in `/public_html/test`; private configs and uploads were preserved. No Prod files or Prod database objects were changed.

Live Test evidence: homepage/menu and checkout rendered; blank and invalid requested email produced errors; unchecked email saved order `LIF-260926-F8ABD4`; requested email saved `LIF-260926-8EB4E0` with mail sent in Test; XL requested email saved `LIF-260926-C0E25D` at 80 DKK. Success copy correctly says Test mail went to Café LIF's test recipient. Mobile order status found both initial test orders. Admin login page rendered; authenticated admin view still requires the user to sign in.
