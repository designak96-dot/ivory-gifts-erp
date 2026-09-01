# Ivory Gifts ERP — Fix "413 Content Too Large" on Bulk Uploads — 2026-09-01-v54

## What caused this

A "413 Content Too Large" happens at the **web server level**, before
PHP or this app ever see the request — confirmed by checking: the
app's own validation already allows files up to 500MB, so this wasn't
an app bug. Your `products.zip` is ~35MB (84 real products with real
images), which is a completely reasonable size that a hosting
provider's default PHP upload limit (commonly 8MB–32MB on shared
cPanel hosting) will reject outright.

## The fix

Adds a `.user.ini` file to the deployed public folder, raising PHP's
own `upload_max_filesize` and `post_max_size` to 100MB — this is the
standard, reliable way to override PHP limits on shared cPanel hosting
that uses PHP-FPM (the most common setup). Also added the equivalent
`php_value` directives to `.htaccess`, safely wrapped so they're a
no-op (not an error) if your specific setup uses a different PHP
handler that doesn't support them there.

**One honest limitation**: if your specific host has their *web
server's own* request-size cap set below 100MB (separate from PHP's
setting), this file can't override that — that layer sits in front of
PHP entirely. If uploads still fail after installing this, that's the
next thing to check with your host, or via cPanel's own "MultiPHP INI
Editor" if you have access to it.

**Note**: `.user.ini` changes can take a few minutes to take effect
after upload (PHP-FPM caches it, typically 5 minutes) — if the upload
still fails immediately after installing, wait a few minutes and retry
before assuming it didn't work.

## Testing
**525/525 automated tests passing** — this is a server-configuration
change, not application code, so no new test coverage applies here
directly. The fix itself was verified by directly checking the file's
syntax is valid.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260901-v54.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

This installs `.user.ini` and the updated `.htaccess` into your live
`public_html` folder. Does not touch your database, .env, or APP_KEY.
