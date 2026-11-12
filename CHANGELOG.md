# Changelog

## 1.1.1

- Send the OpenCart 4 integration tag when creating Payop invoices.

## 1.1.0

- Show GB and Europe as Revolut IBAN country choices and preselect based on the order currency. Send the choice only when GB matches GBP or Europe matches EUR.

- Add optional Revolut payer date of birth and account type fields per payment option, forwarding valid values with the order payer details without changing the order currency or amount.

- Distinguish Payop invoice creation failures from unavailable checkout options and show the minimum payment amount when Payop rejects an amount below its limit.

- Add multiple Payop checkout options alongside the general Hosted Page, with translated names and descriptions, enable/disable controls, and inline editing and removal.
- Support Hosted Page with a specific Payment Method ID, selected from saved module settings.
- Add Project ID and optional JWT Token for loading available project payment methods. Preserve configured options when loading fails.
- Create a new invoice for each payment attempt and retain previous invoices for late payment notifications.
- Authenticate invoice verification with the Public Key and a SHA-256 signature, allowing paid overdue invoices to be confirmed without JWT.
- Prevent forged payment notifications from changing order statuses: require a saved invoice/order binding, paid invoice status, and matching order ID, amount and currency from the Payop API.
- Prevent duplicate successful payment actions and order status downgrades from late notifications.
- Enable TLS certificate verification and request timeouts, and remove notification payloads from diagnostic logs.

## 1.0.2

- Replace the Payop logo.
