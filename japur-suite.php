<?php
/**
 * Plugin Name: JaPur Suite Production
 * Plugin URI: https://jayapurnama.com/
 * Description: Satu plugin, banyak pekerjaan, bikin ngonten jadi lebih mudah, cepat, rapi, dan tetap santai. 😎 Karena kerja boleh serius, tapi prosesnya harus tetap seru! 🚀
 * Version: 1.3.276
 * Author: Japur Ganteng
 * Author URI: https://jayapurnama.com/
 * License: GPL-2.0-or-later
 * Text Domain: japur-suite
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('JAPUR_SUITE_VERSION')) define('JAPUR_SUITE_VERSION', '1.3.276');
if (!defined('JAPUR_SUITE_FILE')) define('JAPUR_SUITE_FILE', __FILE__);
if (!defined('JAPUR_SUITE_DIR')) define('JAPUR_SUITE_DIR', plugin_dir_path(__FILE__));
if (!defined('JAPUR_SUITE_URL')) define('JAPUR_SUITE_URL', plugin_dir_url(__FILE__));

final class JapurSuite_Core {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function cleanup_removed_autopilot() {
        if (wp_next_scheduled('jaf_autopilot_tick')) {
            wp_clear_scheduled_hook('jaf_autopilot_tick');
        }
        delete_transient('jaf_autopilot_lock');
        delete_option('jaf_autopilot_queue');
    }

    private function __construct() {
        $this->cleanup_removed_autopilot();
        $this->load_modules();
        add_action('admin_menu', [$this, 'admin_menu'], 5);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_footer', [$this, 'admin_feedback_footer'], 100);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'legacy_notice']);
        add_filter('plugin_action_links_' . plugin_basename(JAPUR_SUITE_FILE), [$this, 'plugin_action_links']);
    }

    private function load_modules() {
        $files = [
            'modules/javanese-auto-post-importer.php',
            'modules/auto-update-post-date.php',
            'modules/auto-webp-watermark.php',
            'modules/popup-promo.php',
            'modules/rpi-pro.php',
            'modules/lead-domain-auto-link.php',
            'modules/auto-index-pro.php',
            'modules/master-workflow-settings.php',
            'modules/master-system-audit.php',
            'modules/japur-extractor-ai/module.php',
            'modules/article-task-manager/module.php',
            'modules/openai-cost/module.php',
            'modules/source-sync/module.php',
        ];
        foreach ($files as $file) {
            $path = JAPUR_SUITE_DIR . $file;
            if (file_exists($path)) require_once $path;
        }
    }

    public function admin_assets($hook) {
        if (is_admin()) {
            wp_enqueue_script('japur-suite-admin-menu', JAPUR_SUITE_URL . 'assets/japur-suite-admin-menu.js', ['jquery'], JAPUR_SUITE_VERSION, true);
        }
    }

    public function admin_menu() {
        add_menu_page('JaPur Suite Production', 'JaPur Suite', 'manage_options', 'japur-suite', [$this, 'dashboard'], 'dashicons-admin-generic', 3);
    }

    public function register_settings() {}
    public function plugin_action_links($links) { $links[] = '<a href="' . esc_url(admin_url('admin.php?page=japur-suite')) . '">Dashboard</a>'; return $links; }
    public function admin_feedback_footer() {}
    public function dashboard() {
        echo '<div class="wrap"><h1>JaPur Suite Production</h1><p>Master source repository baseline.</p></div>';
    }

    public static function module_enabled($key) {
        return (bool) get_option('japur_suite_module_' . sanitize_key($key), 1);
    }

    public function legacy_notice() {
        if (!current_user_can('manage_options')) return;
        if (!function_exists('is_plugin_active')) require_once ABSPATH.'wp-admin/includes/plugin.php';
        $legacy = [
            'javanese-auto-post-importer/javanese-auto-post-importer.php',
            'auto-update-post-date/auto-update-post-date.php',
            'auto-webp-watermark/auto-webp-watermark.php',
            'popup-gambar-promo-random.php',
            'rpi-pro/rpi-pro.php',
            'lead-domain-auto-link/lead-domain-auto-link.php',
            'japur-auto-index-pro/japur-auto-index-pro.php',
        ];
        $active=[];
        foreach($legacy as $p) if(is_plugin_active($p)) $active[]=$p;
        if(!$active) return;
        echo '<div class="notice notice-warning"><p><strong>Japur Suite:</strong> versi gabungan aktif. Nonaktifkan plugin lama yang sudah digabung untuk mencegah fungsi berjalan dua kali.</p></div>';
    }
}

JapurSuite_Core::instance();
