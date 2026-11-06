<?php

$_['heading_title'] = 'Payop Payment Gateway';

$_['text_payop'] = '<a href="https://payop.com" target="_blank"><img src="/extension/payop/admin/view/image/payment/payop.png" style="max-width:130px; height:auto;"></a>';
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified Payop settings!';
$_['text_edit'] = 'Edit Payop Payment Gateway settings';

$_['entry_public_id'] = 'Public Key';
$_['entry_secret_key'] = 'Secret Key';
$_['entry_order_status_wait'] = 'Order Status for Pending Payment';
$_['entry_order_status_success'] = 'Order Status for Successful Payment';
$_['entry_order_status_error'] = 'Order Status for Failed Payment';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort Order';
$_['entry_ipn_url'] = 'IPN URL';

$_['help_public_id'] = 'You can find this parameter in your Payop project settings.';
$_['help_secret_key'] = 'You can find this parameter in your Payop project settings.';
$_['help_ipn_url'] = 'Copy this URL and add it in Payop dashboard under "IPN" settings.';

$_['error_permission'] = 'Warning: You do not have permission to modify these settings!';
$_['error_public_id'] = 'This field is required.';
$_['error_secret_key'] = 'This field is required.';

$_['entry_project_id'] = 'Project ID';
$_['entry_jwt_token'] = 'JWT Token';
$_['help_project_id'] = 'Use the actual Project ID from your Payop dashboard. It may differ from the Public Key. Only needed to load payment methods.';
$_['help_jwt_token'] = 'Only used to load project payment methods. Regular Hosted Page and invoice verification work without JWT.';
$_['help_payment_buttons'] = 'The main Payop Hosted Page remains available. Add any number of additional options here. Enter a name for each store language; descriptions are optional. Load methods before selecting a new Payment Method ID.';
$_['entry_button_name'] = 'Customer name';
$_['entry_button_description'] = 'Customer description';
$_['entry_integration_type'] = 'Integration type';
$_['entry_payment_method'] = 'Payment method';
$_['text_payment_buttons'] = 'Additional checkout options';
$_['text_hosted_page'] = 'Hosted Page';
$_['text_payment_method'] = 'Hosted Page with Payment Method ID';
$_['text_saved_method'] = 'Saved method ID:';
$_['text_select_method'] = 'Select a payment method';
$_['text_methods_loaded'] = 'Payment methods loaded. Existing selections have been preserved.';
$_['text_buttons_javascript'] = 'JavaScript is required to edit additional options. Saved options are preserved when saving other settings.';
$_['button_load_methods'] = 'Load payment methods';
$_['button_add'] = 'Add option';
$_['button_remove'] = 'Remove option';
$_['error_buttons'] = 'Invalid checkout option data. Settings were not saved.';
$_['error_button_method'] = 'Select a payment method available to this project for each option using Payment Method ID.';
$_['error_button_name'] = 'Enter a name for each store language for every enabled option.';
$_['error_methods_credentials'] = 'Enter Project ID and JWT Token to load payment methods. Existing options are preserved.';
$_['error_methods_auth'] = 'Payop rejected the JWT Token or access to this project. Check the token and Project ID. Existing options are preserved.';
$_['error_methods_network'] = 'Unable to connect to Payop. Try loading payment methods again. Existing options are preserved.';
$_['error_methods_response'] = 'Payop could not return payment methods. Check Project ID and JWT Token and try again. Existing options are preserved.';

$_['entry_revolut_fields'] = 'Collect optional Revolut payer details';
