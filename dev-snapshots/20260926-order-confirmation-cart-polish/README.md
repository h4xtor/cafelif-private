# Café LIF Dev snapshot, 2026-09-26

Base: read-only `C:\CafeLIF\prod\public_html260926` (195 files). `C:\CafeLIF\dev` was empty and was initialized from this base; no prior Dev configs existed to preserve. The nested historical `test/` files were retained unchanged. The local `C:\CafeLIF\test` folder was empty before staging.

Changes: opt-in mail confirmation checkbox (checked by default), dynamic email requirement, server validation from the older Test order endpoint, post-commit customer mail using the existing PHPMailer adapter, optional admin mail, test recipient guard, mail state columns and log, and small cart/success-state polish. Existing products, variants, meeting item, status lookup and admin UI remain in place. No live deployment or database change was made.

Deployment order for Test only:
1. Back up Test files and Test database.
2. Run `database/migrations/20260926_order_confirmation_email.sql` on `cafelif_dk_db_cafelif_test` manually and verify columns.
3. In Test's private `config.mail.local.php`, add `test_recipient` with a controlled email address. Optionally add `admin_email` for admin notification. Do not put credentials or addresses in Git. Test sending fails closed when `test_recipient` is absent.
4. Copy the changed files in `changed-files.txt` from Dev to the corresponding Test paths. Ensure `storage/logs/` is writable and `storage/.htaccess` is deployed. Keep Test's existing `config.local.php` and `config.mail.local.php` unchanged except for the test recipient key.
5. Execute `test-results.txt` manual cases at `https://cafelif.dk/test`.

The local Dev copy inherited Prod's private configs during initialization. Do not run it against the live database or upload those configs to Test. This snapshot excludes both configs, `.env`, dumps, runtime logs and private keys.

PHP 8.4 CLI in WSL passed lint for all four requested PHP files. Live Test execution remains required before Test approval. The email template is plain structured content with an HTML escaped rendering. Mail is sent only after DB commit; failed mail does not roll back the order. Prod later requires the same migration and a deliberate deployment decision.

