<?php
/** Remove only Light Auth settings when a site owner uninstalls the plugin. */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$light_auth_options = array(
    'la_sms_username',
    'la_sms_password',
    'la_sms_bodyid',
    'la_otp_length',
    'la_otp_expiry',
    'la_otp_cooldown',
    'la_otp_attempts',
    'la_login_redirect',
);

foreach ($light_auth_options as $light_auth_option) {
    delete_option($light_auth_option);
}

// User phone metadata is intentionally preserved. It may also be used by
// WooCommerce or another plugin. Expiring OTP transients expire naturally.
