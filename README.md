# Payop Payment Gateway for OpenCart 4

Accept payments through Payop Hosted Page, with optional checkout options for specific payment methods.

## Requirements

- OpenCart 4.0+
- PHP cURL extension
- Payop Public Key and Secret Key

## Installation

1. Download `payop.ocmod.zip` from the [releases page](https://github.com/Payop/opencart-v4-plugin/releases).
2. In OpenCart admin, go to **Extensions → Installer** and upload the archive.
3. Go to **Extensions → Extensions**, select **Payments**, and install **Payop Payment Gateway**.
4. Open the module settings, enter your **Public Key** and **Secret Key**, and configure the pending, successful and failed payment order statuses.
5. Copy the **IPN URL** shown in the settings and add it to your Payop project IPN settings.
6. Enable the module and save.

Find your Public Key and Secret Key in **Payop → Projects → Projects list → Details**.

## Additional checkout options

The main **Payop** option opens Hosted Page with all available methods. It works with the existing Public Key and Secret Key; JWT is optional.

To add options such as **Crypto** or **Revolut**:

1. Open **Extensions → Extensions → Payments → Payop → Edit**.
2. Enter your **Project ID** and **JWT Token**, then click **Load payment methods**. Use the actual Project ID from your Payop dashboard; it may differ from the Public Key. These fields are needed only to load available methods.
3. Click **Add option**. Enter a customer-facing name in each store language and an optional description.
4. Choose the integration type:
   - **Hosted Page** opens all available methods.
   - **Hosted Page with Payment Method ID** opens the method selected in the required payment method dropdown.
5. Enable the option and save. Add, edit, disable or remove further options on the same settings page.

There is no module limit on the number of options. All options share the module credentials, availability rules and order status settings. Custom checkout extensions must support OpenCart payment sub-options.

If methods cannot be loaded, the module displays an error and preserves configured options. Check the Project ID and JWT Token and retry. New or changed Payment Method IDs are validated against the project methods when saving. Existing unchanged options and the general Hosted Page continue to work without a valid JWT.

Each payment attempt creates a new invoice, including after a customer returns from Payop and selects another option. Previous invoices remain linked to their original order, even if the option is later edited or deleted.

## Revolut payer details

For a specific Revolut Payment Method ID, enable **Collect optional Revolut payer details** in that option and save. This setting is off by default; enable it only for Revolut methods. The store displays optional date of birth and account type fields before the payment button. The IBAN country defaults to GB for a GBP order and Europe for a EUR order; other currencies default to completing it on Payop. The shopper can change this selection, including choosing to complete it on Payop.

The invoice uses the saved method ID, the order email and full name, and valid supplied values in `payer.extraFields.date_of_birth` (`dd.mm.yyyy`) and `payer.extraFields.iban_type` (`GB` or `NOT_GB`). Names and surnames in the order must be in Latin characters. The module does not transliterate names or change the order amount or currency. The module sends the IBAN country only for GB with a GBP order or Europe with a EUR order. For other combinations, the shopper chooses the IBAN country on Payop.

With all valid required payer data, Payop Hosted Page can continue directly to the selected payment method. Missing or invalid data can be completed on Payop; skipping its form is controlled by Payop. Optional fields are sent for the current attempt only and are not saved in module settings, invoice history or diagnostic logs.

## Payment verification

The module confirms payment using an authenticated request to the Payop invoice API. JWT is not required for this request. A successful payment must have invoice status `1` and match the saved invoice ID, order ID, amount and currency. Paid overdue invoices are supported.

Unknown invoices, unpaid invoices, API errors and mismatched details do not confirm payment. Repeated notifications do not repeat successful order actions, and late pending or failed notifications do not downgrade a paid order. The browser return to the success page does not confirm payment.

## Upgrading to 1.1.0

Existing module settings and the main Payop option are preserved. Additional checkout options are optional.

The module automatically creates the `payop_invoice` table with your configured database prefix on the first payment request or notification. The OpenCart database account needs `CREATE TABLE` permission. Payment attempts retain the amount and currency sent to Payop, using the order's saved exchange rate.

Invoices created before this update do not have a saved order binding and require manual reconciliation against Payop. Keep the invoice table during upgrades or temporary uninstallation, as it contains payment history and duplicate-prevention records.

If payment processing is interrupted while updating the order, the module logs that reconciliation is required and prevents automatic replay of order actions. Check the Payop invoice, OpenCart order history, stock and notifications before resolving the payment, or contact support.

## Support

- [Open an issue](https://github.com/Payop/opencart-v4-plugin/issues)
- [Payop documentation](https://payop.com/en/documentation/common/)
- [Contact Payop support](https://payop.com/en/contact-us/)

Include your OpenCart and plugin versions, other installed extensions, relevant error messages and screenshots when reporting a problem. Remove credentials and customer data from shared logs and screenshots.

## Contributing

Report bugs and suggestions through [GitHub issues](https://github.com/Payop/opencart-v4-plugin/issues). Submit code changes as pull requests.

## License

Released under the [MIT License](LICENSE).
