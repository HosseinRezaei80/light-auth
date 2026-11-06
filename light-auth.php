<?php
/**
 * Plugin Name: ورود و ثبت‌نام یکپارچه پیامکی (Light Auth OTP)
 * Description: ورود و ثبت‌نام پیامکی سبک با کد یک‌بارمصرف، ملی‌پیامک، پاپ‌آپ و فرم درون‌صفحه‌ای.
 * Version: 3.6.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Light Auth Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: light-auth
 */

if (!defined('ABSPATH')) exit;

define('LA_VERSION', '3.6.1');

define('LA_FONT_VARIABLE', '100 1000');

// ==========================================
// فونت یکان بخ از پوشه fonts داخل افزونه
// فایل‌های woff2 / woff / ttf را با هر نامی در پوشه fonts بگذارید؛
// وزن هر فایل از روی نامش (Regular, Medium, Bold, ...) تشخیص داده می‌شود.
// ==========================================
function la_get_font_files() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $dir = plugin_dir_path(__FILE__) . 'fonts/';
    if (!is_dir($dir)) return $cached = array();

    // ترتیب مهم است: کلمات ترکیبی قبل از کلمات ساده بررسی می‌شوند
    $weight_map = array(
        'extrablack' => 950, 'ultrablack' => 950, 'black' => 900,
        'extrabold'  => 800, 'ultrabold'  => 800, 'heavy' => 800,
        'semibold'   => 600, 'demibold'   => 600, 'bold'  => 700,
        'medium'     => 500, 'regular'    => 400, 'normal' => 400,
        'extralight' => 200, 'ultralight' => 200, 'light' => 300,
        'hairline'   => 100, 'thin'       => 100,
    );
    $format_rank = array('woff2' => 1, 'woff' => 2, 'ttf' => 3);
    $format_name = array('woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype');

    $sets     = array('en' => array(), 'fanum' => array());
    $variable = array('en' => array(), 'fanum' => array());

    foreach ((array) scandir($dir) as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!isset($format_rank[$ext])) continue;

        $key = strtolower(preg_replace('/[\s\-_]+/', '', pathinfo($file, PATHINFO_FILENAME)));
        $set = (strpos($key, 'fanum') !== false) ? 'fanum' : 'en';

        // فونت متغیر (Variable) — همه وزن‌ها در یک فایل
        if (preg_match('/variable|wght|vf$|\bvf\b/', $key)) {
            // نسخه «وریبل عرض» (wdth) لازم نیست؛ فقط محور وزن
            if (strpos($key, 'wdth') !== false || strpos($key, 'width') !== false) continue;
            $variable[$set][LA_FONT_VARIABLE][$ext] = $file;
            continue;
        }

        $weight = 400;
        foreach ($weight_map as $word => $w) {
            if (strpos($key, $word) !== false) { $weight = $w; break; }
        }
        $sets[$set][$weight][$ext] = $file;
    }

    // اولویت: متغیر انگلیسی ← ثابت انگلیسی ← متغیر FaNum ← ثابت FaNum
    if (!empty($variable['en']))      $chosen = $variable['en'];
    elseif (!empty($sets['en']))      $chosen = $sets['en'];
    elseif (!empty($variable['fanum'])) $chosen = $variable['fanum'];
    else                              $chosen = $sets['fanum'];
    ksort($chosen);

    $result = array();
    foreach ($chosen as $weight => $files) {
        uksort($files, function ($a, $b) use ($format_rank) { return $format_rank[$a] - $format_rank[$b]; });
        $src = array();
        foreach ($files as $ext => $file) {
            $src[] = array('url' => plugins_url('fonts/' . rawurlencode($file), __FILE__), 'format' => $format_name[$ext], 'file' => $file);
        }
        $result[$weight] = $src;
    }
    return $cached = $result;
}

function la_get_font_face_css() {
    $css = '';
    foreach (la_get_font_files() as $weight => $sources) {
        $src = array();
        foreach ($sources as $s) {
            $src[] = "url('" . esc_url($s['url']) . "') format('" . $s['format'] . "')";
        }
        $weight_css = ($weight === LA_FONT_VARIABLE) ? LA_FONT_VARIABLE : intval($weight);
        $css .= "@font-face{font-family:'LA Yekan Bakh';font-style:normal;font-weight:" . $weight_css . ";font-display:swap;src:" . implode(',', $src) . ";}\n";
    }
    return $css;
}

// پیش‌بارگذاری وزن‌های اصلی فونت برای جلوگیری از پرش متن
function la_preload_fonts() {
    $fonts = la_get_font_files();
    foreach (array(LA_FONT_VARIABLE, 400, 700) as $weight) {
        if (isset($fonts[$weight][0]) && $fonts[$weight][0]['format'] === 'woff2') {
            echo '<link rel="preload" href="' . esc_url($fonts[$weight][0]['url']) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }
}
add_action('wp_head', 'la_preload_fonts', 2);

// ==========================================
// تم روشن/تیره — پیش‌فرض تیره، انتخاب کاربر در مرورگرش ذخیره می‌شود
// این اسکریپت کوچک در head اجرا می‌شود تا هنگام بارگذاری صفحه، تم اشتباه لحظه‌ای دیده نشود
// ==========================================
function la_theme_boot_script() {
    echo "<script>(function(){try{var t=localStorage.getItem('la_theme');if(t==='light'||t==='dark'){document.documentElement.setAttribute('data-la-theme',t);}}catch(e){}})();</script>\n";
}
add_action('wp_head', 'la_theme_boot_script', 1);

function la_theme_toggle_html() {
    return '<button type="button" class="la-theme-toggle" aria-label="تغییر به تم روشن" title="تغییر تم">
                <svg class="la-ic-sun" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path></svg>
                <svg class="la-ic-moon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>';
}

// بارگذاری فایل‌های CSS و JS در کل سایت
function la_enqueue_assets() {
    wp_enqueue_style('la-style', plugins_url('style.css', __FILE__), array(), LA_VERSION);
    wp_enqueue_script('la-script', plugins_url('script.js', __FILE__), array(), LA_VERSION, true);

    $font_css = la_get_font_face_css();
    if ($font_css) {
        wp_add_inline_style('la-style', $font_css);
    }

    wp_localize_script('la-script', 'la_ajax', array(
        'url'             => admin_url('admin-ajax.php'),
        // Token obtained on demand (rather than embedded in cacheable HTML).
        'otpLength'       => la_setting_int('la_otp_length', 6, 5, 6),
        'expirySeconds'   => la_setting_int('la_otp_expiry', 3, 2, 10) * MINUTE_IN_SECONDS,
        'resendSeconds'   => la_setting_int('la_otp_cooldown', 60, 45, 180),
        'redirect'        => la_login_redirect_url(),
    ));
}
add_action('wp_enqueue_scripts', 'la_enqueue_assets');

// ==========================================
// ۱. ایجاد صفحه تنظیمات در پیشخوان وردپرس
// ==========================================
function la_add_settings_menu() {
    add_options_page(
        'تنظیمات افزونه پیامک',
        'تنظیمات پیامک',
        'manage_options',
        'light-auth-settings',
        'la_settings_page_html'
    );
}
add_action('admin_menu', 'la_add_settings_menu');

/** Admin assets are only loaded on this plugin's settings screen. */
function la_admin_enqueue_assets($hook_suffix) {
    if ($hook_suffix !== 'settings_page_light-auth-settings') return;
    wp_enqueue_style('la-admin-style', plugins_url('admin.css', __FILE__), array(), LA_VERSION);
    if (la_get_font_files()) {
        wp_add_inline_style('la-admin-style', la_get_font_face_css());
    }
    wp_enqueue_script('la-admin-script', plugins_url('admin.js', __FILE__), array(), LA_VERSION, true);
}
add_action('admin_enqueue_scripts', 'la_admin_enqueue_assets');


/** Lightweight configuration; server-side limits remain authoritative. */
function la_setting_int($name, $default, $min, $max) {
    return max($min, min($max, absint(get_option($name, $default))));
}

function la_sanitize_int_option($value, $min, $max) {
    return max($min, min($max, absint($value)));
}

function la_keep_sms_password($value) {
    // Empty password fields never overwrite an existing credential.
    $value = is_string($value) ? trim($value) : '';
    return $value === '' ? (string) get_option('la_sms_password', '') : $value;
}

function la_register_settings() {
    register_setting('la_options_group', 'la_sms_username', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('la_options_group', 'la_sms_password', array('sanitize_callback' => 'la_keep_sms_password'));
    register_setting('la_options_group', 'la_sms_bodyid', array('sanitize_callback' => 'absint'));
    register_setting('la_options_group', 'la_otp_length', array('sanitize_callback' => function ($v) { return la_sanitize_int_option($v, 5, 6); }, 'default' => 6));
    register_setting('la_options_group', 'la_otp_expiry', array('sanitize_callback' => function ($v) { return la_sanitize_int_option($v, 2, 10); }, 'default' => 3));
    register_setting('la_options_group', 'la_otp_cooldown', array('sanitize_callback' => function ($v) { return la_sanitize_int_option($v, 45, 180); }, 'default' => 60));
    register_setting('la_options_group', 'la_otp_attempts', array('sanitize_callback' => function ($v) { return la_sanitize_int_option($v, 3, 8); }, 'default' => 5));
    register_setting('la_options_group', 'la_login_redirect', array('sanitize_callback' => function ($v) {
        return in_array($v, array('same', 'home', 'myaccount'), true) ? $v : 'same';
    }, 'default' => 'same'));
}

function la_login_redirect_url() {
    $choice = (string) get_option('la_login_redirect', 'same');
    if ($choice === 'home') return home_url('/');
    if ($choice === 'myaccount' && function_exists('wc_get_page_permalink')) {
        return wp_validate_redirect(wc_get_page_permalink('myaccount'), home_url('/'));
    }
    return '';
}
add_action('admin_init', 'la_register_settings');

// لینک «تنظیمات» کنار نام افزونه در صفحه افزونه‌ها
function la_plugin_action_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=light-auth-settings')) . '">تنظیمات</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'la_plugin_action_links');

function la_settings_page_html() {
    if (!current_user_can('manage_options')) return;

    $username = (string) get_option('la_sms_username', '');
    $password = (string) get_option('la_sms_password', '');
    $bodyid   = absint(get_option('la_sms_bodyid', 0));
    $ready    = $username !== '' && $password !== '' && $bodyid > 0;
    $shortcodes = array(
        array('code' => '[light_auth]', 'desc' => 'فرم ورود و عضویت داخل یک برگه یا نوشته'),
        array('code' => '[light_auth_btn]', 'desc' => 'دکمه ورود و منوی حساب کاربری'),
        array('code' => '[light_auth_btn text="ورود"]', 'desc' => 'دکمه ورود با عنوان دلخواه'),
    );
    $font_exists = !empty(la_get_font_files());
    ?>
    <div class="wrap la-admin" dir="rtl">
        <div class="la-admin-hero">
            <div class="la-admin-hero-main">
                <div class="la-admin-brand">
                    <span class="la-admin-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none"><rect x="7.8" y="14" width="16.4" height="13" rx="4" stroke="currentColor" stroke-width="2.5"/><path d="M11.5 14V10a4.5 4.5 0 019 0v4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="16" cy="20.5" r="1.75" fill="currentColor"/></svg>
                    </span>
                    <div>
                        <span class="la-admin-eyebrow">LIGHT AUTH <span class="la-admin-hero-dot">·</span> OTP</span>
                        <h1>مدیریت ورود پیامکی</h1>
                    </div>
                </div>
                <p class="la-admin-hero-desc">تنظیمات سرویس پیامک، حفاظت از کد تأیید و تجربه ورود کاربران — ساده و یکپارچه.</p>
            </div>
            <div class="la-admin-hero-meta">
                <span class="la-admin-version">نسخه <?php echo esc_html(LA_VERSION); ?></span>
                <span class="la-admin-status <?php echo $ready ? 'is-ready' : 'is-missing'; ?>">
                    <span class="la-admin-status-dot" aria-hidden="true"></span>
                    <?php echo $ready ? 'اطلاعات درگاه ثبت شده' : 'اتصال پیامک تنظیم نشده'; ?>
                </span>
            </div>
        </div>

        <?php settings_errors('la_options_group'); ?>
        <nav class="la-admin-nav" aria-label="بخش‌های تنظیمات">
            <a href="#la-connection"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> اتصال پیامک</a>
            <a href="#la-security"><span class="dashicons dashicons-shield" aria-hidden="true"></span> امنیت کد تأیید</a>
            <a href="#la-behavior"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> رفتار ورود</a>
            <a href="#la-guide"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> راهنمای استفاده</a>
        </nav>

        <div class="la-admin-layout">
            <form method="post" action="options.php" autocomplete="off" class="la-admin-form">
                <?php settings_fields('la_options_group'); ?>
                <section class="la-admin-card" id="la-connection" aria-labelledby="la-connection-title">
                    <header class="la-admin-card-head">
                        <span class="la-admin-card-icon"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span></span>
                        <div><h2 id="la-connection-title">اتصال به ملی‌پیامک</h2><p>مشخصات ورود به پنل و الگوی ارسال کد را وارد کنید.</p></div>
                    </header>
                    <div class="la-admin-fields">
                        <div class="la-admin-field">
                            <label for="la_sms_username">نام کاربری درگاه <span class="la-required" aria-hidden="true">*</span></label>
                            <input type="text" id="la_sms_username" name="la_sms_username" value="<?php echo esc_attr($username); ?>" dir="ltr" autocomplete="off" spellcheck="false" placeholder="SMS username" />
                            <p class="description">نام کاربری حساب ملی‌پیامک</p>
                        </div>
                        <div class="la-admin-field">
                            <label for="la_sms_password">رمز عبور / API Key <span class="la-required" aria-hidden="true">*</span></label>
                            <div class="la-admin-password"><input type="password" id="la_sms_password" name="la_sms_password" value="" dir="ltr" autocomplete="new-password" spellcheck="false" placeholder="<?php echo esc_attr($password !== '' ? '••••••••  (ذخیره‌شده)' : 'Password or API key'); ?>" /><button type="button" class="la-admin-password-toggle" data-toggle-password="la_sms_password" aria-pressed="false">نمایش</button></div>
                            <p class="description">برای نگه‌داشتن مقدار قبلی، این فیلد را خالی بگذارید.</p>
                        </div>
                        <div class="la-admin-field la-admin-field-full">
                            <label for="la_sms_bodyid">شناسه الگوی پیامک (BodyId) <span class="la-required" aria-hidden="true">*</span></label>
                            <input type="number" id="la_sms_bodyid" name="la_sms_bodyid" value="<?php echo esc_attr($bodyid ?: ''); ?>" min="1" step="1" dir="ltr" inputmode="numeric" placeholder="123456" />
                            <p class="description">الگو باید در ملی‌پیامک تأیید شده باشد و یک متغیر کد مانند <code dir="ltr">{0}</code> داشته باشد.</p>
                        </div>
                    </div>
                </section>

                <section class="la-admin-card" id="la-security" aria-labelledby="la-security-title">
                    <header class="la-admin-card-head">
                        <span class="la-admin-card-icon"><span class="dashicons dashicons-shield" aria-hidden="true"></span></span>
                        <div><h2 id="la-security-title">تنظیمات امنیت و کد تأیید</h2><p>محدودیت‌های اصلی ارسال و تأیید کد از سمت سرور اعمال می‌شوند.</p></div>
                    </header>
                    <div class="la-admin-fields la-admin-fields-two">
                        <div class="la-admin-field">
                            <label for="la_otp_length">طول کد تأیید</label>
                            <select id="la_otp_length" name="la_otp_length">
                                <?php foreach (array(6, 5) as $n) : ?>
                                    <option value="<?php echo (int) $n; ?>" <?php selected(la_setting_int('la_otp_length', 6, 5, 6), $n); ?>><?php echo (int) $n; ?> رقم</option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">۶ رقم پیشنهاد می‌شود.</p>
                        </div>
                        <div class="la-admin-field">
                            <label for="la_otp_expiry">مدت اعتبار کد</label>
                            <div class="la-admin-input-suffix"><input type="number" id="la_otp_expiry" name="la_otp_expiry" min="2" max="10" step="1" value="<?php echo esc_attr(la_setting_int('la_otp_expiry', 3, 2, 10)); ?>" dir="ltr" /><span>دقیقه</span></div>
                            <p class="description">بین ۲ تا ۱۰ دقیقه</p>
                        </div>
                        <div class="la-admin-field">
                            <label for="la_otp_cooldown">فاصله ارسال مجدد</label>
                            <div class="la-admin-input-suffix"><input type="number" id="la_otp_cooldown" name="la_otp_cooldown" min="45" max="180" step="1" value="<?php echo esc_attr(la_setting_int('la_otp_cooldown', 60, 45, 180)); ?>" dir="ltr" /><span>ثانیه</span></div>
                            <p class="description">بین ۴۵ تا ۱۸۰ ثانیه</p>
                        </div>
                        <div class="la-admin-field">
                            <label for="la_otp_attempts">حداکثر تلاش اشتباه</label>
                            <div class="la-admin-input-suffix"><input type="number" id="la_otp_attempts" name="la_otp_attempts" min="3" max="8" step="1" value="<?php echo esc_attr(la_setting_int('la_otp_attempts', 5, 3, 8)); ?>" dir="ltr" /><span>بار</span></div>
                            <p class="description">بعد از آن، کد باطل می‌شود.</p>
                        </div>
                    </div>
                    <div class="la-admin-note"><span class="dashicons dashicons-lock" aria-hidden="true"></span><p>تلاش برای حدس کد و ارسال انبوه پیامک محدود شده است. ورود پیامکی به حساب‌های دارای دسترسی مدیریتی نیز مسدود است.</p></div>
                </section>

                <section class="la-admin-card" id="la-behavior" aria-labelledby="la-behavior-title">
                    <header class="la-admin-card-head">
                        <span class="la-admin-card-icon"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span></span>
                        <div><h2 id="la-behavior-title">رفتار بعد از ورود</h2><p>تعیین کنید کاربر بعد از تأیید کد کجا هدایت شود.</p></div>
                    </header>
                    <div class="la-admin-field">
                        <label for="la_login_redirect">انتقال بعد از ورود موفق</label>
                        <select id="la_login_redirect" name="la_login_redirect">
                            <option value="same" <?php selected(get_option('la_login_redirect', 'same'), 'same'); ?>>همین صفحه (بارگذاری مجدد)</option>
                            <option value="home" <?php selected(get_option('la_login_redirect', 'same'), 'home'); ?>>صفحه اصلی سایت</option>
                            <option value="myaccount" <?php selected(get_option('la_login_redirect', 'same'), 'myaccount'); ?>>صفحه حساب کاربری ووکامرس (در صورت وجود)</option>
                        </select>
                    </div>
                </section>

                <div class="la-admin-savebar"><div class="la-admin-savebar-hint"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> تغییرات فقط پس از ذخیره اعمال می‌شوند.</div><?php submit_button('ذخیره تغییرات', 'primary la-admin-save-button', 'submit', false); ?></div>
            </form>

            <aside class="la-admin-sidebar" id="la-guide" aria-label="راهنما و وضعیت افزونه">
                <section class="la-admin-sidecard la-admin-sidecard-status">
                    <div class="la-admin-sidecard-top"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><h2>وضعیت راه‌اندازی</h2></div>
                    <div class="la-admin-checkline <?php echo $username !== '' ? 'is-ok' : ''; ?>"><span class="dashicons <?php echo $username !== '' ? 'dashicons-yes' : 'dashicons-minus'; ?>" aria-hidden="true"></span> نام کاربری درگاه</div>
                    <div class="la-admin-checkline <?php echo $password !== '' ? 'is-ok' : ''; ?>"><span class="dashicons <?php echo $password !== '' ? 'dashicons-yes' : 'dashicons-minus'; ?>" aria-hidden="true"></span> رمز اتصال ذخیره‌شده</div>
                    <div class="la-admin-checkline <?php echo $bodyid > 0 ? 'is-ok' : ''; ?>"><span class="dashicons <?php echo $bodyid > 0 ? 'dashicons-yes' : 'dashicons-minus'; ?>" aria-hidden="true"></span> شناسه الگوی پیامکی</div>
                    <p class="la-admin-sidecard-foot">این وضعیت فقط تکمیل بودن تنظیمات را نشان می‌دهد، نه موفق بودن اتصال به API.</p>
                </section>
                <section class="la-admin-sidecard">
                    <div class="la-admin-sidecard-top"><span class="dashicons dashicons-shortcode" aria-hidden="true"></span><h2>شورت‌کدهای آماده</h2></div>
                    <p class="la-admin-sidecard-intro">برای درج فرم یا دکمه ورود در سایت، کد موردنظر را کپی کنید.</p>
                    <?php foreach ($shortcodes as $sc) : ?>
                        <div class="la-admin-shortcode">
                            <div class="la-admin-shortcode-line"><code dir="ltr"><?php echo esc_html($sc['code']); ?></code><button class="la-admin-copy" type="button" data-copy="<?php echo esc_attr($sc['code']); ?>" aria-label="کپی شورت‌کد">کپی</button></div>
                            <p><?php echo esc_html($sc['desc']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </section>
                <section class="la-admin-sidecard">
                    <div class="la-admin-sidecard-top"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><h2>باز کردن پاپ‌آپ</h2></div>
                    <p class="la-admin-sidecard-intro">یک لینک منو با آدرس <code dir="ltr">#light-auth</code> بسازید یا کلاس <code dir="ltr">la-open-modal</code> را به دکمه دلخواه اضافه کنید.</p>
                </section>
                <section class="la-admin-sidecard">
                    <div class="la-admin-sidecard-top"><span class="dashicons dashicons-editor-textcolor" aria-hidden="true"></span><h2>فونت رابط کاربری</h2></div>
                    <p class="la-admin-sidecard-intro"><?php echo $font_exists ? 'فونت محلی سفارشی شناسایی شد و برای بخش مدیریت و فرم استفاده می‌شود.' : 'در نبود فونت محلی، از فونت‌های سیستم استفاده می‌شود. می‌توانید فونت مجاز خود را با فرمت WOFF2 داخل پوشه fonts قرار دهید.'; ?></p>
                </section>
                <p class="la-admin-aside-footer">Light Auth OTP <span>·</span> طراحی سبک و بدون کتابخانه جانبی</p>
            </aside>
        </div>
    </div>
    <?php
}

/** Describe personal information processing in WordPress Privacy Policy Guide. */
function la_register_privacy_policy_content() {
    if (!function_exists('wp_add_privacy_policy_content')) return;
    $text = '<p>افزونه Light Auth برای ارسال کد ورود، شماره موبایل و کد یک‌بارمصرف را به سرویس ملی‌پیامک (payamak-panel.com) ارسال می‌کند. برای ثبت‌نام، نام و شماره موبایل در اطلاعات حساب وردپرس ذخیره می‌شوند. برای حفاظت از فرایند ورود، خلاصه رمزنگارانه کد تأیید و شمارنده‌های موقت محدودسازی درخواست در پایگاه داده وردپرس نگهداری می‌شوند و مدت اعتبار محدودی دارند. افزونه کد تأیید را به‌صورت متن ساده ذخیره نمی‌کند. افزونه انتخاب حالت روشن/تاریک را در localStorage مرورگر نگه می‌دارد. اطلاعات حساب کاربران تا حذف حساب باقی می‌ماند. جزئیات نگهداری داده‌ها توسط ارائه‌دهنده پیامک تابع شرایط آن سرویس است.</p>';
    wp_add_privacy_policy_content('Light Auth OTP', wp_kses_post($text));
}
add_action('admin_init', 'la_register_privacy_policy_content');

// ==========================================
// ۲. منطق هسته افزونه و احراز هویت
// ==========================================

function la_get_user_by_phone($phone) {
    $users = get_users(array(
        'meta_key'    => 'phone_number',
        'meta_value'  => $phone,
        'number'      => 2,
        'count_total' => false
    ));
    if (count($users) > 1) return new WP_Error('la_duplicate_phone', 'Duplicate verified phone numbers');
    return !empty($users) ? $users[0] : false;
}

// ---------- کمکی‌های نمایشی ----------

// آواتار حرفی (حرف اول نام) یا آیکون پیش‌فرض
function la_user_avatar_html($user, $class = 'la-user-avatar') {
    $name    = trim((string) $user->display_name);
    $initial = '';
    if ($name !== '' && !preg_match('/^[0-9+\s]+$/', $name)) {
        $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
    }
    if ($initial !== '') {
        return '<span class="' . esc_attr($class) . '" aria-hidden="true">' . esc_html($initial) . '</span>';
    }
    return '<span class="' . esc_attr($class) . '" aria-hidden="true"><svg viewBox="0 0 24 24" width="55%" height="55%" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>';
}

// لینک‌های حساب کاربر لاگین‌شده
function la_user_links() {
    $links = array();
    if (function_exists('wc_get_page_permalink')) {
        $links[] = array('url' => wc_get_page_permalink('myaccount'), 'label' => 'حساب کاربری من', 'icon' => 'user');
    }
    if (current_user_can('edit_posts')) {
        $links[] = array('url' => admin_url(), 'label' => 'پیشخوان', 'icon' => 'grid');
    }
    return $links;
}

function la_icon($name) {
    $icons = array(
        'user'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
        'login'  => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line>',
        'chevron'=> '<polyline points="6 9 12 15 18 9"></polyline>',
    );
    return '<svg class="la-ic" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
}

function la_get_form_html($is_modal = false) {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $phone        = get_user_meta($current_user->ID, 'phone_number', true);
        ob_start(); ?>
        <div class="la-box la-logged-in">
            <div class="la-topbar">
                <span class="la-topbar-spacer"></span>
                <div class="la-tools"><?php echo la_theme_toggle_html(); ?></div>
            </div>
            <div class="la-profile">
                <?php echo la_user_avatar_html($current_user); ?>
                <div class="la-profile-text">
                    <p class="la-welcome-text">سلام، <?php echo esc_html($current_user->display_name); ?></p>
                    <?php if ($phone) : ?>
                        <p class="la-welcome-phone" dir="ltr"><?php echo esc_html($phone); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="la-logged-actions">
                <?php foreach (la_user_links() as $link) : ?>
                    <a href="<?php echo esc_url($link['url']); ?>" class="la-btn la-btn-soft"><?php echo la_icon($link['icon']); ?><span><?php echo esc_html($link['label']); ?></span></a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="la-btn la-btn-outline"><?php echo la_icon('logout'); ?><span>خروج از حساب کاربری</span></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    $uid = function_exists('wp_unique_id') ? wp_unique_id('la-') : 'la-' . wp_rand(1000, 99999);

    ob_start(); ?>
    <div class="la-box <?php echo $is_modal ? 'la-in-modal' : ''; ?>" data-step="phone">
        <div class="la-topbar">
            <div class="la-steps" aria-hidden="true"><span></span><span></span></div>
            <div class="la-tools">
                <?php echo la_theme_toggle_html(); ?>
                <?php if ($is_modal) : ?>
                    <button type="button" class="la-close-modal" aria-label="بستن">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="la-header">
            <h3 class="la-title" id="<?php echo esc_attr($uid); ?>-title">ورود یا عضویت</h3>
            <p class="la-subtitle">برای ورود یا ثبت‌نام، شماره موبایل خود را وارد کنید</p>
        </div>

        <div class="la-message" role="status" aria-live="polite" hidden></div>

        <!-- مرحله ۱: دریافت شماره موبایل -->
        <form class="la-form la-step-phone active" novalidate>
            <div class="la-field">
                <label for="<?php echo esc_attr($uid); ?>-phone">شماره موبایل</label>
                <div class="la-input-wrap">
                    <input type="tel" id="<?php echo esc_attr($uid); ?>-phone" name="phone" class="la-phone-input" placeholder="09123456789" dir="ltr" inputmode="numeric" autocomplete="tel-national" aria-describedby="<?php echo esc_attr($uid); ?>-phone-err">
                    <svg class="la-valid-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <p class="la-field-error" id="<?php echo esc_attr($uid); ?>-phone-err" hidden></p>
            </div>
            <button type="submit" class="la-btn" data-loading-text="در حال ارسال پیامک…">
                <span class="la-spinner" aria-hidden="true"></span>
                <span class="la-btn-text">دریافت کد تایید</span>
            </button>
        </form>

        <!-- مرحله ۲: تایید کد پیامکی -->
        <form class="la-form la-step-verify" novalidate>
            <input type="hidden" name="phone" class="la-hidden-phone">

            <div class="la-phone-chip">
                <span class="la-phone-display" dir="ltr"></span>
                <button type="button" class="la-link la-edit-phone">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    <span>ویرایش</span>
                </button>
            </div>

            <div class="la-field la-fullname-wrap" hidden>
                <label for="<?php echo esc_attr($uid); ?>-name">نام و نام خانوادگی</label>
                <input type="text" id="<?php echo esc_attr($uid); ?>-name" name="fullname" class="la-fullname-input" placeholder="مثلاً: علی محمدی" autocomplete="name" enterkeyhint="next" aria-describedby="<?php echo esc_attr($uid); ?>-name-err">
                <p class="la-field-error" id="<?php echo esc_attr($uid); ?>-name-err" hidden></p>
            </div>

            <div class="la-field">
                <label for="<?php echo esc_attr($uid); ?>-otp">کد تأیید <?php echo esc_html(la_setting_int('la_otp_length', 6, 5, 6)); ?> رقمی</label>
                <input type="text" id="<?php echo esc_attr($uid); ?>-otp" name="otp" class="la-otp-input la-otp-field" placeholder="<?php echo esc_attr(str_repeat('•', la_setting_int('la_otp_length', 6, 5, 6))); ?>" dir="ltr" inputmode="numeric" autocomplete="one-time-code" enterkeyhint="done" aria-describedby="<?php echo esc_attr($uid); ?>-otp-err">
                <p class="la-field-error" id="<?php echo esc_attr($uid); ?>-otp-err" hidden></p>
            </div>

            <label class="la-remember-label"><input type="checkbox" class="la-remember" name="remember_me"><span>مرا به خاطر بسپار</span></label>
            <button type="submit" class="la-btn" data-loading-text="در حال بررسی…">
                <span class="la-spinner" aria-hidden="true"></span>
                <span class="la-btn-text">ورود به حساب</span>
            </button>

            <div class="la-footer-links">
                <span class="la-timer">
                    <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15 14"></polyline></svg>
                    <span>ارسال مجدد کد تا <b class="la-timer-value" dir="ltr">01:00</b></span>
                </span>
                <button type="button" class="la-link la-resend" data-loading-text="در حال ارسال…" hidden>
                    <span class="la-spinner" aria-hidden="true"></span>
                    <span class="la-btn-text">ارسال مجدد کد</span>
                </button>
            </div>
        </form>

        <!-- حالت موفقیت -->
        <div class="la-success" hidden>
            <div class="la-success-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="34" height="34" stroke="currentColor" stroke-width="2.6" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <p class="la-success-title">خوش آمدید!</p>
            <p class="la-success-text"></p>
            <div class="la-progress" aria-hidden="true"><span></span></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function la_auth_shortcode() {
    return la_get_form_html(false);
}
add_shortcode('light_auth', 'la_auth_shortcode');

function la_auth_btn_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'ورود | عضویت'
    ), $atts);

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $phone        = get_user_meta($current_user->ID, 'phone_number', true);
        ob_start(); ?>
        <div class="la-user-menu">
            <button type="button" class="la-trigger-btn la-logged-btn" aria-haspopup="true" aria-expanded="false">
                <?php echo la_user_avatar_html($current_user, 'la-mini-avatar'); ?>
                <span class="la-logged-name"><?php echo esc_html($current_user->display_name); ?></span>
                <span class="la-chevron"><?php echo la_icon('chevron'); ?></span>
            </button>
            <div class="la-user-dropdown" role="menu">
                <div class="la-user-dropdown-head">
                    <strong><?php echo esc_html($current_user->display_name); ?></strong>
                    <?php if ($phone) : ?><span dir="ltr"><?php echo esc_html($phone); ?></span><?php endif; ?>
                </div>
                <?php foreach (la_user_links() as $link) : ?>
                    <a href="<?php echo esc_url($link['url']); ?>" class="la-menu-item" role="menuitem"><?php echo la_icon($link['icon']); ?><span><?php echo esc_html($link['label']); ?></span></a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="la-menu-item la-menu-item-danger" role="menuitem"><?php echo la_icon('logout'); ?><span>خروج از حساب</span></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    return '<button type="button" class="la-trigger-btn la-open-modal" aria-haspopup="dialog">
                ' . la_icon('login') . '
                <span>' . esc_html($atts['text']) . '</span>
            </button>';
}
add_shortcode('light_auth_btn', 'la_auth_btn_shortcode');

function la_render_modal_in_footer() {
    if (is_user_logged_in()) return;
    ?>
    <div id="la-modal-overlay" class="la-modal-overlay" aria-hidden="true">
        <div class="la-modal-container" role="dialog" aria-modal="true" aria-label="ورود | عضویت">
            <?php echo la_get_form_html(true); ?>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'la_render_modal_in_footer');

// ---------- امنیت OTP: کلیدهای غیرقابل حدس، محدودیت درخواست و قفل کوتاه ----------
function la_secret_key($purpose, $identifier) {
    return 'la_' . $purpose . '_' . substr(hash_hmac('sha256', (string) $identifier, wp_salt('auth')), 0, 40);
}

function la_client_ip() {
    // Never trust spoofable forwarding headers for rate limits.
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
}

function la_normalize_phone($raw) {
    $raw = strtr((string) $raw, array(
        '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9',
        '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9'
    ));
    $number = preg_replace('/[^0-9]/', '', $raw);
    if (strpos($number, '0098') === 0) $number = '0' . substr($number, 4);
    elseif (strlen($number) === 12 && strpos($number, '98') === 0) $number = '0' . substr($number, 2);
    elseif (strlen($number) === 10 && $number[0] === '9') $number = '0' . $number;
    return preg_match('/^09[0-9]{9}$/D', $number) ? $number : '';
}

function la_ajax_field($key) {
    if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) return '';
    return sanitize_text_field(wp_unslash($_POST[$key]));
}

function la_json_error($message, $code = 'error', $retry_after = 0) {
    $data = array('message' => $message, 'code' => $code);
    if ($retry_after > 0) $data['retry_after'] = (int) $retry_after;
    wp_send_json_error($data);
}

/** Returns seconds to wait, or zero. Fixed windows limit phone/IP SMS costs. */
function la_consume_quota($bucket, $maximum, $window) {
    // Serialise quota updates, including parallel requests using different phones.
    $mutex = la_phone_mutex('quota:' . $bucket);
    if (!$mutex) return 2;
    $key = la_secret_key('quota', $bucket);
    $now = time();
    $record = get_transient($key);
    if (!is_array($record) || empty($record['until']) || $record['until'] <= $now) {
        $record = array('count' => 0, 'until' => $now + $window);
    }
    if ($record['count'] >= $maximum) {
        $wait = max(1, $record['until'] - $now);
    } else {
        $record['count']++;
        set_transient($key, $record, max(1, $record['until'] - $now));
        $wait = 0;
    }
    delete_option($mutex);
    return $wait;
}

/** Database-backed mutex: avoids two simultaneous requests issuing or consuming the same OTP. */
function la_phone_mutex($phone) {
    $key = la_secret_key('mutex', $phone);
    $now = time();
    $existing = (int) get_option($key, 0);
    if ($existing && $existing < $now - 30) delete_option($key);
    return add_option($key, $now, '', 'no') ? $key : false;
}

function la_reject_privileged_user($user) {
    // Passwordless SMS is never sufficient to take over high-privilege accounts.
    return user_can($user, 'manage_options') || user_can($user, 'edit_users') || user_can($user, 'activate_plugins') || user_can($user, 'manage_woocommerce') || user_can($user, 'edit_shop_orders') || user_can($user, 'promote_users');
}

/** Fresh anti-CSRF nonce. Never embed an expiring nonce in publicly cached markup. */
function la_get_auth_nonce() {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error(array('message' => 'Invalid method'), 405);
    }
    nocache_headers();
    wp_send_json_success(array('nonce' => wp_create_nonce('la_auth_nonce')));
}
add_action('wp_ajax_nopriv_la_get_nonce', 'la_get_auth_nonce');
add_action('wp_ajax_la_get_nonce', 'la_get_auth_nonce');

// Public endpoint: sends only a generic confirmation. Never reveals account existence.
function la_send_otp() {
    check_ajax_referer('la_auth_nonce', 'security');
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') la_json_error('درخواست نامعتبر است.');
    $phone = la_normalize_phone(la_ajax_field('phone'));
    if (!$phone) la_json_error('لطفاً شماره موبایل معتبر وارد کنید.', 'invalid_phone');

    $mutex = la_phone_mutex($phone);
    if (!$mutex) la_json_error('درخواست دیگری در حال پردازش است. کمی بعد دوباره تلاش کنید.', 'busy', 10);

    $result = array('success' => false, 'message' => 'ارسال پیامک انجام نشد. لطفاً بعداً تلاش کنید.', 'code' => 'send_error');
    $now = time();
    $cooldown_key = la_secret_key('cooldown', $phone);
    $locked_until = (int) get_transient($cooldown_key);
    $cooldown = la_setting_int('la_otp_cooldown', 60, 45, 180);

    if ($locked_until > $now) {
        $result = array('success' => false, 'message' => 'برای ارسال مجدد کد کمی صبر کنید.', 'code' => 'rate_limited', 'retry_after' => $locked_until - $now);
    } else {
        $ip = la_client_ip();
        // Some IPs are shared; phone limits are the primary control.
        $limits = array(
            array('send_ip_hour:' . $ip, 20, HOUR_IN_SECONDS),
            array('send_ip_day:' . $ip, 60, DAY_IN_SECONDS),
            array('send_phone_hour:' . $phone, 5, HOUR_IN_SECONDS),
            array('send_phone_day:' . $phone, 12, DAY_IN_SECONDS),
        );
        $wait = 0;
        foreach ($limits as $limit) {
            $wait = la_consume_quota($limit[0], $limit[1], $limit[2]);
            if ($wait) break;
        }
        if ($wait) {
            $result = array('success' => false, 'message' => 'تعداد درخواست‌های مجاز تمام شده است. بعداً دوباره تلاش کنید.', 'code' => 'rate_limited', 'retry_after' => $wait);
        } else {
            $length = la_setting_int('la_otp_length', 6, 5, 6);
            $otp = (string) random_int((int) pow(10, $length - 1), (int) pow(10, $length) - 1);
            $sms = la_send_sms_api($phone, $otp);
            if ($sms['success']) {
                $nonce = bin2hex(random_bytes(16));
                $expiry = la_setting_int('la_otp_expiry', 3, 2, 10) * MINUTE_IN_SECONDS;
                set_transient(la_secret_key('code', $phone), array(
                    'nonce' => $nonce,
                    'digest' => hash_hmac('sha256', $nonce . ':' . $phone . ':' . $otp, wp_salt('auth')),
                    'attempts' => 0,
                    'expires' => $now + $expiry,
                ), $expiry);
                set_transient($cooldown_key, $now + $cooldown, $cooldown);
                $result = array('success' => true, 'message' => 'کد تأیید ارسال شد.', 'resend_after' => $cooldown, 'expires_in' => $expiry);
            }
        }
    }
    delete_option($mutex);
    if ($result['success']) wp_send_json_success($result);
    wp_send_json_error($result);
}
add_action('wp_ajax_nopriv_la_send_otp', 'la_send_otp');

// Validate and consume a challenge. For new accounts, only request a name AFTER OTP verification.
function la_verify_otp() {
    check_ajax_referer('la_auth_nonce', 'security');
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') la_json_error('درخواست نامعتبر است.');
    $phone = la_normalize_phone(la_ajax_field('phone'));
    $otp = la_ajax_field('otp');
    $otp = strtr($otp, array('۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'));
    $fullname = trim(la_ajax_field('fullname'));
    $length = la_setting_int('la_otp_length', 6, 5, 6);
    if (!$phone || !preg_match('/^[0-9]{' . $length . '}$/D', $otp)) {
        la_json_error('شماره موبایل یا کد تأیید معتبر نیست.', 'invalid_code');
    }
    if (la_consume_quota('verify_ip:' . la_client_ip(), 45, HOUR_IN_SECONDS)) {
        la_json_error('تلاش‌های زیادی انجام شده است. بعداً دوباره تلاش کنید.', 'rate_limited');
    }

    $mutex = la_phone_mutex($phone);
    if (!$mutex) la_json_error('درخواست دیگری در حال پردازش است. دوباره تلاش کنید.', 'busy', 2);
    $otp_key = la_secret_key('code', $phone);
    $stored = get_transient($otp_key);
    $result = array('success' => false, 'message' => 'کد تأیید اشتباه است یا اعتبار آن به پایان رسیده است.', 'code' => 'invalid_code');
    $login_user = false;

    if (is_array($stored) && isset($stored['digest'], $stored['nonce'], $stored['attempts'], $stored['expires']) && $stored['expires'] > time()) {
        $expected = hash_hmac('sha256', $stored['nonce'] . ':' . $phone . ':' . $otp, wp_salt('auth'));
        if (!hash_equals($stored['digest'], $expected)) {
            $stored['attempts']++;
            if ($stored['attempts'] >= la_setting_int('la_otp_attempts', 5, 3, 8)) {
                delete_transient($otp_key);
                $result = array('success' => false, 'message' => 'تعداد تلاش‌های اشتباه بیش از حد مجاز بود. کد جدید بگیرید.', 'code' => 'locked');
            } else {
                set_transient($otp_key, $stored, max(1, $stored['expires'] - time()));
            }
        } else {
            $user = la_get_user_by_phone($phone);
            if (is_wp_error($user)) {
                $result = array('success' => false, 'message' => 'امکان ورود با این شماره وجود ندارد. با پشتیبانی سایت تماس بگیرید.', 'code' => 'account_conflict');
            } elseif ($user && la_reject_privileged_user($user)) {
                delete_transient($otp_key);
                $result = array('success' => false, 'message' => 'برای این حساب، از روش ورود معمول وردپرس استفاده کنید.', 'code' => 'privileged_account');
            } elseif (!$user && $fullname === '') {
                $result = array('success' => true, 'needs_name' => true, 'message' => 'شماره تأیید شد. برای تکمیل ثبت‌نام نام خود را وارد کنید.');
            } else {
                if (!$user) {
                    if (function_exists('mb_strlen') ? mb_strlen($fullname, 'UTF-8') > 80 : strlen($fullname) > 240) {
                        $result = array('success' => false, 'message' => 'نام واردشده بیش از حد طولانی است.', 'code' => 'invalid_name');
                    } else {
                        $base = $phone;
                        // Never attach a phone to a pre-existing unverified username.
                        $username = username_exists($base) ? 'la_' . $phone . '_' . wp_generate_password(5, false, false) : $base;
                        $role = function_exists('wc_get_page_permalink') && get_role('customer') ? 'customer' : 'subscriber';
                        $user_id = wp_insert_user(array(
                            'user_login'   => $username,
                            'user_pass'    => wp_generate_password(32, true, true),
                            'first_name'   => $fullname,
                            'display_name' => $fullname,
                            'role'         => $role,
                        ));
                        if (is_wp_error($user_id)) {
                            $result = array('success' => false, 'message' => 'ساخت حساب کاربری انجام نشد. دوباره تلاش کنید.', 'code' => 'registration_failed');
                        } else {
                            update_user_meta($user_id, 'phone_number', $phone);
                            update_user_meta($user_id, 'billing_phone', $phone);
                            update_user_meta($user_id, 'la_phone_verified_at', time());
                            $user = get_user_by('id', $user_id);
                        }
                    }
                }
                if ($user && !is_wp_error($user)) {
                    delete_transient($otp_key); // one-time use
                    $login_user = $user;
                    $result = array('success' => true, 'message' => 'احراز هویت موفق! در حال ورود به سایت...');
                }
            }
        }
    }
    delete_option($mutex);
    if ($login_user) {
        wp_set_current_user($login_user->ID);
        wp_set_auth_cookie($login_user->ID, la_ajax_field('remember_me') === '1', is_ssl());
        do_action('wp_login', $login_user->user_login, $login_user);
    }
    if ($result['success']) wp_send_json_success($result);
    wp_send_json_error($result);
}
add_action('wp_ajax_nopriv_la_verify_otp', 'la_verify_otp');

function la_send_sms_api($phone, $code) {
    $username = get_option('la_sms_username', '');
    $password = get_option('la_sms_password', '');
    $bodyId   = absint(get_option('la_sms_bodyid', 0));
    if (!$username || !$password || !$bodyId) return array('success' => false);

    $response = wp_remote_post('https://api.payamak-panel.com/post/Send.asmx/SendByBaseNumber2', array(
        'body' => array('username' => $username, 'password' => $password, 'text' => (string) $code, 'to' => $phone, 'bodyId' => $bodyId),
        'timeout' => 15,
        'sslverify' => true,
        'redirection' => 0,
    ));
    if (is_wp_error($response)) return array('success' => false);
    $status = wp_remote_retrieve_response_code($response);
    if ($status !== 200) return array('success' => false);
    $receipt = trim(wp_strip_all_tags(wp_remote_retrieve_body($response)));
    return array('success' => preg_match('/^[0-9]{11,}$/D', $receipt) === 1 && (float) $receipt > 0);
}

// ستون شماره موبایل در کاربران پیشخوان
function la_add_phone_column($columns) {
    $columns['phone_number'] = 'شماره موبایل';
    return $columns;
}
add_filter('manage_users_columns', 'la_add_phone_column');

function la_show_phone_column_content($value, $column_name, $user_id) {
    if ('phone_number' === $column_name) {
        $phone = get_user_meta($user_id, 'phone_number', true);
        return $phone ? esc_html($phone) : '—';
    }
    return $value;
}
add_filter('manage_users_custom_column', 'la_show_phone_column_content', 10, 3);