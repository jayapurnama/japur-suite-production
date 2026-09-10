<?php
if (!defined('ABSPATH')) exit;

// Modul Japur Auto Index PRO v1.3.6.
// Kode inti dipertahankan dari versi standalone; file wrapper ini hanya menghubungkan
// modul ke Japur Suite dan kontrol ON/OFF tanpa mengubah fungsi internalnya.
if (class_exists('JAI_Pro_Plugin')) return;

if (!defined('JAI_PRO_VERSION')) define('JAI_PRO_VERSION', '1.3.6');
if (!defined('JAI_PRO_FILE')) define('JAI_PRO_FILE', JAPUR_SUITE_FILE);
if (!defined('JAI_PRO_DIR')) define('JAI_PRO_DIR', JAPUR_SUITE_DIR . 'modules/auto-index-pro/');
if (!defined('JAI_PRO_URL')) define('JAI_PRO_URL', JAPUR_SUITE_URL . 'modules/auto-index-pro/');
if (!defined('JAI_PRO_DB_VERSION')) define('JAI_PRO_DB_VERSION', '1.0.0');

$jai_files = [
    'class-jai-db.php',
    'class-jai-site.php',
    'class-jai-indexnow.php',
    'class-jai-sitemap.php',
    'class-jai-gsc.php',
    'class-jai-queue.php',
    'class-jai-admin.php',
    'class-jai-plugin.php',
];
foreach ($jai_files as $jai_file) {
    require_once JAI_PRO_DIR . 'includes/' . $jai_file;
}
unset($jai_files, $jai_file);

// Activation hook tidak tersedia per-modul di dalam Suite, jadi lakukan bootstrap
// yang setara secara aman hanya bila database modul belum terpasang.
if (get_option('jai_pro_db_version') !== JAI_PRO_DB_VERSION) {
    JAI_Pro_DB::install();
    if (!get_option('jai_pro_indexnow_key')) {
        update_option('jai_pro_indexnow_key', '0ac8ea0a346b48afaa9c2afc5e59fc89', false);
    }
    if (!get_option('jai_pro_enabled')) add_option('jai_pro_enabled', 1);
    if (!get_option('jai_pro_post_types')) add_option('jai_pro_post_types', ['post']);
    if (!get_option('jai_pro_batch')) add_option('jai_pro_batch', 5);
    if (!get_option('jai_pro_max_attempts')) add_option('jai_pro_max_attempts', 5);
    if (!get_option('jai_pro_gsc_auto_inspect')) add_option('jai_pro_gsc_auto_inspect', 1);
    JAI_Pro_IndexNow::sync_key_file();
}

// Pastikan Key Location tetap tersedia jika file pernah terhapus.
$key_file = trailingslashit(ABSPATH) . 'japurai-indexnow-key.txt';
if (!is_file($key_file) || !is_readable($key_file)) {
    JAI_Pro_IndexNow::sync_key_file();
}
unset($key_file);

JAI_Pro_Plugin::instance();
