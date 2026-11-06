# Regression tests

Run from the repository root with PHP CLI, the cURL extension and Node.js:

```sh
php tests/payop.php
php tests/options.php
php tests/admin.php
php tests/security.php
php tests/transport.php
node tests/checkout.js
```

The tests use OpenCart, input and API test doubles. They do not contact Payop or require real credentials. Coverage includes invoice authentication and exact amount validation, overdue payments, checkout options, translations, settings validation, method switching, forged and repeated notifications, API errors, and transport security.

`security.php` covers the INS-1206 forged-IPN payload through the public callback method. Tests and this directory are excluded from the installation archive.

Before release, also verify installation, saved settings, checkout selection, method switching and real test IPN delivery on an OpenCart/MySQL staging store. The standalone suite does not replace this integration check.
