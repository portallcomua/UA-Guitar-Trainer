<?php
/**
 * Plugin Name: UA Guitar Trainer
 * Description: Гітарний тренажер, PWA, рекламодавці. Пріоритет реальних семплів (SF2/FreePats), резервна KS-генерація.
 * Version: 3.1.0
 * Author: UA Tools
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

final class UA_Guitar_Trainer_3 {

    const OPTION = 'uag2_advertisers';
    const ICON_OPTION = 'uag2_app_icon';
    const NONCE = 'uag2_admin_nonce';
    const SAMPLES_DIR = 'uag2-samples';

    public function __construct() {
        add_shortcode('ua_guitar_trainer', array($this, 'shortcode'));

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_uag2_save_ad', array($this, 'save_ad'));
        add_action('admin_post_uag2_delete_ad', array($this, 'delete_ad'));
        add_action('admin_post_uag2_toggle_ad', array($this, 'toggle_ad'));

        add_action('wp_ajax_uag2_impression', array($this, 'impression'));
        add_action('wp_ajax_nopriv_uag2_impression', array($this, 'impression'));

        add_action('wp_ajax_uag2_save_icon', array($this, 'save_icon'));
        add_action('wp_ajax_nopriv_uag2_save_icon', function () {
            wp_send_json_error('Потрібна авторизація адміністратора.');
        });

        add_action('wp_ajax_uag2_samples', array($this, 'get_samples_manifest'));
        add_action('wp_ajax_nopriv_uag2_samples', array($this, 'get_samples_manifest'));

        add_action('init', array($this, 'pwa_endpoints'), 1);
        add_action('init', array($this, 'click_redirect'), 2);

        add_filter('cron_schedules', array($this, 'cron_schedules'));
        add_action('uag2_monthly_reports', array($this, 'monthly_reports'));
        add_action('uag2_expiry_check', array($this, 'expiry_check'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function activate() {
        if (!wp_next_scheduled('uag2_monthly_reports')) {
            wp_schedule_event(time() + 120, 'uag2_monthly', 'uag2_monthly_reports');
        }
        if (!wp_next_scheduled('uag2_expiry_check')) {
            wp_schedule_event(time() + 300, 'daily', 'uag2_expiry_check');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('uag2_monthly_reports');
        wp_clear_scheduled_hook('uag2_expiry_check');
    }

    public function cron_schedules($schedules) {
        $schedules['uag2_monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => 'UA Guitar Trainer monthly'
        );
        return $schedules;
    }

    private function ads() {
        $ads = get_option(self::OPTION, array());
        return is_array($ads) ? $ads : array();
    }

    private function save_ads($ads) {
        update_option(self::OPTION, $ads, false);
    }

    // ========== Адмін-панель ==========
    public function admin_menu() {
        add_menu_page('UA Guitar Trainer', 'Guitar Trainer', 'manage_options', 'uag2-admin', array($this, 'admin_page'), 'dashicons-format-audio', 58);
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $ads = $this->ads();
        $edit_id = isset($_GET['edit']) ? sanitize_text_field(wp_unslash($_GET['edit'])) : '';
        $editing = isset($ads[$edit_id]) ? $ads[$edit_id] : array();
        $month = current_time('Y-m');
        ?>
        <div class="wrap">
            <h1>🎸 UA Guitar Trainer — рекламодавці</h1>
            <p>Керування рекламними розміщеннями, періодами, статистикою та звітами.</p>
            <hr>
            <h2><?php echo $editing ? 'Редагувати' : 'Додати'; ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="uag2_save_ad">
                <input type="hidden" name="ad_id" value="<?php echo esc_attr($edit_id); ?>">
                <?php wp_nonce_field(self::NONCE, 'uag2_nonce'); ?>
                <table class="form-table">
                    <tr><th>Назва</th><td><input required class="regular-text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>"></td></tr>
                    <tr><th>Email</th><td><input type="email" class="regular-text" name="email" value="<?php echo esc_attr($editing['email'] ?? ''); ?>"></td></tr>
                    <tr><th>Контакти</th><td><textarea class="large-text" rows="2" name="contacts"><?php echo esc_textarea($editing['contacts'] ?? ''); ?></textarea></td></tr>
                    <tr><th>HTML</th><td><textarea class="large-text code" rows="7" name="html"><?php echo esc_textarea($editing['html'] ?? ''); ?></textarea></td></tr>
                    <tr><th>Зображення URL</th><td><input class="regular-text" type="url" name="image" value="<?php echo esc_attr($editing['image'] ?? ''); ?>"></td></tr>
                    <tr><th>Посилання</th><td><input class="regular-text" type="url" name="link" value="<?php echo esc_attr($editing['link'] ?? ''); ?>"></td></tr>
                    <tr><th>Вартість (грн)</th><td><input type="number" min="0" step="0.01" name="price" value="<?php echo esc_attr($editing['price'] ?? ''); ?>"></td></tr>
                    <tr><th>Початок</th><td><input type="date" name="start" value="<?php echo esc_attr($editing['start'] ?? wp_date('Y-m-d')); ?>"></td></tr>
                    <tr><th>Кінець</th><td><input type="date" name="end" value="<?php echo esc_attr($editing['end'] ?? ''); ?>"></td></tr>
                    <tr><th>Активний</th><td><label><input type="checkbox" name="active" value="1" <?php checked(($editing['active'] ?? 1), 1); ?>> Так</label></td></tr>
                </table>
                <?php submit_button($editing ? 'Зберегти' : 'Додати'); ?>
            </form>
            <hr>
            <h2>Статистика</h2>
            <table class="widefat striped">
                <thead><tr><th>Назва</th><th>Email</th><th>Період</th><th>Ціна</th><th>Покази (міс)</th><th>Кліки (міс)</th><th>Всього показів</th><th>Всього кліків</th><th>Статус</th><th>Дії</th></tr></thead>
                <tbody>
                <?php if (!$ads): ?><tr><td colspan="10">Порожньо.</td></tr><?php endif; ?>
                <?php foreach ($ads as $id => $ad):
                    $stats = $ad['stats'] ?? array();
                    $monthly = $stats[$month] ?? array('views'=>0,'clicks'=>0);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($ad['name']); ?></strong></td>
                        <td><?php echo esc_html($ad['email']); ?></td>
                        <td><?php echo esc_html($ad['start'] . ' — ' . $ad['end']); ?></td>
                        <td><?php echo esc_html(number_format_i18n((float)$ad['price'], 2)); ?> грн</td>
                        <td><?php echo esc_html($monthly['views'] ?? 0); ?></td>
                        <td><?php echo esc_html($monthly['clicks'] ?? 0); ?></td>
                        <td><?php echo esc_html((int)($ad['total_views'] ?? 0)); ?></td>
                        <td><?php echo esc_html((int)($ad['total_clicks'] ?? 0)); ?></td>
                        <td><?php echo !empty($ad['active']) ? '<span style="color:green">Активний</span>' : '<span style="color:#b00">Зупинений</span>'; ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=uag2-admin&edit=' . rawurlencode($id))); ?>">Редагувати</a> |
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=uag2_toggle_ad&ad_id=' . rawurlencode($id)), self::NONCE)); ?>"><?php echo !empty($ad['active']) ? 'Зупинити' : 'Запустити'; ?></a> |
                            <a style="color:#b00" onclick="return confirm('Видалити?')" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=uag2_delete_ad&ad_id=' . rawurlencode($id)), self::NONCE)); ?>">Видалити</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <hr>
            <h2>PWA та іконка</h2>
            <?php $icon = get_option(self::ICON_OPTION); if ($icon) echo '<p><img src="' . esc_url($icon) . '" width="96" height="96" style="border-radius:16px"></p>'; ?>
            <p>WP-Cron має відвідуватися для звітів.</p>
        </div>
        <?php
    }

    public function save_ad() {
        if (!current_user_can('manage_options')) wp_die('Доступ заборонено.');
        check_admin_referer(self::NONCE, 'uag2_nonce');
        $ads = $this->ads();
        $id = sanitize_text_field(wp_unslash($_POST['ad_id'] ?? ''));
        if (!$id || !isset($ads[$id])) $id = 'ad_' . wp_generate_password(12, false, false);
        $ads[$id] = array(
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'contacts' => sanitize_textarea_field(wp_unslash($_POST['contacts'] ?? '')),
            'html' => wp_kses_post(wp_unslash($_POST['html'] ?? '')),
            'image' => esc_url_raw(wp_unslash($_POST['image'] ?? '')),
            'link' => esc_url_raw(wp_unslash($_POST['link'] ?? '')),
            'price' => max(0, (float)($_POST['price'] ?? 0)),
            'start' => sanitize_text_field(wp_unslash($_POST['start'] ?? '')),
            'end' => sanitize_text_field(wp_unslash($_POST['end'] ?? '')),
            'active' => isset($_POST['active']) ? 1 : 0,
            'stats' => $ads[$id]['stats'] ?? array(),
            'total_views' => (int)($ads[$id]['total_views'] ?? 0),
            'total_clicks' => (int)($ads[$id]['total_clicks'] ?? 0),
            'expiry_notice_sent' => $ads[$id]['expiry_notice_sent'] ?? ''
        );
        $this->save_ads($ads);
        wp_safe_redirect(admin_url('admin.php?page=uag2-admin&saved=1'));
        exit;
    }

    public function delete_ad() {
        if (!current_user_can('manage_options')) wp_die('Доступ заборонено.');
        check_admin_referer(self::NONCE);
        $id = sanitize_text_field(wp_unslash($_GET['ad_id'] ?? ''));
        $ads = $this->ads();
        unset($ads[$id]);
        $this->save_ads($ads);
        wp_safe_redirect(admin_url('admin.php?page=uag2-admin'));
        exit;
    }

    public function toggle_ad() {
        if (!current_user_can('manage_options')) wp_die('Доступ заборонено.');
        check_admin_referer(self::NONCE);
        $id = sanitize_text_field(wp_unslash($_GET['ad_id'] ?? ''));
        $ads = $this->ads();
        if (isset($ads[$id])) {
            $ads[$id]['active'] = empty($ads[$id]['active']) ? 1 : 0;
            $this->save_ads($ads);
        }
        wp_safe_redirect(admin_url('admin.php?page=uag2-admin'));
        exit;
    }

    private function is_ad_active($ad) {
        if (empty($ad['active'])) return false;
        $today = current_time('Y-m-d');
        if (!empty($ad['start']) && $today < $ad['start']) return false;
        if (!empty($ad['end']) && $today > $ad['end']) return false;
        return true;
    }

    private function active_ad() {
        foreach ($this->ads() as $id => $ad) {
            if ($this->is_ad_active($ad)) return array('id'=>$id, 'ad'=>$ad);
        }
        return false;
    }

    private function click_url($id) {
        return add_query_arg('uag2_click', rawurlencode($id), home_url('/'));
    }

    public function click_redirect() {
        if (empty($_GET['uag2_click'])) return;
        $id = sanitize_text_field(wp_unslash($_GET['uag2_click']));
        $ads = $this->ads();
        if (!isset($ads[$id]) || !$this->is_ad_active($ads[$id])) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
        $month = current_time('Y-m');
        if (!isset($ads[$id]['stats'][$month])) $ads[$id]['stats'][$month] = array('views'=>0,'clicks'=>0);
        $ads[$id]['stats'][$month]['clicks']++;
        $ads[$id]['total_clicks'] = (int)($ads[$id]['total_clicks'] ?? 0) + 1;
        $this->save_ads($ads);
        $url = $ads[$id]['link'] ?? '';
        if (!$url && !empty($ads[$id]['image'])) $url = $ads[$id]['image'];
        if (!$url) $url = home_url('/');
        wp_redirect(esc_url_raw($url));
        exit;
    }

    public function impression() {
        check_ajax_referer('uag2_front', 'nonce');
        $id = sanitize_text_field(wp_unslash($_POST['ad_id'] ?? ''));
        $ads = $this->ads();
        if (!isset($ads[$id]) || !$this->is_ad_active($ads[$id])) wp_send_json_error();
        $month = current_time('Y-m');
        if (!isset($ads[$id]['stats'][$month])) $ads[$id]['stats'][$month] = array('views'=>0,'clicks'=>0);
        $ads[$id]['stats'][$month]['views']++;
        $ads[$id]['total_views'] = (int)($ads[$id]['total_views'] ?? 0) + 1;
        $this->save_ads($ads);
        wp_send_json_success();
    }

    public function monthly_reports() {
        $ads = $this->ads();
        $month = wp_date('Y-m', strtotime('first day of last month', current_time('timestamp')));
        $label = wp_date('m.Y', strtotime($month . '-01'));
        foreach ($ads as $id => $ad) {
            if (empty($ad['email']) || !is_email($ad['email'])) continue;
            $stats = $ad['stats'][$month] ?? array('views'=>0,'clicks'=>0);
            $subject = 'Звіт — ' . get_bloginfo('name') . ' — ' . $label;
            $body = "Вітаємо, {$ad['name']}!\n\nЗвіт за {$label}\nПокази: " . (int)$stats['views'] . "\nКліки: " . (int)$stats['clicks'] . "\nВартість: " . (float)$ad['price'] . " грн\nПеріод: {$ad['start']} — {$ad['end']}\n\nСайт: " . home_url('/');
            wp_mail($ad['email'], $subject, $body);
        }
    }

    public function expiry_check() {
        $ads = $this->ads();
        $today = current_time('Y-m-d');
        $changed = false;
        foreach ($ads as $id => $ad) {
            if (empty($ad['end']) || empty($ad['email']) || !is_email($ad['email'])) continue;
            $end = strtotime($ad['end'] . ' 00:00:00');
            $now = strtotime($today . ' 00:00:00');
            $days = (int)floor(($end - $now) / DAY_IN_SECONDS);
            if ($days === 7 && ($ad['expiry_notice_sent'] ?? '') !== $ad['end']) {
                wp_mail($ad['email'], 'Нагадування: завершення розміщення', "Вітаємо, {$ad['name']}!\n\nРозміщення завершується через 7 днів.\nДата: {$ad['end']}\n\n" . home_url('/'));
                $ads[$id]['expiry_notice_sent'] = $ad['end'];
                $changed = true;
            }
        }
        if ($changed) $this->save_ads($ads);
    }

    public function save_icon() {
        if (!current_user_can('manage_options')) wp_send_json_error('Потрібні права адміністратора.', 403);
        check_ajax_referer('uag2_icon', 'nonce');
        $data = wp_unslash($_POST['image'] ?? '');
        if (!preg_match('#^data:image/png;base64,#', $data)) wp_send_json_error('Неправильний формат.');
        $raw = base64_decode(substr($data, strpos($data, ',') + 1), true);
        if (!$raw || strlen($raw) > 500000) wp_send_json_error('Файл завеликий.');
        $upload = wp_upload_bits('ua-guitar-trainer-icon.png', null, $raw);
        if (!empty($upload['error'])) wp_send_json_error($upload['error']);
        update_option(self::ICON_OPTION, esc_url_raw($upload['url']));
        wp_send_json_success(array('url'=>$upload['url']));
    }

    // ========== СЕМПЛИ: SF2-вилучення → реальні файли → KS-генерація ==========

    private function get_sample_url($freq, $instrument) {
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/' . self::SAMPLES_DIR;
        $url = $upload_dir['baseurl'] . '/' . self::SAMPLES_DIR;
        if (!file_exists($dir)) wp_mkdir_p($dir);

        $key = $instrument . '_' . number_format($freq, 2, '.', '');
        $file = $dir . '/' . $key . '.wav';
        $file_url = $url . '/' . $key . '.wav';

        // Пріоритет 0: спроба автоматичного вилучення з SF2 (лише nylon, лише один раз)
        if ($instrument === 'nylon') {
            $this->try_extract_from_sf2();
        }

        // Пріоритет 1: існуючий реальний семпл
        if (file_exists($file) && filesize($file) > 1000) {
            return $file_url;
        }

        // Пріоритет 2: KS-генерація
        $generated = $this->generate_wav_sample($freq, $instrument);
        return $generated ? $generated : false;
    }

    /**
     * Спроба автоматичного вилучення з SF2 (nylon.sf2) через sf2extract.
     * Потребує: gigtools на сервері + файл nylon.sf2 у директорії семплів.
     */
    private function try_extract_from_sf2() {
        $upload_dir = wp_upload_dir();
        $sf2_dir = $upload_dir['basedir'] . '/' . self::SAMPLES_DIR;
        $sf2_file = $sf2_dir . '/nylon.sf2';

        if (!file_exists($sf2_file) || filesize($sf2_file) < 10000) {
            return false;
        }

        $sf2extract_bin = $this->find_sf2extract();
        if (!$sf2extract_bin) {
            return false;
        }

        $marker = $sf2_dir . '/.extracted';
        if (file_exists($marker)) {
            return true;
        }

        $tmp_dir = $sf2_dir . '/tmp_extract_' . uniqid();
        if (!wp_mkdir_p($tmp_dir)) {
            return false;
        }

        $cmd = escapeshellarg($sf2extract_bin) . ' ' . escapeshellarg($sf2_file) . ' ' . escapeshellarg($tmp_dir) . ' 2>&1';
        $output = array();
        $return_code = 0;
        @exec($cmd, $output, $return_code);

        if ($return_code !== 0) {
            $this->rrmdir($tmp_dir);
            return false;
        }

        $wav_files = glob($tmp_dir . '/*.wav');
        if (empty($wav_files)) {
            $this->rrmdir($tmp_dir);
            return false;
        }

        // Наближене зіставлення назв файлів з 6 базовими нотами
        // Увага: назви семплів у SF2 можуть відрізнятися; якщо зіставлення не вдалося — повертаємось до генерації
        $note_map = array(
            '82.41'  => array('E2', 'E1'),
            '110.00' => array('A2', 'A1'),
            '146.83' => array('D3', 'D2'),
            '196.00' => array('G3', 'G2'),
            '246.94' => array('B3', 'B2'),
            '329.63' => array('E4', 'E3'),
        );

        $matched = 0;
        foreach ($wav_files as $wav) {
            $basename = strtolower(pathinfo($wav, PATHINFO_FILENAME));
            foreach ($note_map as $freq => $note_names) {
                foreach ($note_names as $note_name) {
                    if (strpos($basename, strtolower($note_name)) !== false) {
                        $dest = $sf2_dir . '/nylon_' . $freq . '.wav';
                        if (!file_exists($dest)) {
                            @copy($wav, $dest);
                            $matched++;
                        }
                        break 2;
                    }
                }
            }
        }

        $this->rrmdir($tmp_dir);

        if ($matched >= 3) {
            @file_put_contents($marker, date('Y-m-d H:i:s'));
            return true;
        }
        return false;
    }

    private function find_sf2extract() {
        $paths = array('/usr/bin/sf2extract', '/usr/local/bin/sf2extract', '/opt/bin/sf2extract');
        foreach ($paths as $p) {
            if (is_executable($p)) return $p;
        }
        $output = array();
        $rc = 0;
        @exec('which sf2extract 2>/dev/null', $output, $rc);
        if ($rc === 0 && !empty($output[0]) && is_executable(trim($output[0]))) {
            return trim($output[0]);
        }
        return false;
    }

    private function rrmdir($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) $this->rrmdir($path);
            else @unlink($path);
        }
        @rmdir($dir);
    }

    private function generate_wav_sample($freq, $instrument) {
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/' . self::SAMPLES_DIR;
        $url = $upload_dir['baseurl'] . '/' . self::SAMPLES_DIR;
        if (!file_exists($dir)) wp_mkdir_p($dir);

        $key = $instrument . '_' . number_format($freq, 2, '.', '');
        $file = $dir . '/' . $key . '.wav';
        $file_url = $url . '/' . $key . '.wav';

        if (file_exists($file) && filesize($file) > 1000) return $file_url;

        $presets = array(
            'acoustic' => array('decay' => 0.99965, 'lp' => 0.48, 'pickup' => 0.20, 'dur' => 2.2),
            'nylon'    => array('decay' => 0.99935, 'lp' => 0.40, 'pickup' => 0.24, 'dur' => 2.4),
            'electric' => array('decay' => 0.99975, 'lp' => 0.60, 'pickup' => 0.14, 'dur' => 2.0),
        );
        $P = $presets[$instrument] ?? $presets['acoustic'];

        $sr = 44100;
        $totalLen = (int)($sr * $P['dur']);
        $period = $sr / $freq;
        $N = (int)floor($period);
        $frac = $period - $N;

        $ring = array_fill(0, $N + 1, 0.0);
        $pinkState = 0.0;
        mt_srand(crc32($key));
        for ($i = 0; $i < $N; $i++) {
            $w = (mt_rand() / mt_getrandmax()) * 2 - 1;
            $pinkState = $pinkState * 0.7 + $w * 0.3;
            $ring[$i] = $pinkState;
        }

        $pp = max(1, (int)floor($N * $P['pickup']));
        $ringInit = $ring;
        for ($i = 0; $i < $N; $i++) {
            $a = $ringInit[$i];
            $b = $ringInit[($i + $pp) % $N];
            $ring[$i] = ($a - $b) * 0.7;
        }

        $decayRate = pow($P['decay'], $sr / ($N * $sr / 440));
        $lp = $P['lp'];
        $out = array_fill(0, $totalLen, 0.0);
        $prevOut = 0.0;

        for ($i = 0; $i < $totalLen; $i++) {
            $idx = $i % $N;
            $cur = $ring[$idx];
            if ($frac > 0.01) {
                $val = $prevOut + $frac * ($cur - $prevOut);
                $prevOut = $cur;
            } else {
                $val = $cur;
            }
            $out[$i] = $val;
            $next = $ring[($idx + 1) % $N];
            $smoothed = ($cur + $next) * 0.5;
            $filtered = $smoothed + ($cur - $smoothed) * $lp;
            $ring[$idx] = $filtered * $decayRate;
        }

        $attackLen = min((int)($sr * 0.004), $totalLen);
        for ($i = 0; $i < $attackLen; $i++) $out[$i] *= $i / $attackLen;
        $releaseLen = min((int)($sr * 0.15), $totalLen);
        for ($i = 0; $i < $releaseLen; $i++) $out[$totalLen - 1 - $i] *= $i / $releaseLen;

        $peak = 0.0;
        for ($i = 0; $i < $totalLen; $i++) {
            $a = abs($out[$i]);
            if ($a > $peak) $peak = $a;
        }
        if ($peak > 0.001) {
            $norm = 0.5 / $peak;
            for ($i = 0; $i < $totalLen; $i++) $out[$i] *= $norm;
        }

        $bodyHP = ($instrument === 'electric') ? 90.0 : 70.0;
        $hpCoeff = exp(-2.0 * M_PI * $bodyHP / $sr);
        $hpState = 0.0;
        for ($i = 0; $i < $totalLen; $i++) {
            $hpState = $out[$i] * (1 - $hpCoeff) + $hpState * $hpCoeff;
            $out[$i] = $hpState;
        }

        $lpCutoff = ($instrument === 'electric') ? 4200.0 : 6500.0;
        $lpCoeff = exp(-2.0 * M_PI * $lpCutoff / $sr);
        $lpState = 0.0;
        for ($i = 0; $i < $totalLen; $i++) {
            $lpState = $out[$i] * (1 - $lpCoeff) + $lpState * $lpCoeff;
            $out[$i] = $lpState;
        }

        $wav = $this->build_wav($out, $sr);
        if (file_put_contents($file, $wav) === false) return false;
        return $file_url;
    }

    private function build_wav($samples, $sr) {
        $numSamples = count($samples);
        $dataSize = $numSamples * 2;
        $fileSize = 36 + $dataSize;
        $header = 'RIFF' . pack('V', $fileSize) . 'WAVE' . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) . pack('V', $sr) . pack('V', $sr * 2) . pack('v', 2) . pack('v', 16) . 'data' . pack('V', $dataSize);
        $data = '';
        for ($i = 0; $i < $numSamples; $i++) {
            $v = $samples[$i];
            if ($v > 1) $v = 1; if ($v < -1) $v = -1;
            $int = (int)round($v * 32767);
            if ($int < -32768) $int = -32768;
            if ($int > 32767) $int = 32767;
            $data .= pack('v', $int & 0xFFFF);
        }
        return $header . $data;
    }

    public function get_samples_manifest() {
        $notes = array(82.41, 110.00, 146.83, 196.00, 246.94, 329.63);
        $insts = array('acoustic', 'nylon', 'electric');
        $manifest = array();
        foreach ($insts as $inst) {
            $manifest[$inst] = array();
            foreach ($notes as $freq) {
                $url = $this->get_sample_url($freq, $inst);
                if ($url) $manifest[$inst][] = array('freq' => $freq, 'url' => $url);
            }
        }
        wp_send_json_success($manifest);
    }

    // ========== PWA ==========
    public function pwa_endpoints() {
        if (isset($_GET['uag2_manifest'])) {
            $icon = get_option(self::ICON_OPTION);
            if (!$icon) $icon = includes_url('images/w-logo-blue-white.png');
            $manifest = array(
                'name' => 'UA Guitar Trainer', 'short_name' => 'Guitar', 'description' => 'Тренажер гітари',
                'start_url' => home_url('/'), 'scope' => home_url('/'), 'display' => 'standalone',
                'background_color' => '#10294b', 'theme_color' => '#164b91',
                'icons' => array(
                    array('src'=>$icon,'sizes'=>'192x192','type'=>'image/png'),
                    array('src'=>$icon,'sizes'=>'512x512','type'=>'image/png')
                )
            );
            nocache_headers(); header('Content-Type: application/manifest+json; charset=utf-8');
            echo wp_json_encode($manifest); exit;
        }
        if (isset($_GET['uag2_sw'])) {
            nocache_headers(); header('Content-Type: application/javascript; charset=utf-8');
            header('Service-Worker-Allowed: /');
            $home = wp_json_encode(home_url('/'));
            echo "const CACHE='ua-guitar-trainer-v3';const HOME={$home};self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE).then(c=>c.add(HOME)).catch(()=>{}));self.skipWaiting();});self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))));self.clients.claim();});self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;e.respondWith(fetch(e.request).then(r=>{if(r&&r.status===200&&new URL(e.request.url).origin===location.origin){const copy=r.clone();caches.open(CACHE).then(c=>c.put(e.request,copy));}return r;}).catch(()=>caches.match(e.request).then(r=>r||caches.match(HOME))));});";
            exit;
        }
    }

    // ========== Шорткод (HTML + JS) ==========
    public function shortcode() {
        static $n=0; $n++;
        $id='uag2-'.$n.'-'.wp_rand(100,9999);
        $ad=$this->active_ad();
        ob_start();
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="uag2">
            <div class="uag2-ad-area">
                <?php if ($ad): $ad_id=$ad['id']; $data=$ad['ad']; ?>
                    <div class="uag2-ad">
                        <div class="uag2-ad-label">РЕКЛАМА</div>
                        <?php if (!empty($data['html'])): ?><div class="uag2-ad-html"><?php echo wp_kses_post($data['html']); ?></div>
                        <?php elseif (!empty($data['image'])): ?><a href="<?php echo esc_url($this->click_url($ad_id)); ?>" target="_blank" rel="sponsored noopener"><img src="<?php echo esc_url($data['image']); ?>" alt=""></a>
                        <?php else: ?><a href="<?php echo esc_url($this->click_url($ad_id)); ?>" target="_blank" rel="sponsored noopener"><?php echo esc_html($data['name']); ?></a><?php endif; ?>
                        <?php if (!empty($data['html']) && !empty($data['link'])): ?><a class="uag2-ad-click" href="<?php echo esc_url($this->click_url($ad_id)); ?>" target="_blank" rel="sponsored noopener">Перейти</a><?php endif; ?>
                    </div>
                    <script>(function(){const d=new URLSearchParams();d.set('action','uag2_impression');d.set('ad_id',<?php echo wp_json_encode($ad_id); ?>);d.set('nonce',<?php echo wp_json_encode(wp_create_nonce('uag2_front')); ?>);fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:d.toString()}).catch(()=>{});})();</script>
                <?php endif; ?>
            </div>
            <header class="uag2-header">
                <div><strong>🎸 UA GUITAR TRAINER</strong><small>Твоя гітара. Твій ритм.</small></div>
                <div class="uag2-top-actions">
                    <button type="button" class="uag2-install" hidden>⬇ Встановити</button>
                    <button type="button" class="uag2-icon-button">🎨 Іконка</button>
                </div>
            </header>
            <div class="uag2-tabs">
                <button type="button" class="active" data-mode="styles">🎼 За стилем</button>
                <button type="button" data-mode="custom">🎵 Своя</button>
            </div>
            <div class="uag2-settings">
                <label>Гітара<select class="uag2-instrument"><option value="acoustic">Акустична</option><option value="electric">Електрична</option><option value="nylon">Нейлон</option></select></label>
                <div class="uag2-style-fields"><label>Стиль<select class="uag2-style"></select></label><label>Варіант<select class="uag2-variant"></select></label></div>
                <div class="uag2-custom-fields" hidden>
                    <label>Акорд<select class="uag2-chord"></select></label>
                    <button type="button" class="uag2-add">+ Додати</button>
                    <div class="uag2-sequence"></div>
                    <button type="button" class="uag2-clear">Очистити</button>
                    <label>Ритм<select class="uag2-pattern"><option value="basic">Рівномірний</option><option value="downup">Вниз-вгору</option><option value="ballad">Балада</option><option value="rock">Рок</option><option value="reggae">Реггі</option><option value="arpeggio">Перебір</option><option value="fingerstyle">Фінґерстайл</option></select></label>
                </div>
                <label>Швидкість <b class="uag2-bpm-label">100 BPM</b><input type="range" min="40" max="220" value="100" class="uag2-bpm"></label>
                <label>Гучність <b class="uag2-volume-label">70%</b><input type="range" min="0" max="100" value="70" class="uag2-volume"></label>
                <label>Реверб <b class="uag2-reverb-label">25%</b><input type="range" min="0" max="100" value="25" class="uag2-reverb"></label>
                <label>Яскравість <b class="uag2-bright-label">60%</b><input type="range" min="0" max="100" value="60" class="uag2-bright"></label>
                <label class="uag2-check"><input type="checkbox" class="uag2-loop" checked> Повторювати</label>
                <label class="uag2-check"><input type="checkbox" class="uag2-metronome"> Метроном</label>
            </div>
            <div class="uag2-description"></div>
            <div class="uag2-stage">
                <div class="uag2-now">ЗАРАЗ ГРАЄ<strong class="uag2-current">—</strong><small class="uag2-next"></small></div>
                <div class="uag2-guitar"><div class="uag2-neck"><div class="uag2-strings"><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="uag2-frets"><i></i><i></i><i></i><i></i></div></div><div class="uag2-hand">✋</div></div>
                <div class="uag2-direction">Натисни «Грати»</div>
                <div class="uag2-beats">○ ○ ○ ○</div>
            </div>
            <div class="uag2-learning">
                <div><h3>Схема акорду</h3><div class="uag2-diagram"></div><strong class="uag2-chord-name">—</strong></div>
                <div><h3>Права рука</h3><p class="uag2-instruction">Підказка з'явиться під час гри.</p><small>↓ Вниз · ↑ Вгору · ♪ Перебір · — Пауза</small></div>
            </div>
            <div class="uag2-progress"></div>
            <div class="uag2-buttons">
                <button type="button" class="uag2-play">▶ ГРАТИ</button>
                <button type="button" class="uag2-stop" disabled>■ СТОП</button>
                <button type="button" class="uag2-save">♥ ЗБЕРЕГТИ</button>
                <button type="button" class="uag2-load">МОЇ КОМПОЗИЦІЇ</button>
            </div>
            <div class="uag2-message" aria-live="polite"></div>
        </div>

        <style>
        #<?php echo esc_attr($id); ?>{font-family:Arial,sans-serif;color:#17243a;background:#f3f6fb;border:1px solid #dce4ef;border-radius:15px;max-width:950px;margin:20px auto;overflow:hidden}
        #<?php echo esc_attr($id); ?> *{box-sizing:border-box}
        #<?php echo esc_attr($id); ?> button,#<?php echo esc_attr($id); ?> select{font:inherit}
        #<?php echo esc_attr($id); ?> button{cursor:pointer}
        #<?php echo esc_attr($id); ?> button:disabled{opacity:.4}
        #<?php echo esc_attr($id); ?> .uag2-ad-area{padding:10px 15px 0}
        #<?php echo esc_attr($id); ?> .uag2-ad{background:white;border:1px solid #dce4ef;border-radius:10px;padding:10px;text-align:center}
        #<?php echo esc_attr($id); ?> .uag2-ad-label{font-size:9px;color:#999;text-align:right;letter-spacing:1px}
        #<?php echo esc_attr($id); ?> .uag2-ad img{max-width:100%;height:auto;display:block;margin:auto;max-height:180px}
        #<?php echo esc_attr($id); ?> .uag2-ad-html{overflow:hidden}
        #<?php echo esc_attr($id); ?> .uag2-ad-click{display:inline-block;margin-top:7px;background:#164b91;color:white;padding:7px 14px;border-radius:6px;text-decoration:none}
        #<?php echo esc_attr($id); ?> .uag2-header{background:linear-gradient(120deg,#10294b,#205ba5);color:white;padding:16px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
        #<?php echo esc_attr($id); ?> .uag2-header strong{font-size:19px;display:block}
        #<?php echo esc_attr($id); ?> .uag2-header small{display:block;margin-top:5px;opacity:.8}
        #<?php echo esc_attr($id); ?> .uag2-top-actions{display:flex;gap:6px;flex-wrap:wrap}
        #<?php echo esc_attr($id); ?> .uag2-top-actions button{border:0;border-radius:7px;padding:9px;background:#ffdc54;color:#17243a;font-weight:bold;font-size:12px}
        #<?php echo esc_attr($id); ?> .uag2-tabs{display:flex;gap:7px;padding:10px;background:#e4eaf3}
        #<?php echo esc_attr($id); ?> .uag2-tabs button{flex:1;border:0;background:transparent;padding:11px;border-radius:7px;color:#52647b;font-weight:bold}
        #<?php echo esc_attr($id); ?> .uag2-tabs button.active{background:white;color:#164b91}
        #<?php echo esc_attr($id); ?> .uag2-settings{padding:15px 17px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
        #<?php echo esc_attr($id); ?> label{font-size:12px;font-weight:bold;display:block}
        #<?php echo esc_attr($id); ?> select{display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #ccd5e1;border-radius:7px;background:white}
        #<?php echo esc_attr($id); ?> input[type=range]{width:100%;display:block;margin-top:9px;accent-color:#164b91}
        #<?php echo esc_attr($id); ?> .uag2-check{display:flex;align-items:center;gap:7px}
        #<?php echo esc_attr($id); ?> .uag2-style-fields{display:contents}
        #<?php echo esc_attr($id); ?> .uag2-custom-fields{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:10px}
        #<?php echo esc_attr($id); ?> .uag2-custom-fields[hidden]{display:none}
        #<?php echo esc_attr($id); ?> .uag2-add,#<?php echo esc_attr($id); ?> .uag2-clear{border:0;border-radius:7px;padding:9px;background:#164b91;color:white}
        #<?php echo esc_attr($id); ?> .uag2-clear{background:#e7edf5;color:#a22}
        #<?php echo esc_attr($id); ?> .uag2-sequence{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:5px}
        #<?php echo esc_attr($id); ?> .uag2-sequence button{border:0;background:#164b91;color:white;padding:6px;border-radius:5px}
        #<?php echo esc_attr($id); ?> .uag2-description{padding:0 17px 12px;color:#66778e;font-size:12px}
        #<?php echo esc_attr($id); ?> .uag2-stage{margin:0 14px;padding:17px;border-radius:12px;background:linear-gradient(140deg,#10294b,#265588);color:white;text-align:center}
        #<?php echo esc_attr($id); ?> .uag2-now{font-size:10px;letter-spacing:2px}
        #<?php echo esc_attr($id); ?> .uag2-current{display:block;font-size:42px;color:#ffdc54;letter-spacing:0}
        #<?php echo esc_attr($id); ?> .uag2-next{display:block;font-size:11px;letter-spacing:0}
        #<?php echo esc_attr($id); ?> .uag2-guitar{height:115px;max-width:550px;margin:15px auto;position:relative;border:5px solid #71421f;border-radius:60px;background:linear-gradient(90deg,#8d4f28,#d59b53,#9b5b2d);overflow:hidden}
        #<?php echo esc_attr($id); ?> .uag2-neck{position:absolute;left:8%;right:8%;top:17px;height:72px;background:#422d22;border:2px solid #c29a6a}
        #<?php echo esc_attr($id); ?> .uag2-strings{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:space-around}
        #<?php echo esc_attr($id); ?> .uag2-strings i{height:1px;background:#f2dfb8}
        #<?php echo esc_attr($id); ?> .uag2-frets{position:absolute;inset:0;display:flex;justify-content:space-around}
        #<?php echo esc_attr($id); ?> .uag2-frets i{height:100%;width:2px;background:#d6c4a8}
        #<?php echo esc_attr($id); ?> .uag2-hand{position:absolute;right:17%;top:17px;font-size:40px;z-index:2;transform:rotate(-30deg)}
        #<?php echo esc_attr($id); ?> .uag2-guitar.down .uag2-hand{animation:u2down .2s}
        #<?php echo esc_attr($id); ?> .uag2-guitar.up .uag2-hand{animation:u2up .2s}
        @keyframes u2down{from{transform:translateY(-8px) rotate(-30deg)}to{transform:translateY(10px) rotate(10deg)}}
        @keyframes u2up{from{transform:translateY(10px) rotate(10deg)}to{transform:translateY(-8px) rotate(-30deg)}}
        #<?php echo esc_attr($id); ?> .uag2-direction{font-size:12px}
        #<?php echo esc_attr($id); ?> .uag2-beats{color:#ffdc54;letter-spacing:6px;margin-top:8px}
        #<?php echo esc_attr($id); ?> .uag2-learning{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:15px;text-align:center}
        #<?php echo esc_attr($id); ?> .uag2-learning>div{background:white;border:1px solid #e0e7ef;border-radius:10px;padding:12px}
        #<?php echo esc_attr($id); ?> .uag2-learning h3{font-size:14px;margin:0 0 8px;color:#53647b}
        #<?php echo esc_attr($id); ?> .uag2-diagram{height:115px}
        #<?php echo esc_attr($id); ?> .uag2-diagram svg{width:175px;height:115px}
        #<?php echo esc_attr($id); ?> .uag2-chord-name{color:#164b91;font-size:19px}
        #<?php echo esc_attr($id); ?> .uag2-instruction{font-size:13px;color:#586b82;min-height:40px}
        #<?php echo esc_attr($id); ?> .uag2-learning small{font-size:10px;color:#64748b}
        #<?php echo esc_attr($id); ?> .uag2-progress{display:flex;gap:5px;flex-wrap:wrap;padding:0 15px 15px}
        #<?php echo esc_attr($id); ?> .uag2-progress span{padding:7px 10px;background:#e0e7f0;border-radius:6px;font-size:12px;font-weight:bold}
        #<?php echo esc_attr($id); ?> .uag2-progress span.active{background:#164b91;color:white;box-shadow:0 0 0 2px #ffdc54}
        #<?php echo esc_attr($id); ?> .uag2-buttons{display:flex;gap:7px;padding:0 15px 12px;flex-wrap:wrap}
        #<?php echo esc_attr($id); ?> .uag2-buttons button{flex:1;border:0;border-radius:7px;padding:11px 8px;font-weight:bold;font-size:11px}
        #<?php echo esc_attr($id); ?> .uag2-play{background:#164b91;color:white}
        #<?php echo esc_attr($id); ?> .uag2-stop{background:#dce3ed}
        #<?php echo esc_attr($id); ?> .uag2-save,#<?php echo esc_attr($id); ?> .uag2-load{background:#ffdc54}
        #<?php echo esc_attr($id); ?> .uag2-message{padding:0 15px 12px;font-size:12px;color:#164b91;min-height:20px}
        @media(max-width:600px){#<?php echo esc_attr($id); ?> .uag2-settings,#<?php echo esc_attr($id); ?> .uag2-learning{grid-template-columns:1fr}#<?php echo esc_attr($id); ?> .uag2-custom-fields{grid-template-columns:1fr}#<?php echo esc_attr($id); ?> .uag2-current{font-size:35px}}
        </style>

        <script>
        (function(){
            "use strict";
            const root=document.getElementById(<?php echo wp_json_encode($id); ?>);
            if(!root||root.dataset.ready)return;
            root.dataset.ready="1";
            const $=s=>root.querySelector(s);
            const $$=s=>Array.from(root.querySelectorAll(s));

            const CH={
                C:{n:[130.81,164.81,196,261.63,329.63],s:[-1,3,2,0,1,0]},
                Cm:{n:[130.81,155.56,196,261.63,311.13],s:[-1,3,1,0,1,3]},
                C7:{n:[130.81,164.81,196,233.08,329.63],s:[-1,3,2,3,1,0]},
                D:{n:[146.83,220,293.66,369.99,440],s:[-1,-1,0,2,3,2]},
                Dm:{n:[146.83,220,293.66,349.23,440],s:[-1,-1,0,2,3,1]},
                D7:{n:[146.83,220,293.66,349.23,392],s:[-1,-1,0,2,1,2]},
                E:{n:[82.41,123.47,164.81,207.65,246.94,329.63],s:[0,2,2,1,0,0]},
                Em:{n:[82.41,123.47,164.81,196,246.94,329.63],s:[0,2,2,0,0,0]},
                E7:{n:[82.41,123.47,164.81,196,246.94,311.13],s:[0,2,0,1,0,0]},
                F:{n:[87.31,130.81,174.61,220,261.63,349.23],s:[1,3,3,2,1,1]},
                Fm:{n:[87.31,130.81,174.61,207.65,261.63,349.23],s:[1,3,3,1,1,1]},
                F7:{n:[87.31,130.81,174.61,207.65,261.63,311.13],s:[1,3,1,2,1,1]},
                G:{n:[98,146.83,196,246.94,293.66,392],s:[3,2,0,0,0,3]},
                Gm:{n:[98,146.83,196,233.08,293.66,392],s:[3,5,5,3,3,3]},
                G7:{n:[98,146.83,196,233.08,293.66,392],s:[3,2,0,0,0,1]},
                A:{n:[110,164.81,220,277.18,329.63],s:[-1,0,2,2,2,0]},
                Am:{n:[110,164.81,220,261.63,329.63],s:[-1,0,2,2,1,0]},
                A7:{n:[110,164.81,220,261.63,329.63],s:[-1,0,2,0,2,0]},
                B:{n:[123.47,185,246.94,311.13,370],s:[-1,2,4,4,4,2]},
                Bm:{n:[123.47,185,246.94,293.66,370],s:[-1,2,4,4,3,2]},
                B7:{n:[123.47,185,246.94,293.66,370],s:[-1,2,1,2,0,2]},
                Cadd9:{n:[130.81,164.81,196,293.66,329.63],s:[-1,3,2,0,3,3]},
                Dsus4:{n:[146.83,220,293.66,392,440],s:[-1,-1,0,2,3,3]},
                Em7:{n:[82.41,123.47,164.81,196,246.94,293.66],s:[0,2,0,0,0,0]},
                Am7:{n:[110,164.81,220,261.63,392],s:[-1,0,2,0,1,0]},
                Gmaj7:{n:[98,146.83,196,246.94,293.66,369.99],s:[3,2,0,0,0,2]}
            };

            const ST={
                blues:{title:"Блюз",desc:"Класичний блюзовий ритм.",v:[
                    ["Класичний блюз",["E7","A7","E7","E7","A7","A7","E7","E7","B7","A7","E7","B7"],["D","D","U","D","U","D","U","D"]],
                    ["Повільний блюз",["A7","D7","A7","A7","D7","D7","A7","A7","E7","D7","A7","E7"],["D","-","D","U","-","U","D","U"]],
                    ["Блюз-рок",["E7","G","A","E7","G","A","B7","A7"],["D","D","-","U","D","U","D","U"]]
                ]},
                rock:{title:"Рок",desc:"Енергійний рок-бій.",v:[
                    ["Класичний рок",["E","G","D","A"],["D","D","U","D","U","D","U","U"]],
                    ["Потужний рок",["Am","F","C","G"],["D","-","D","U","D","-","D","U"]],
                    ["Рок-балада",["G","D","Em","C"],["D","-","U","D","-","U","D","U"]]
                ]},
                pop:{title:"Поп",desc:"Популярні гармонії.",v:[
                    ["Популярна прогресія",["C","G","Am","F"],["D","-","D","U","-","U","D","U"]],
                    ["Легкий поп",["G","D","Em","C"],["D","D","U","U","D","U","D","U"]],
                    ["Поп-балада",["Am","F","C","G"],["D","-","-","U","D","-","U","-"]]
                ]},
                folk:{title:"Фолк",desc:"Народний акустичний акомпанемент.",v:[
                    ["Простий фолк",["G","C","D","G"],["D","-","D","U","D","-","D","U"]],
                    ["Народний",["Am","Dm","E","Am"],["D","U","D","U","D","U","D","U"]],
                    ["Перебір",["C","Am","F","G"],["P","P","P","P","P","P","P","P"]]
                ]},
                country:{title:"Кантрі",desc:"Ритм із чергуванням басових нот.",v:[
                    ["Кантрі",["G","C","D","G"],["D","-","U","D","-","U","D","U"]],
                    ["Кантрі-балада",["D","G","A","D"],["P","P","D","U","P","P","D","U"]]
                ]},
                reggae:{title:"Реггі",desc:"Характерні акценти на слабких долях.",v:[
                    ["Класичний реггі",["Am","G","F","E"],["-","D","-","D","-","D","-","D"]],
                    ["М'який реггі",["C","G","Am","F"],["-","D","U","-","-","D","U","-"]]
                ]},
                jazz:{title:"Джаз",desc:"М'який джазовий акомпанемент.",v:[
                    ["Джазова основа",["Am7","D7","Gmaj7","C"],["D","-","U","D","-","U","D","U"]],
                    ["Джазова прогресія",["C","Am7","D7","Gmaj7"],["D","-","-","U","D","-","U","-"]]
                ]},
                punk:{title:"Панк-рок",desc:"Швидкий прямолінійний ритм.",v:[
                    ["Швидкий панк",["A","D","E","A"],["D","D","D","D","D","D","D","D"]],
                    ["Панк-рок",["E","B","A","B"],["D","D","-","D","D","D","-","D"]]
                ]},
                ballad:{title:"Балада",desc:"Повільний акомпанемент.",v:[
                    ["Ніжна балада",["C","G","Am","Em","F","C","F","G"],["P","P","P","P","P","P","P","P"]],
                    ["Повільний бій",["G","D","Em","C"],["D","-","-","U","D","-","U","-"]]
                ]},
                flamenco:{title:"Фламенко",desc:"Іспанський ритм.",v:[
                    ["Іспанський",["Am","G","F","E"],["D","U","D","U","D","U","D","U"]],
                    ["Фламенко-бій",["Am","G","F","E"],["D","D","U","D","U","D","U","U"]]
                ]}
            };

            const PAT={
                basic:["D","-","D","-","D","-","D","-"],
                downup:["D","U","D","U","D","U","D","U"],
                ballad:["P","P","P","P","P","P","P","P"],
                rock:["D","D","-","U","D","D","-","U"],
                reggae:["-","D","-","D","-","D","-","D"],
                arpeggio:["P","P","P","P","P","P","P","P"],
                fingerstyle:["P","P","P","P","P","P","P","P"]
            };

            let mode="styles",instrument="acoustic",sequence=[],chords=[],pattern=[];
            let index=0,step=0,playing=false,timer=null,audio=null,master=null;
            let bpm=100,volume=.7,brightness=.6,reverbAmt=.25;
            let metroOn=false,metroTimer=null;

            const style=$(".uag2-style"),variant=$(".uag2-variant"),chord=$(".uag2-chord");

            Object.keys(ST).forEach(k=>style.add(new Option(ST[k].title,k)));
            Object.keys(CH).forEach(k=>chord.add(new Option(k,k)));

            function msg(t){$(".uag2-message").textContent=t;}

            // ============ AUDIO ENGINE ============

            let reverbNode=null, dryGain=null, wetGain=null;
            let bodyFilter=null, presenceFilter=null;
            const sampleBuffers = {};
            let samplesLoaded = false;

            function readyAudio(){
                if(!audio){
                    const AC=window.AudioContext||window.webkitAudioContext;
                    if(!AC){msg("Браузер не підтримує аудіо.");return false;}
                    audio=new AC();

                    master=audio.createGain();
                    master.gain.value=volume;

                    const comp=audio.createDynamicsCompressor();
                    comp.threshold.value=-18;
                    comp.knee.value=8;
                    comp.ratio.value=3.5;
                    comp.attack.value=0.005;
                    comp.release.value=0.25;

                    bodyFilter=audio.createBiquadFilter();
                    bodyFilter.type="peaking";
                    bodyFilter.frequency.value=110;
                    bodyFilter.Q.value=0.7;
                    bodyFilter.gain.value=3.5;

                    presenceFilter=audio.createBiquadFilter();
                    presenceFilter.type="peaking";
                    presenceFilter.frequency.value=2400;
                    presenceFilter.Q.value=0.8;
                    presenceFilter.gain.value=2;

                    reverbNode=audio.createConvolver();
                    reverbNode.buffer=makeImpulseResponse(2.4,2.2);
                    dryGain=audio.createGain();
                    wetGain=audio.createGain();
                    dryGain.gain.value=1-reverbAmt*0.5;
                    wetGain.gain.value=reverbAmt;

                    master.connect(bodyFilter);
                    bodyFilter.connect(presenceFilter);
                    presenceFilter.connect(comp);
                    comp.connect(dryGain);
                    dryGain.connect(audio.destination);
                    comp.connect(reverbNode);
                    reverbNode.connect(wetGain);
                    wetGain.connect(audio.destination);
                }
                if(audio.state==="suspended")audio.resume();
                return true;
            }

            function makeImpulseResponse(seconds,decay){
                const rate=audio.sampleRate;
                const len=Math.floor(rate*seconds);
                const buf=audio.createBuffer(2,len,rate);
                for(let ch=0;ch<2;ch++){
                    const d=buf.getChannelData(ch);
                    for(let i=0;i<len;i++){
                        const t=i/len;
                        const early=(i<rate*0.06)?(Math.random()*0.9):0;
                        d[i]=(Math.random()*2-1)*Math.pow(1-t,decay)+early*0.4*Math.pow(1-t,10);
                    }
                }
                return buf;
            }

            const BASE_FREQS = [
                82.41, 87.31, 92.50, 98.00, 103.83, 110.00, 116.54, 123.47,
                130.81, 138.59, 146.83, 155.56, 164.81, 174.61, 185.00, 196.00,
                207.65, 220.00, 233.08, 246.94, 261.63, 277.18, 293.66, 311.13,
                329.63
            ];

            function closestBaseFrequency(freq){
                let best = BASE_FREQS[0];
                let bestDist = Math.abs(Math.log2(freq / best));
                for(let i = 1; i < BASE_FREQS.length; i++){
                    const d = Math.abs(Math.log2(freq / BASE_FREQS[i]));
                    if(d < bestDist){ bestDist = d; best = BASE_FREQS[i]; }
                }
                return best;
            }

            async function loadSamplesFromServer(){
                if(samplesLoaded) return;
                msg("Завантажую семпли гітари...");

                try {
                    const form = new URLSearchParams();
                    form.set("action", "uag2_samples");
                    const resp = await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                        body: form.toString()
                    });
                    const json = await resp.json();
                    if(!json || !json.success){
                        msg("Не вдалося отримати семпли.");
                        samplesLoaded = true;
                        return;
                    }
                    const manifest = json.data;

                    const tasks = [];
                    for(const inst in manifest){
                        for(const item of manifest[inst]){
                            const key = inst + "_" + item.freq.toFixed(2);
                            tasks.push(
                                fetch(item.url, {credentials:"same-origin"})
                                    .then(r => r.arrayBuffer())
                                    .then(ab => audio.decodeAudioData(ab))
                                    .then(buffer => { sampleBuffers[key] = buffer; })
                                    .catch(() => {})
                            );
                        }
                    }
                    await Promise.all(tasks);
                    samplesLoaded = true;
                    msg("Готово! Натисніть ГРАТИ.");
                } catch(e) {
                    samplesLoaded = true;
                    msg("Помилка завантаження семплів.");
                }
            }

            function playSample(freq, when, velocity){
                const baseFreq = closestBaseFrequency(freq);
                const key = instrument + "_" + baseFreq.toFixed(2);
                const buf = sampleBuffers[key];
                if(!buf) return;

                const src = audio.createBufferSource();
                src.buffer = buf;
                src.playbackRate.value = freq / baseFreq;

                const g = audio.createGain();
                const rate = freq / baseFreq;
                const gainComp = 1 / Math.sqrt(rate);
                g.gain.value = (velocity || 0.9) * 0.42 * gainComp;

                src.connect(g);
                g.connect(master);
                src.start(when || audio.currentTime);
            }

            function sound(direction){
                if(!audio) return;
                const d = CH[chords[index]];
                if(!d) return;

                const now = audio.currentTime + 0.02;
                const notes = d.n.slice();

                if(direction === "U"){
                    notes.reverse();
                    notes.forEach((f, i) => {
                        playSample(f, now + i * 0.014, 0.75);
                    });
                } else if(direction === "D"){
                    notes.forEach((f, i) => {
                        playSample(f, now + i * 0.018, 0.95);
                    });
                } else if(direction === "P"){
                    const order = [0, 2, 1, 3, 2, 4, 3, 5];
                    const idx = step % order.length;
                    const f = notes[Math.min(order[idx], notes.length - 1)];
                    if(f) playSample(f, now, 0.88);
                }
            }

            function metronomeTick(){
                if(!audio || !metroOn) return;
                const now = audio.currentTime;
                const osc = audio.createOscillator();
                osc.type = "square";
                osc.frequency.value = 1100;
                const g = audio.createGain();
                g.gain.setValueAtTime(0.05, now);
                g.gain.exponentialRampToValueAtTime(0.0001, now + 0.05);
                osc.connect(g);
                g.connect(master);
                osc.start(now);
                osc.stop(now + 0.06);
            }

            // ============ UI LOGIC ============

            function setMode(m){
                stop();mode=m;
                $$(".uag2-tabs button").forEach(b=>b.classList.toggle("active",b.dataset.mode===m));
                $(".uag2-style-fields").hidden=m!=="styles";
                $(".uag2-custom-fields").hidden=m!=="custom";
                update();
            }

            function loadVariants(){
                const data=ST[style.value];
                variant.innerHTML="";
                data.v.forEach((v,i)=>variant.add(new Option(v[0],i)));
                $(".uag2-description").textContent=data.desc;
                update();
            }

            function update(){
                if(mode==="styles"){
                    const v=ST[style.value].v[Number(variant.value)||0];
                    chords=v[1].slice();pattern=v[2].slice();
                }else{
                    chords=sequence.slice();
                    pattern=PAT[$(".uag2-pattern").value]||PAT.basic;
                }
                index=0;step=0;show(chords[0]);
            }

            function drawSequence(){
                const box=$(".uag2-sequence");box.innerHTML="";
                sequence.forEach((c,i)=>{
                    const b=document.createElement("button");
                    b.type="button";b.textContent=c+" ×";
                    b.addEventListener("click",()=>{sequence.splice(i,1);drawSequence();update();});
                    box.appendChild(b);
                });
            }

            function progress(){
                const box=$(".uag2-progress");box.innerHTML="";
                chords.forEach((c,i)=>{
                    const s=document.createElement("span");
                    s.textContent=c;if(i===index)s.className="active";
                    box.appendChild(s);
                });
            }

            function diagram(c){
                const box=$(".uag2-diagram");box.innerHTML="";
                const d=CH[c];if(!d)return;
                const ns="http://www.w3.org/2000/svg";
                const svg=document.createElementNS(ns,"svg");svg.setAttribute("viewBox","0 0 180 120");
                for(let i=0;i<6;i++){
                    const l=document.createElementNS(ns,"line");
                    l.setAttribute("x1",25+i*24);l.setAttribute("x2",25+i*24);
                    l.setAttribute("y1",16);l.setAttribute("y2",105);
                    l.setAttribute("stroke","#53647b");l.setAttribute("stroke-width",i===0?3:1.3);svg.appendChild(l);
                }
                for(let i=0;i<5;i++){
                    const l=document.createElementNS(ns,"line");
                    l.setAttribute("x1",25);l.setAttribute("x2",145);
                    l.setAttribute("y1",16+i*22);l.setAttribute("y2",16+i*22);
                    l.setAttribute("stroke","#53647b");svg.appendChild(l);
                }
                d.s.forEach((f,i)=>{
                    const x=25+i*24;
                    if(f<=0){
                        const t=document.createElementNS(ns,"text");
                        t.setAttribute("x",x);t.setAttribute("y",10);t.setAttribute("text-anchor","middle");
                        t.setAttribute("fill",f<0?"#b22":"#18794e");t.textContent=f<0?"×":"○";svg.appendChild(t);
                    }else{
                        const c1=document.createElementNS(ns,"circle");
                        c1.setAttribute("cx",x);c1.setAttribute("cy",16+(Math.min(f,4)-.5)*22);
                        c1.setAttribute("r",7);c1.setAttribute("fill","#164b91");svg.appendChild(c1);
                    }
                });
                box.appendChild(svg);
            }

            function show(c){
                $(".uag2-current").textContent=c||"—";
                $(".uag2-chord-name").textContent=c||"—";
                $(".uag2-next").textContent=chords.length?"Наступний: "+chords[(index+1)%chords.length]:"Додай акорди";
                diagram(c);progress();
            }

            function tick(){
                if(!playing||!chords.length)return;
                const dir=pattern[step%pattern.length]||"-";
                if(dir!=="-")sound(dir);

                const guitar=$(".uag2-guitar");
                guitar.classList.remove("down","up");
                void guitar.offsetWidth;
                if(dir==="D")guitar.classList.add("down");
                if(dir==="U")guitar.classList.add("up");

                const labels={D:"↓ Рух руки вниз",U:"↑ Рух руки вгору",P:"♪ Перебір пальцями","-":"Пауза"};
                $(".uag2-direction").textContent=labels[dir];
                $(".uag2-instruction").textContent=labels[dir];
                $(".uag2-beats").textContent=[0,1,2,3].map((x)=>x===Math.floor((step%pattern.length)/2)?"●":"○").join(" ");

                step++;
                if(step>=pattern.length){
                    step=0;index++;
                    if(index>=chords.length){
                        if($(".uag2-loop").checked)index=0;
                        else{stop();msg("Композиція завершена.");return;}
                    }
                    show(chords[index]);
                }
            }

            function start(){
                if(playing)return;
                if(!chords.length){msg("Спочатку обери стиль або додай акорди.");return;}
                if(!readyAudio())return;
                playing=true;index=0;step=0;show(chords[0]);
                bpm=Number($(".uag2-bpm").value);
                master.gain.setTargetAtTime(volume,audio.currentTime,.03);
                $(".uag2-play").disabled=true;$(".uag2-stop").disabled=false;
                const interval=60000/bpm/2;
                tick();
                timer=setInterval(tick,interval);
                if(metroOn){
                    metroTimer=setInterval(metronomeTick,interval*2);
                }
                msg("Відтворення запущено.");
            }

            function stop(){
                playing=false;
                if(timer)clearInterval(timer);timer=null;
                if(metroTimer)clearInterval(metroTimer);metroTimer=null;
                $(".uag2-play").disabled=false;$(".uag2-stop").disabled=true;
                $(".uag2-guitar").classList.remove("down","up");
                $(".uag2-direction").textContent="Зупинено";
                $(".uag2-beats").textContent="○ ○ ○ ○";
            }

            function save(){
                if(!sequence.length){msg("Спочатку додай акорди.");return;}
                const name=prompt("Назва композиції:","Моя композиція");if(!name)return;
                let all=[];try{all=JSON.parse(localStorage.getItem("uag2_saved")||"[]");}catch(e){}
                all.push({name:name,chords:sequence,pattern:$(".uag2-pattern").value,bpm:Number($(".uag2-bpm").value),instrument:instrument});
                localStorage.setItem("uag2_saved",JSON.stringify(all));msg("Композицію збережено на цьому пристрої.");
            }

            function load(){
                let all=[];try{all=JSON.parse(localStorage.getItem("uag2_saved")||"[]");}catch(e){}
                if(!all.length){msg("Збережених композицій немає.");return;}
                const answer=prompt("Введи номер композиції:\n"+all.map((x,i)=>(i+1)+". "+x.name+" — "+x.chords.join(" / ")).join("\n"),"1");
                if(answer===null)return;
                const item=all[Number(answer)-1];if(!item)return;
                stop();sequence=item.chords.slice();$(".uag2-pattern").value=item.pattern||"basic";
                if(item.instrument){instrument=item.instrument;$(".uag2-instrument").value=item.instrument;}
                $(".uag2-bpm").value=item.bpm||100;$(".uag2-bpm-label").textContent=$(".uag2-bpm").value+" BPM";
                drawSequence();setMode("custom");update();msg("Композицію завантажено.");
            }

            function createIcon(){
                const canvas=document.createElement("canvas");canvas.width=512;canvas.height=512;
                const ctx=canvas.getContext("2d");
                const g=ctx.createLinearGradient(0,0,512,512);
                g.addColorStop(0,"#10294b");g.addColorStop(1,"#205ba5");
                ctx.fillStyle=g;ctx.fillRect(0,0,512,512);
                ctx.fillStyle="#ffdc54";ctx.font="bold 230px Arial";ctx.textAlign="center";ctx.textBaseline="middle";
                ctx.fillText("🎸",256,245);
                ctx.fillStyle="white";ctx.font="bold 48px Arial";ctx.fillText("UA GUITAR",256,430);
                const data=canvas.toDataURL("image/png");
                const a=document.createElement("a");a.href=data;a.download="ua-guitar-trainer-icon.png";a.click();

                <?php if (current_user_can('manage_options')): ?>
                const form=new URLSearchParams();
                form.set("action","uag2_save_icon");form.set("nonce",<?php echo wp_json_encode(wp_create_nonce('uag2_icon')); ?>);form.set("image",data);
                fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:form.toString()})
                    .then(r=>r.json()).then(r=>{msg(r.success?"Іконку завантажено та збережено для PWA.":(r.data||"Не вдалося зберегти іконку."));})
                    .catch(()=>msg("PNG завантажено на пристрій. Збереження на сайті не вдалося."));
                <?php else: ?>
                msg("PNG-іконку створено та завантажено. Для PWA потрібен адміністратор.");
                <?php endif; ?>
            }

            $$(".uag2-tabs button").forEach(b=>b.addEventListener("click",()=>setMode(b.dataset.mode)));
            style.addEventListener("change",loadVariants);
            variant.addEventListener("change",update);
            $(".uag2-add").addEventListener("click",()=>{
                if(sequence.length>=32){msg("Максимум 32 акорди.");return;}
                sequence.push(chord.value);drawSequence();update();
            });
            $(".uag2-clear").addEventListener("click",()=>{sequence=[];drawSequence();update();});
            $(".uag2-pattern").addEventListener("change",update);
            $(".uag2-play").addEventListener("click",start);
            $(".uag2-stop").addEventListener("click",stop);
            $(".uag2-save").addEventListener("click",save);
            $(".uag2-load").addEventListener("click",load);
            $(".uag2-icon-button").addEventListener("click",createIcon);
            $(".uag2-instrument").addEventListener("change",e=>{instrument=e.target.value;msg("Інструмент: "+e.target.options[e.target.selectedIndex].text);});
            $(".uag2-bpm").addEventListener("input",e=>{
                bpm=Number(e.target.value);$(".uag2-bpm-label").textContent=bpm+" BPM";
                if(playing){
                    clearInterval(timer);timer=setInterval(tick,60000/bpm/2);
                    if(metroTimer){clearInterval(metroTimer);metroTimer=setInterval(metronomeTick,60000/bpm*2);}
                }
            });
            $(".uag2-volume").addEventListener("input",e=>{
                volume=Number(e.target.value)/100;$(".uag2-volume-label").textContent=e.target.value+"%";
                if(master&&audio)master.gain.setTargetAtTime(volume,audio.currentTime,.03);
            });
            $(".uag2-reverb").addEventListener("input",e=>{
                reverbAmt=Number(e.target.value)/100;$(".uag2-reverb-label").textContent=e.target.value+"%";
                if(wetGain&&audio){wetGain.gain.setTargetAtTime(reverbAmt,audio.currentTime,.05);dryGain.gain.setTargetAtTime(1-reverbAmt*0.5,audio.currentTime,.05);}
            });
            $(".uag2-bright").addEventListener("input",e=>{
                brightness=Number(e.target.value)/100;$(".uag2-bright-label").textContent=e.target.value+"%";
            });
            $(".uag2-metronome").addEventListener("change",e=>{
                metroOn=e.target.checked;
                if(playing){
                    if(metroTimer){clearInterval(metroTimer);metroTimer=null;}
                    if(metroOn)metroTimer=setInterval(metronomeTick,60000/bpm*2);
                }
            });

            let installPrompt=null;
            const installButton=$(".uag2-install");
            window.addEventListener("beforeinstallprompt",e=>{
                e.preventDefault();installPrompt=e;installButton.hidden=false;
            });
            installButton.addEventListener("click",async()=>{
                if(!installPrompt){msg("Відкрий меню браузера та вибери «Встановити застосунок».");return;}
                installPrompt.prompt();
                await installPrompt.userChoice;
                installPrompt=null;installButton.hidden=true;
            });

            if("serviceWorker" in navigator && location.protocol==="https:"){
                navigator.serviceWorker.register(<?php echo wp_json_encode(add_query_arg('uag2_sw','1',home_url('/'))); ?>,{scope:"/"})
                    .catch(()=>msg("Не вдалося зареєструвати PWA. Перевір HTTPS."));
            }

            const manifest=document.createElement("link");
            manifest.rel="manifest";
            manifest.href=<?php echo wp_json_encode(add_query_arg('uag2_manifest','1',home_url('/'))); ?>;
            document.head.appendChild(manifest);

            let preloaded=false;
            function initAudioOnce(){
                if(preloaded) return;
                preloaded=true;
                if(!readyAudio()) return;
                loadSamplesFromServer();
            }
            root.addEventListener("touchstart", initAudioOnce, {passive:true, once:true});
            root.addEventListener("mousedown", initAudioOnce, {passive:true, once:true});

            loadVariants();
            drawSequence();
            msg("Оберіть стиль або створіть власну композицію. Торкніться тренажера, щоб підготувати звук.");
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

new UA_Guitar_Trainer_3();
