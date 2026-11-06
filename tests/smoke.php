<?php
/**
 * Dependency-free smoke tests for the real plugin PHP file.
 * No network requests or WordPress installation required.
 * Run: php tests/smoke.php
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
$GLOBALS['options'] = array(
    'la_sms_username' => 'test-user',
    'la_sms_password' => 'test-password',
    'la_sms_bodyid' => 123,
    'la_otp_length' => 6,
    'la_otp_expiry' => 3,
    'la_otp_cooldown' => 60,
    'la_otp_attempts' => 5,
);
$GLOBALS['transients'] = array();
$GLOBALS['users'] = array();
$GLOBALS['sms_codes'] = array();
$GLOBALS['logged_in'] = null;
$GLOBALS['next_user_id'] = 1;
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$_SERVER['REQUEST_METHOD'] = 'POST';

class WP_Error {
    public $code;
    public function __construct($code = '', $message = '') { $this->code = $code; }
}
class LA_Ajax_Response extends Exception {
    public $success;
    public $data;
    public function __construct($success, $data) { parent::__construct('AJAX completed'); $this->success = $success; $this->data = $data; }
}
function add_action() { }
function add_filter() { }
function add_shortcode() { }
function plugin_basename($name) { return $name; }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function add_option($key, $value, $deprecated = '', $autoload = 'yes') {
    if (array_key_exists($key, $GLOBALS['options'])) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function set_transient($key, $value, $ttl = 0) { $GLOBALS['transients'][$key] = array('value' => $value, 'until' => time() + $ttl); return true; }
function get_transient($key) {
    if (!isset($GLOBALS['transients'][$key])) return false;
    if ($GLOBALS['transients'][$key]['until'] < time()) { unset($GLOBALS['transients'][$key]); return false; }
    return $GLOBALS['transients'][$key]['value'];
}
function delete_transient($key) { unset($GLOBALS['transients'][$key]); return true; }
function wp_salt($scheme = '') { return 'test-secret-only'; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function check_ajax_referer($action, $field) { if (!isset($_POST[$field]) || $_POST[$field] !== 'test-nonce') throw new Exception('CSRF check failed'); }
function wp_create_nonce($action) { return 'test-nonce'; }
function nocache_headers() { }
function wp_send_json_success($data = null, $status_code = null) { throw new LA_Ajax_Response(true, $data); }
function wp_send_json_error($data = null, $status_code = null) { throw new LA_Ajax_Response(false, $data); }
function wp_remote_post($url, $args) {
    if ($url !== 'https://api.payamak-panel.com/post/Send.asmx/SendByBaseNumber2') throw new Exception('Wrong API endpoint');
    if (empty($args['sslverify']) || !empty($args['redirection'])) throw new Exception('Insecure API transport');
    $GLOBALS['sms_codes'][] = $args['body']['text'];
    return array('status' => 200, 'body' => '1234567890123');
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function get_users($args) {
    $matches = array();
    foreach ($GLOBALS['users'] as $user) {
        if (!empty($user->meta[$args['meta_key']]) && $user->meta[$args['meta_key']] === $args['meta_value']) $matches[] = $user;
    }
    return array_slice($matches, 0, $args['number']);
}
function user_can($user, $cap) { return !empty($user->caps[$cap]); }
function username_exists($name) {
    foreach ($GLOBALS['users'] as $user) if ($user->user_login === $name) return $user->ID;
    return false;
}
function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) { return str_repeat('X', $length); }
function wp_insert_user($data) {
    $id = $GLOBALS['next_user_id']++;
    $user = (object) array('ID' => $id, 'user_login' => $data['user_login'], 'display_name' => $data['display_name'], 'role' => $data['role'], 'caps' => array(), 'meta' => array());
    $GLOBALS['users'][$id] = $user;
    return $id;
}
function update_user_meta($id, $key, $value) { $GLOBALS['users'][$id]->meta[$key] = $value; }
function get_user_by($field, $id) { return isset($GLOBALS['users'][$id]) ? $GLOBALS['users'][$id] : false; }
function wp_set_current_user($id) { $GLOBALS['logged_in'] = $id; }
function wp_set_auth_cookie($id, $remember = false, $secure = false) { $GLOBALS['cookie_user'] = $id; $GLOBALS['cookie_remember'] = $remember; }
function is_ssl() { return true; }
function do_action() { }

require dirname(__DIR__) . '/light-auth.php';
$tests = 0;
function check($condition, $message) {
    global $tests;
    $tests++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}
function endpoint($function, $params = array()) {
    $_POST = array_merge($params, array('security' => 'test-nonce'));
    try { $function(); } catch (LA_Ajax_Response $res) { return $res; }
    throw new RuntimeException('AJAX endpoint did not produce a response');
}
function current_sms_code() { return end($GLOBALS['sms_codes']); }
function reset_phone_cooldown($phone) { delete_transient(la_secret_key('cooldown', $phone)); }

// Input normalization and configuration bounds.
check(la_normalize_phone('۰۹۱۲۳۴۵۶۷۸۹') === '09123456789', 'Persian digits');
check(la_normalize_phone('+989123456789') === '09123456789', '+98 input');
check(la_normalize_phone('00989123456789') === '09123456789', '0098 input');
check(la_normalize_phone('9123456789') === '09123456789', 'leading zero input');
check(la_normalize_phone('091234567890') === '', 'reject oversize phone');
check(la_setting_int('la_otp_attempts', 5, 3, 8) === 5, 'config reads');
check(la_sanitize_int_option(100, 3, 8) === 8, 'config caps');
check(la_keep_sms_password('') === 'test-password', 'password retained on empty');
check(endpoint('la_get_auth_nonce')->success, 'nonce refresh endpoint available');

$phone = '09123456789';
$sent = endpoint('la_send_otp', array('phone' => $phone));
check($sent->success, 'SMS sent');
check(!isset($sent->data['is_new_user']), 'does not reveal account existence');
$otp = current_sms_code();
check(strlen($otp) === 6, 'OTP 6 digits');
$stored = get_transient(la_secret_key('code', $phone));
check(isset($stored['digest']) && !isset($stored['otp']), 'OTP digest only');
check(strpos(serialize($stored), $otp) === false, 'OTP plaintext absent');
$cooldown = endpoint('la_send_otp', array('phone' => $phone));
check(!$cooldown->success && $cooldown->data['code'] === 'rate_limited', 'resend cooldown');

for ($i = 0; $i < 5; $i++) {
    $wrong = endpoint('la_verify_otp', array('phone' => $phone, 'otp' => '000000'));
    check(!$wrong->success, 'incorrect OTP rejected');
}
check($wrong->data['code'] === 'locked', 'attempt limit locks challenge');
check(get_transient(la_secret_key('code', $phone)) === false, 'challenge deleted after too many attempts');

reset_phone_cooldown($phone);
check(endpoint('la_send_otp', array('phone' => $phone))->success, 'resend after cooldown expiry');
$otp = current_sms_code();
$step = endpoint('la_verify_otp', array('phone' => $phone, 'otp' => $otp));
check($step->success && !empty($step->data['needs_name']), 'requires name only for new verified phone');
check(count($GLOBALS['users']) === 0, 'no account created before name');
$registered = endpoint('la_verify_otp', array('phone' => $phone, 'otp' => $otp, 'fullname' => 'تست کاربر', 'remember_me' => '1'));
check($registered->success && empty($registered->data['needs_name']), 'creates account with verified name');
check(count($GLOBALS['users']) === 1, 'exactly one user');
$user = $GLOBALS['users'][1];
check($user->meta['phone_number'] === $phone && $user->meta['billing_phone'] === $phone, 'phone metadata set');
check($user->role === 'subscriber', 'subscriber role without WooCommerce');
check($GLOBALS['cookie_user'] === 1 && $GLOBALS['cookie_remember'] === true, 'cookie created with optional remember');
check(get_transient(la_secret_key('code', $phone)) === false, 'consumed OTP deleted');
$replay = endpoint('la_verify_otp', array('phone' => $phone, 'otp' => $otp));
check(!$replay->success, 'replay rejected');

reset_phone_cooldown($phone);
check(endpoint('la_send_otp', array('phone' => $phone))->success, 'third send allowed within phone hourly cap');
$login = endpoint('la_verify_otp', array('phone' => $phone, 'otp' => current_sms_code()));
check($login->success && empty($login->data['needs_name']), 'existing user logs in without name');

reset_phone_cooldown($phone);
$user->caps['manage_options'] = true;
check(endpoint('la_send_otp', array('phone' => $phone))->success, 'send for privileged account reveals nothing');
$blocked = endpoint('la_verify_otp', array('phone' => $phone, 'otp' => current_sms_code()));
check(!$blocked->success && $blocked->data['code'] === 'privileged_account', 'privileged account denied');

// The same phone cannot be attached to two different accounts for OTP login.
$user->caps = array();
$another = (object) array('ID' => 100, 'user_login' => 'another', 'display_name' => 'Another', 'caps' => array(), 'meta' => array('phone_number' => $phone));
$GLOBALS['users'][100] = $another;
check(is_wp_error(la_get_user_by_phone($phone)), 'duplicate phone conflict blocked');

// Quota checks are real plugin functions, not independent reimplementations.
check(la_consume_quota('test-specific-limit', 2, 60) === 0, 'quota first');
check(la_consume_quota('test-specific-limit', 2, 60) === 0, 'quota second');
check(la_consume_quota('test-specific-limit', 2, 60) > 0, 'quota third rejected');

fwrite(STDOUT, "OK — {$tests} assertions passed.\n");
