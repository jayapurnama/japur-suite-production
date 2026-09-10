<?php
/**
 * Plugin Name: JaPur Suite Production
 * Plugin URI: https://jayapurnama.com/
 * Description: Satu plugin, banyak pekerjaan, bikin ngonten jadi lebih mudah, cepat, rapi, dan tetap santai. 😎 Karena kerja boleh serius, tapi prosesnya harus tetap seru! 🚀
 * Version: 1.3.275
 * Author: Japur Ganteng
 * Author URI: https://jayapurnama.com/
 * License: GPL-2.0-or-later
 * Text Domain: japur-suite
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('JAPUR_SUITE_VERSION')) define('JAPUR_SUITE_VERSION', '1.3.275');
if (!defined('JAPUR_SUITE_FILE')) define('JAPUR_SUITE_FILE', __FILE__);
if (!defined('JAPUR_SUITE_DIR')) define('JAPUR_SUITE_DIR', plugin_dir_path(__FILE__));
if (!defined('JAPUR_SUITE_URL')) define('JAPUR_SUITE_URL', plugin_dir_url(__FILE__));

final class JapurSuite_Core {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /**
     * v1.3.216: cancel and purge Auto Pilot state left by removed pre-release builds.
     * The Auto Pilot feature itself is intentionally absent from this release.
     */
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
        // v1.3.228: tampilkan tautan Dashboard pada baris aksi plugin di halaman Plugins.
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
            // v1.3.229: pastikan modul Manajemen Artikel ikut dimuat oleh core.
            'modules/article-task-manager/module.php',
            'modules/openai-cost/module.php',
        ];
        foreach ($files as $file) {
            $path = JAPUR_SUITE_DIR . $file;
            if (file_exists($path)) require_once $path;
        }
    }

    public function admin_assets($hook) {
        if (is_admin()) {
            wp_enqueue_script('japur-suite-admin-menu', JAPUR_SUITE_URL . 'assets/japur-suite-admin-menu.js', ['jquery'], JAPUR_SUITE_VERSION, true);
            wp_add_inline_style('dashicons', '.wp-submenu.japur-suite-collapsed{display:none!important}.wp-has-submenu.japur-suite-submenu-closed>a .wp-menu-arrow:before{transform:rotate(180deg)}.japi-status{display:flex!important;align-items:center!important;gap:12px!important;background:#fff!important;border:1px solid #dcdcde!important;border-radius:12px!important;padding:14px 16px!important;margin:0 0 16px!important;box-shadow:0 1px 2px rgba(0,0,0,.03)!important;box-sizing:border-box!important;width:100%!important}.japi-status-dot{width:10px!important;height:10px!important;min-width:10px!important;border-radius:50%!important;flex:0 0 10px!important;margin:0!important;background:#d63638!important;box-sizing:border-box!important}.japi-status.is-on .japi-status-dot{background:#00a32a!important;box-shadow:0 0 0 4px #edfaef!important}.japi-status.is-off .japi-status-dot{background:#d63638!important;box-shadow:0 0 0 4px #fcf0f1!important}.japi-status-label{display:flex!important;align-items:center!important;min-width:0!important;min-height:20px!important;margin:0!important}.japi-status-label strong{margin:0!important;line-height:20px!important;font-size:14px!important;color:#1d2327!important}.japi-dashboard-link{display:flex!important;align-items:center!important;margin-left:auto!important;line-height:20px!important;min-height:20px!important;padding:0!important;text-decoration:none!important;font-weight:600!important;white-space:nowrap!important;flex:0 0 auto!important}');
            wp_add_inline_style('dashicons', '.jaf-confirm-backdrop{position:fixed!important;inset:0!important;z-index:100001!important;background:rgba(0,0,0,.38)!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:16px!important;box-sizing:border-box!important}.jaf-confirm-card{width:min(420px,100%)!important;background:#fff!important;border:1px solid #dcdcde!important;border-radius:14px!important;box-shadow:0 18px 48px rgba(0,0,0,.22)!important;padding:20px!important;box-sizing:border-box!important}.jaf-confirm-title{margin:0 0 8px!important;font-size:17px!important;line-height:1.35!important;color:#1d2327!important}.jaf-confirm-message{margin:0 0 18px!important;font-size:14px!important;line-height:1.55!important;color:#50575e!important;white-space:pre-wrap!important}.jaf-confirm-actions{display:flex!important;gap:8px!important;justify-content:flex-end!important;flex-wrap:wrap!important}.jaf-confirm-actions button{min-height:40px!important;padding:7px 14px!important}.jaf-confirm-cancel{background:#fff!important}.jaf-confirm-ok{background:#2271b1!important;border-color:#2271b1!important;color:#fff!important}@media(max-width:650px){.jaf-confirm-backdrop{align-items:flex-end!important}.jaf-confirm-card{border-radius:14px 14px 0 0!important;padding:18px!important}.jaf-confirm-actions{display:grid!important;grid-template-columns:1fr 1fr!important}.jaf-confirm-actions button{width:100%!important}}');
            wp_add_inline_style('dashicons', '.jaf-floating-notice{position:fixed!important;top:44px!important;right:18px!important;left:auto!important;z-index:100000!important;display:inline-flex!important;align-items:center!important;gap:9px!important;width:auto!important;max-width:min(420px,calc(100vw - 36px))!important;min-height:44px!important;margin:0!important;padding:11px 14px!important;box-sizing:border-box!important;border:1px solid #b7dfc0!important;border-left:4px solid #46b96b!important;border-radius:10px!important;background:#f0fbf3!important;box-shadow:0 8px 24px rgba(0,0,0,.16)!important;color:#1e4620!important;font-size:14px!important;font-weight:600!important;line-height:1.35!important;opacity:1!important;transform:translateY(0)!important;transition:opacity .35s ease,transform .35s ease!important}.jaf-floating-notice .dashicons{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex:0 0 20px!important;width:20px!important;height:20px!important;margin:0!important;padding:0!important;font-size:20px!important;line-height:1!important}.jaf-floating-notice .jaf-toast-message{min-width:0!important}.jaf-floating-notice .jaf-toast-close{margin-left:auto!important;flex:0 0 20px!important;width:20px!important;height:20px!important;padding:0!important;border:0!important;background:transparent!important;color:inherit!important;cursor:pointer!important;font-size:20px!important;line-height:20px!important;opacity:.75!important}.jaf-floating-notice .jaf-toast-detail{display:block!important;margin-top:4px!important;font-size:11px!important;font-weight:400!important;line-height:1.45!important;color:#4b6350!important;white-space:pre-wrap!important;max-height:72px!important;overflow:auto!important}.jaf-floating-notice.is-hiding{opacity:0!important;transform:translateY(-8px)!important}.jaf-floating-notice.is-error{background:#fff5f5!important;border-color:#f0b8b8!important;border-left-color:#d63638!important;color:#8a2424!important}.jaf-floating-notice.is-error .dashicons{color:#d63638!important}.jaf-floating-notice.is-warning{background:#fff9e6!important;border-color:#ead79a!important;border-left-color:#dba617!important;color:#6b5300!important}.jaf-floating-notice.is-warning .dashicons{color:#b77900!important}.jaf-floating-notice.is-info{background:#f0f6fc!important;border-color:#c5d9ed!important;border-left-color:#2271b1!important;color:#174a73!important}.jaf-floating-notice.is-info .dashicons{color:#2271b1!important}@media(max-width:782px){.jaf-floating-notice{top:56px!important;right:16px!important;left:16px!important;max-width:none!important;width:auto!important;justify-content:flex-start!important;padding:11px 13px!important;border-radius:10px!important}}');
            wp_add_inline_script('japur-suite-admin-menu', '(function(){
                var pages=["japur-suite","auto-date-title","rpi-settings","lead-domain-auto-link","popup-promo","japi-settings","webp-settings","japur-auto-index-pro","jaf-extractor","jaf-extractor-workflow"];
                var params=new URLSearchParams(window.location.search);
                var page=params.get("page")||"";
                function showToast(message,type,detail){
                    message=(message||"").trim(); if(!message)return;
                    type=type||"success";
                    var existing=document.querySelector(".jaf-floating-notice[data-jaf-toast=\"1\"]");
                    if(existing) existing.remove();
                    var n=document.createElement("div"); n.className="jaf-floating-notice is-"+type; n.setAttribute("data-jaf-toast","1"); n.setAttribute("role","status"); n.setAttribute("aria-live","polite");
                    var icon=type==="error"?"dashicons-warning":(type==="warning"?"dashicons-warning":(type==="info"?"dashicons-info":"dashicons-yes-alt"));
                    n.innerHTML="<span class=\"dashicons "+icon+"\" aria-hidden=\"true\"></span><span class=\"jaf-toast-message\"></span>"+(detail?"<span class=\"jaf-toast-detail\"></span>":"")+"<button type=\"button\" class=\"jaf-toast-close\" aria-label=\"Tutup\">&times;</button>";
                    n.querySelector(".jaf-toast-message").textContent=message; if(detail)n.querySelector(".jaf-toast-detail").textContent=detail;
                    document.body.appendChild(n);
                    var timer=window.setTimeout(function(){n.classList.add("is-hiding");window.setTimeout(function(){if(n.parentNode)n.remove();},350);},3200);
                    n.querySelector(".jaf-toast-close").addEventListener("click",function(){window.clearTimeout(timer);n.remove();});
                }
                window.JapurSuiteToast={show:showToast};
                window.JapurSuiteConfirm=function(message,onConfirm,options){
                    options=options||{};
                    var old=document.querySelector(".jaf-confirm-backdrop"); if(old)old.remove();
                    var backdrop=document.createElement("div"); backdrop.className="jaf-confirm-backdrop"; backdrop.setAttribute("role","dialog"); backdrop.setAttribute("aria-modal","true");
                    backdrop.innerHTML="<div class=\'jaf-confirm-card\'><h2 class=\'jaf-confirm-title\'>"+(options.title||"Konfirmasi Tindakan")+"</h2><p class=\'jaf-confirm-message\'></p><div class=\'jaf-confirm-actions\'><button type=\'button\' class=\'button jaf-confirm-cancel\'>Batal</button><button type=\'button\' class=\'button button-primary jaf-confirm-ok\'>"+(options.confirmText||"Lanjutkan")+"</button></div></div>";
                    backdrop.querySelector(".jaf-confirm-message").textContent=message||\'Apakah Anda yakin ingin melanjutkan?\';
                    document.body.appendChild(backdrop);
                    var cancel=function(){backdrop.remove();};
                    backdrop.querySelector(".jaf-confirm-cancel").addEventListener(\'click\',cancel);
                    backdrop.querySelector(".jaf-confirm-ok").addEventListener(\'click\',function(){backdrop.remove();if(typeof onConfirm===\'function\')onConfirm();});
                    backdrop.addEventListener(\'click\',function(e){if(e.target===backdrop)cancel();});
                    var key=function(e){if(e.key===\'Escape\'){cancel();document.removeEventListener(\'keydown\',key);}}; document.addEventListener(\'keydown\',key);
                    window.setTimeout(function(){backdrop.querySelector(".jaf-confirm-ok").focus();},20);
                };
                if(pages.indexOf(page)===-1)return;
                function convertNotice(node){
                    if(!node || node.nodeType!==1 || node.dataset.jafConverted==="1")return;
                    if(!node.classList.contains("notice"))return;
                    node.dataset.jafConverted="1";
                    var type=node.classList.contains("notice-error")?"error":(node.classList.contains("notice-warning")?"warning":(node.classList.contains("notice-info")?"info":"success"));
                    var p=node.querySelector("p"); var message=p?p.textContent:node.textContent;
                    var pre=node.querySelector("pre"); var detail=pre?pre.textContent.trim():"";
                    node.remove(); showToast(message,type,detail);
                }
                function scan(){document.querySelectorAll(".notice").forEach(convertNotice);}
                if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",scan);else scan();
                new MutationObserver(function(m){m.forEach(function(x){x.addedNodes&&x.addedNodes.forEach(function(node){if(node.nodeType===1){convertNotice(node);node.querySelectorAll&&node.querySelectorAll(".notice").forEach(convertNotice);}});});}).observe(document.body,{childList:true,subtree:true});
            })();');
        }
    }

    /**
     * Render all redirect-based global feedback after the full admin DOM is ready.
     * This intentionally runs in admin_footer so it does not depend on module page
     * scripts, Settings API notice timing, or inline-script placement.
     */
    public function admin_feedback_footer() {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $payload = array();

        if (isset($_GET['settings-updated']) && in_array((string) wp_unslash($_GET['settings-updated']), array('true','1'), true)) {
            $settings_messages = array(
                'auto-date-title'       => 'Pengaturan Auto Update Judul berhasil disimpan.',
                'lead-domain-auto-link' => 'Pengaturan Lead Domain berhasil disimpan.',
                'japur-auto-index-pro'  => 'Pengaturan Japur Auto Index PRO berhasil disimpan.',
            );
            if (isset($settings_messages[$page])) {
                $payload[] = array('success', $settings_messages[$page], '');
            }
        }

        if ($page === 'jaf-extractor') {
            $feedback = get_transient('jaf_save_feedback_' . get_current_user_id());
            if (is_array($feedback) && !empty($feedback['message'])) {
                delete_transient('jaf_save_feedback_' . get_current_user_id());
                $payload[] = array(
                    isset($feedback['type']) ? sanitize_key($feedback['type']) : 'success',
                    sanitize_text_field($feedback['message']),
                    ''
                );
            }
        }

        if ($page === 'japur-auto-index-pro') {
            if (isset($_GET['jai_msg'])) {
                $payload[] = array('success', sanitize_text_field(wp_unslash($_GET['jai_msg'])), '');
            }
            if (isset($_GET['jai_gsc_msg'])) {
                $type = (isset($_GET['jai_gsc_type']) && $_GET['jai_gsc_type'] === 'error') ? 'error' : 'success';
                $payload[] = array($type, sanitize_text_field(wp_unslash($_GET['jai_gsc_msg'])), '');
            }
            if (isset($_GET['jai_gsc_refresh']) && (string) wp_unslash($_GET['jai_gsc_refresh']) !== '') {
                $payload[] = array('success', 'Status Google Search Console diperbarui.', '');
            }
        }

        if (!$payload) {
            return;
        }

        echo '<script>(function(){function run(){var q=' . wp_json_encode($payload) . ';if(!window.JapurSuiteToast||!document.body){return;}q.forEach(function(x){window.JapurSuiteToast.show(x[1],x[0],x[2]);});document.querySelectorAll(".settings-error, .notice.settings-error").forEach(function(n){n.remove();});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}})();</script>';
    }

    public function plugin_action_links($links) {
        $dashboard_url = admin_url('admin.php?page=japur-suite');
        array_unshift($links, '<a href="' . esc_url($dashboard_url) . '">Dashboard</a>');
        return $links;
    }

    public function admin_menu() {
        add_menu_page(
            'Japur Suite',
            'Japur Suite',
            'manage_options',
            'japur-suite',
            [$this, 'dashboard'],
            '',
            58
        );
        add_submenu_page('japur-suite','Dashboard','Dashboard','manage_options','japur-suite',[$this,'dashboard']);
        add_submenu_page('japur-suite','API Center','API Center','manage_options','japur-api-center',[$this,'api_center_page']);
        add_submenu_page('japur-suite','OpenAI Cost','OpenAI Cost','manage_options','japur-openai-cost',['Japur_OpenAI_Cost','page']);
        add_submenu_page('japur-suite','Auto Date Post','Auto Date Post','manage_options','auto-date-title','JapurSuite_auto_date_settings_page');
        add_submenu_page('japur-suite','RPI Pro','RPI Pro','manage_options','rpi-settings','JapurSuite_rpi_settings_page');
        add_submenu_page('japur-suite','Lead Auto Link','Lead Auto Link','manage_options','lead-domain-auto-link',[$this,'lead_domain_page']);
        add_submenu_page('japur-suite','Popup Post','Popup Post','manage_options','popup-promo',[$this,'popup_settings_page']);
        add_submenu_page('japur-suite','IMPost Pro','IMPost Pro','manage_options','japi-settings',[$this,'japi_settings_page']);
        add_submenu_page('japur-suite','WebP + Watermark','WebP + Watermark','manage_options','webp-settings',[$this,'webp_settings_page']);
    }

    public static function openai_admin_key() {
        $self = self::instance();
        $self->migrate_openai_admin_key();
        return $self->decrypt_api_secret(get_option('japur_api_openai_admin_key', ''));
    }

    private function migrate_openai_admin_key() {
        $central = $this->decrypt_api_secret(get_option('japur_api_openai_admin_key', ''));
        if ($central !== '') return $central;
        $legacy = get_option('japur_openai_cost_settings', []);
        if (!is_array($legacy) || empty($legacy['openai_admin_key'])) return '';
        $legacy_key = trim((string)$legacy['openai_admin_key']);
        if ($legacy_key === '') return '';
        update_option('japur_api_openai_admin_key', $this->encrypt_api_secret(sanitize_text_field($legacy_key)), false);
        unset($legacy['openai_admin_key']);
        update_option('japur_openai_cost_settings', $legacy, false);
        return $legacy_key;
    }

    public function api_center_page() {
        if (!current_user_can('manage_options')) return;
        $message = '';
        $message_type = 'success';
        $action = isset($_POST['japur_api_action']) ? sanitize_key(wp_unslash($_POST['japur_api_action'])) : '';
        if ($action && check_admin_referer('japur_api_center')) {
            if ($action === 'save_google_credentials') {
                $client_id = isset($_POST['japur_google_client_id']) ? sanitize_text_field(trim((string) wp_unslash($_POST['japur_google_client_id']))) : '';
                $client_secret = isset($_POST['japur_google_client_secret']) ? sanitize_text_field(trim((string) wp_unslash($_POST['japur_google_client_secret']))) : '';
                if ($client_id === '' || $client_secret === '') {
                    $message = 'Google Client ID dan Client Secret wajib diisi.';
                    $message_type = 'error';
                } else {
                    update_option('jai_pro_gsc_client_id', $client_id, false);
                    update_option('jai_pro_gsc_client_secret', $client_secret, false);
                    $message = 'Kredensial Google berhasil disimpan. Hubungkan/perbarui OAuth untuk menerbitkan token bersama GSC + Blogger.';
                }
            } elseif ($action === 'save_openai') {
                $key = isset($_POST['japur_openai_key']) ? trim((string) wp_unslash($_POST['japur_openai_key'])) : '';
                if ($key !== '') {
                    update_option('japur_api_openai_key', $this->encrypt_api_secret(sanitize_text_field($key)), false);
                    $message = 'OpenAI API Key berhasil disimpan.';
                } else {
                    $message = 'OpenAI API Key tidak diubah. Isi key baru jika ingin menggantinya.';
                    $message_type = 'warning';
                }
            } elseif ($action === 'save_openai_admin') {
                $admin_key = isset($_POST['japur_openai_admin_key']) ? trim((string) wp_unslash($_POST['japur_openai_admin_key'])) : '';
                if ($admin_key !== '') {
                    update_option('japur_api_openai_admin_key', $this->encrypt_api_secret(sanitize_text_field($admin_key)), false);
                    if (function_exists('delete_option')) {
                        $legacy_cost = get_option('japur_openai_cost_settings', []);
                        if (is_array($legacy_cost) && array_key_exists('openai_admin_key', $legacy_cost)) {
                            unset($legacy_cost['openai_admin_key']);
                            update_option('japur_openai_cost_settings', $legacy_cost, false);
                        }
                    }
                    $message = 'OpenAI Admin API Key berhasil disimpan di API Center.';
                } else {
                    $message = 'OpenAI Admin API Key tidak diubah. Isi key baru jika ingin menggantinya.';
                    $message_type = 'warning';
                }
            } elseif ($action === 'save_indexnow') {
                $key = isset($_POST['japur_indexnow_key']) ? sanitize_text_field(trim((string) wp_unslash($_POST['japur_indexnow_key']))) : '';
                if ($key !== '' && preg_match('/^[A-Za-z0-9_-]{8,128}$/', $key)) {
                    update_option('jai_pro_indexnow_key', $key, false);
                    if (class_exists('JAI_Pro_IndexNow')) JAI_Pro_IndexNow::sync_key_file();
                    $message = 'IndexNow API Key berhasil disimpan.';
                } else {
                    $message = 'IndexNow API Key tidak valid.';
                    $message_type = 'error';
                }
            } elseif ($action === 'test_openai') {
                $key = $this->decrypt_api_secret(get_option('japur_api_openai_key', ''));
                if ($key === '') {
                    $message = 'OpenAI API Key belum diisi.';
                    $message_type = 'error';
                } elseif (class_exists('JAF_OpenAI')) {
                    $test = (new JAF_OpenAI($key))->test('gpt-5.6-luna');
                    if (is_wp_error($test)) { $message = 'Tes OpenAI gagal: ' . $test->get_error_message(); $message_type = 'error'; }
                    else { $message = 'OpenAI API berhasil terhubung.'; }
                } else {
                    $message = 'Modul OpenAI belum tersedia.';
                    $message_type = 'error';
                }
            } elseif ($action === 'test_indexnow') {
                if (class_exists('JAI_Pro_IndexNow')) {
                    $test = JAI_Pro_IndexNow::verify_key_location();
                    if (!empty($test['ok'])) $message = 'IndexNow terhubung dan Key Location valid.';
                    else { $message = 'Tes IndexNow gagal: ' . ($test['message'] ?? 'Tidak dapat memverifikasi key.'); $message_type = 'error'; }
                } else { $message = 'Modul IndexNow belum tersedia.'; $message_type = 'error'; }
            }
        }

        // Migrasikan key OpenAI lama ke pusat API sekali saja, tanpa menghapus data lama.
        $central_openai = $this->decrypt_api_secret(get_option('japur_api_openai_key', ''));
        if ($central_openai === '') {
            $legacy = get_option('jaf_options', []);
            if (is_array($legacy) && !empty($legacy['api_key'])) {
                $central_openai = (string) $legacy['api_key'];
                if (strpos($central_openai, 'jafenc:v1:') === 0 && method_exists($this, 'decrypt_jaf_secret_for_api_center')) {
                    $central_openai = $this->decrypt_jaf_secret_for_api_center($central_openai);
                }
                if ($central_openai !== '') update_option('japur_api_openai_key', $this->encrypt_api_secret($central_openai), false);
            }
        }
        // Migrasikan Admin API Key lama dari modul Cost AI ke API Center secara aman.
        $this->migrate_openai_admin_key();
        $google_id = class_exists('JAI_Pro_GSC') ? JAI_Pro_GSC::client_id() : '';
        $google_secret = class_exists('JAI_Pro_GSC') ? JAI_Pro_GSC::client_secret() : '';
        $google_connected = class_exists('JAI_Pro_GSC') ? JAI_Pro_GSC::connected() : false;
        $google_scope = class_exists('JAI_Pro_GSC') ? JAI_Pro_GSC::has_blogger_scope() : false;
        $openai_key = $this->decrypt_api_secret(get_option('japur_api_openai_key', ''));
        $openai_admin_key = $this->decrypt_api_secret(get_option('japur_api_openai_admin_key', ''));
        $indexnow_key = class_exists('JAI_Pro_IndexNow') ? JAI_Pro_IndexNow::key() : (string) get_option('jai_pro_indexnow_key','');
        $google_auth = class_exists('JAI_Pro_GSC') ? JAI_Pro_GSC::auth_url() : '';
        $google_disconnect = wp_nonce_url(admin_url('admin-post.php?action=jai_pro_gsc_disconnect'), 'jai_pro_gsc_disconnect');
        ?>
        <div class="wrap japur-api-center">
            <div class="japur-api-hero">
                <div class="japur-api-eyebrow">JAPUR SUITE</div>
                <h1>API Center</h1>
                <p>Satu tempat untuk mengelola API yang dipakai seluruh modul Japur Suite.</p>
            </div>
            <?php if ($message): ?>
                <div class="notice <?php echo $message_type === 'error' ? 'notice-error' : ($message_type === 'warning' ? 'notice-warning' : 'notice-success'); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <div class="japur-api-grid">
                <section class="japur-api-card">
                    <div class="japur-api-card-head"><div class="japur-api-icon google"><span class="dashicons dashicons-google"></span></div><div><h2>Google API</h2><p>Google Search Console + Blogger dalam satu OAuth.</p></div><span class="japur-api-status <?php echo $google_connected ? 'ok' : 'off'; ?>"><?php echo $google_connected ? 'Terhubung' : 'Belum terhubung'; ?></span></div>
                    <div class="japur-api-body">
                        <form method="post">
                            <?php wp_nonce_field('japur_api_center'); ?><input type="hidden" name="japur_api_action" value="save_google_credentials">
                            <label class="japur-api-label" for="japur-google-client-id">Google Client ID</label>
                            <input id="japur-google-client-id" type="text" name="japur_google_client_id" class="regular-text code" value="<?php echo esc_attr($google_id); ?>" placeholder="xxxx.apps.googleusercontent.com" autocomplete="off">
                            <label class="japur-api-label" for="japur-google-client-secret" style="margin-top:12px">Google Client Secret</label>
                            <input id="japur-google-client-secret" type="password" name="japur_google_client_secret" class="regular-text code" value="" placeholder="<?php echo $google_secret ? 'Secret tersimpan — isi untuk mengganti' : 'Masukkan Client Secret'; ?>" autocomplete="new-password">
                            <div class="japur-api-actions"><button class="button" type="submit">Simpan Kredensial</button></div>
                        </form>
                        <div class="japur-api-scopes"><span class="japur-scope <?php echo $google_connected ? 'active' : ''; ?>">Search Console</span><span class="japur-scope <?php echo ($google_connected && $google_scope) ? 'active' : ''; ?>">Blogger</span></div>
                        <div class="japur-api-actions">
                            <?php if ($google_auth): ?><a class="button button-primary" href="<?php echo esc_url($google_auth); ?>">Hubungkan / Perbarui Google</a><?php else: ?><span class="description">Isi dan simpan Client ID + Secret terlebih dahulu.</span><?php endif; ?>
                            <?php if ($google_connected): ?><a class="button" href="<?php echo esc_url($google_disconnect); ?>">Putuskan</a><?php endif; ?>
                        </div>
                        <p class="description">API Center adalah satu-satunya tempat untuk kredensial Google. OAuth ini dipakai bersama oleh Search Console dan Blogger.</p>
                    </div>
                </section>

                <section class="japur-api-card">
                    <div class="japur-api-card-head"><div class="japur-api-icon openai"><span class="dashicons dashicons-admin-network"></span></div><div><h2>OpenAI API</h2><p>API key bersama untuk generator teks dan gambar.</p></div><span class="japur-api-status <?php echo $openai_key ? 'ok' : 'off'; ?>"><?php echo $openai_key ? 'Tersimpan' : 'Belum diisi'; ?></span></div>
                    <div class="japur-api-body">
                        <form method="post">
                            <?php wp_nonce_field('japur_api_center'); ?><input type="hidden" name="japur_api_action" value="save_openai">
                            <label class="japur-api-label" for="japur-openai-key">OpenAI API Key</label>
                            <div class="japur-api-input"><input id="japur-openai-key" type="password" name="japur_openai_key" class="regular-text code" value="" placeholder="<?php echo $openai_key ? 'Key tersimpan — isi untuk mengganti' : 'sk-…'; ?>" autocomplete="new-password"><button class="button button-primary">Simpan Key</button></div>
                        </form>
                        <form method="post" class="japur-api-test-form"><?php wp_nonce_field('japur_api_center'); ?><input type="hidden" name="japur_api_action" value="test_openai"><button class="button">Tes Koneksi OpenAI</button></form>
                        <div class="japur-api-divider"></div>
                        <form method="post">
                            <?php wp_nonce_field('japur_api_center'); ?><input type="hidden" name="japur_api_action" value="save_openai_admin">
                            <label class="japur-api-label" for="japur-openai-admin-key">OpenAI Admin API Key</label>
                            <div class="japur-api-input"><input id="japur-openai-admin-key" type="password" name="japur_openai_admin_key" class="regular-text code" value="" placeholder="<?php echo $openai_admin_key ? 'Admin Key tersimpan — isi untuk mengganti' : 'sk-admin-…'; ?>" autocomplete="new-password"><button class="button button-primary">Simpan Admin Key</button></div>
                            <p class="description">Dipakai Cost AI untuk OpenAI Costs API organisasi. Disimpan terenkripsi di API Center dan digunakan bersama oleh modul yang membutuhkan akses billing/usage organisasi.</p>
                        </form>
                        <p class="description">Extractor AI membaca key dari API Center ini. Pengaturan model tetap berada di modul Extractor AI.</p>
                    </div>
                </section>

                <section class="japur-api-card">
                    <div class="japur-api-card-head"><div class="japur-api-icon indexnow"><span class="dashicons dashicons-update"></span></div><div><h2>IndexNow API</h2><p>Key untuk pengiriman URL dan verifikasi Key Location.</p></div><span class="japur-api-status <?php echo $indexnow_key ? 'ok' : 'off'; ?>"><?php echo $indexnow_key ? 'Tersimpan' : 'Belum diisi'; ?></span></div>
                    <div class="japur-api-body">
                        <form method="post">
                            <?php wp_nonce_field('japur_api_center'); ?><input type="hidden" name="japur_api_action" value="save_indexnow">
                            <label class="japur-api-label" for="japur-indexnow-key">IndexNow API Key</label>
                            <div class="japur-api-input"><input id="japur-indexnow-key" type="text" name="japur_indexnow_key" class="regular-text code" value="<?php echo esc_attr($indexnow_key); ?>" autocomplete="off"><button class="button button-primary">Simpan Key</button></div>
                        </form>
                        <form method="post" class="japur-api-test-form"><?php wp_nonce_field('japur_api_center'); ?><input type="hidden" name="japur_api_action" value="test_indexnow"><button class="button">Tes IndexNow</button></form>
                        <p class="description">Key tetap menggunakan option dan file Key Location milik Auto Index PRO agar modul lama tidak berubah.</p>
                    </div>
                </section>
            </div>
            <div class="japur-api-note"><strong>Satu pintu API.</strong><span>Google menjadi OAuth bersama. OpenAI dan IndexNow dikelola dari sini sehingga modul tidak perlu menyimpan kredensial terpisah.</span></div>
            <style>
                .japur-api-center{max-width:1100px}.japur-api-hero{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:24px 26px;margin:15px 0 18px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
                .japur-api-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.4px;color:#2271b1;margin-bottom:3px}.japur-api-hero h1{font-size:24px;line-height:1.25;margin:0;color:#1d2327}.japur-api-hero p{margin:7px 0 0;color:#646970;font-size:13px;line-height:1.55}
                .japur-api-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}.japur-api-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;box-shadow:0 2px 7px rgba(0,0,0,.04)}.japur-api-card-head{display:flex;align-items:center;gap:13px;padding:19px 20px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}.japur-api-card-head h2{margin:0;font-size:18px;line-height:1.3;color:#1d2327}.japur-api-card-head p{margin:4px 0 0;color:#646970;font-size:12px;line-height:1.45}.japur-api-icon{width:44px;height:44px;flex:0 0 44px;border-radius:12px;background:#f0f6fc;color:#2271b1;display:flex;align-items:center;justify-content:center}.japur-api-icon .dashicons{font-size:23px;width:23px;height:23px}.japur-api-status{margin-left:auto;white-space:nowrap;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:700;background:#f0f0f1;color:#646970}.japur-api-status.ok{background:#edfaef;color:#187a2f}.japur-api-status.off{background:#fcf0f1;color:#a02222}.japur-api-body{padding:19px 20px}.japur-api-meta{display:flex;justify-content:space-between;gap:15px;padding:9px 0;border-bottom:1px solid #f0f0f1;font-size:12px}.japur-api-meta span{color:#646970}.japur-api-meta strong{color:#1d2327;font-weight:600}.japur-api-scopes{display:flex;gap:7px;flex-wrap:wrap;margin:14px 0}.japur-scope{border:1px solid #dcdcde;border-radius:999px;padding:5px 9px;font-size:11px;color:#646970}.japur-scope.active{border-color:#b7dfc0;background:#edfaef;color:#187a2f}.japur-api-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:15px}.japur-api-label{display:block;font-size:12px;font-weight:600;color:#1d2327;margin-bottom:7px}.japur-api-input{display:flex;gap:8px;align-items:center}.japur-api-input input{width:100%;min-width:0}.japur-api-input .button{flex:0 0 auto}.japur-api-test-form{margin-top:10px}.japur-api-divider{height:1px;background:#e2e4e7;margin:18px 0}.japur-api-body .description{font-size:11px;line-height:1.5;margin:12px 0 0}.japur-api-note{display:flex;gap:8px;align-items:flex-start;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px;padding:15px 18px;margin-top:16px;font-size:12px;line-height:1.5;color:#646970}.japur-api-note strong{color:#1d2327;white-space:nowrap}@media(max-width:600px){.japur-api-grid{grid-template-columns:1fr}.japur-api-card-head{align-items:flex-start;flex-wrap:wrap}.japur-api-status{margin-left:auto}.japur-api-input{align-items:stretch;flex-direction:column}.japur-api-input .button{width:100%}.japur-api-note{display:block}.japur-api-note strong{display:block;margin-bottom:4px}}
            </style>
        </div>
        <?php
    }

    private function api_secret_key() {
        return hash('sha256', AUTH_KEY.'|'.SECURE_AUTH_KEY.'|'.LOGGED_IN_KEY.'|'.NONCE_KEY, true);
    }
    private function encrypt_api_secret($value) {
        $value=(string)$value;
        if($value==='' || strpos($value,'jafenc:v1:')===0 || !function_exists('openssl_encrypt')) return $value;
        $cipher='aes-256-cbc'; $ivlen=openssl_cipher_iv_length($cipher); if(!$ivlen) return $value;
        try{$iv=random_bytes($ivlen);}catch(Exception $e){$iv=openssl_random_pseudo_bytes($ivlen);}
        $enc=openssl_encrypt($value,$cipher,$this->api_secret_key(),OPENSSL_RAW_DATA,$iv);
        return $enc===false?$value:'jafenc:v1:'.base64_encode($iv).':'.base64_encode($enc);
    }
    private function decrypt_api_secret($value) {
        $value=(string)$value;
        if($value==='' || strpos($value,'jafenc:v1:')!==0 || !function_exists('openssl_decrypt')) return $value;
        $parts=explode(':',$value,4); if(count($parts)!==4) return '';
        $iv=base64_decode($parts[2],true); $enc=base64_decode($parts[3],true);
        if($iv===false || $enc===false) return '';
        $plain=openssl_decrypt($enc,'aes-256-cbc',$this->api_secret_key(),OPENSSL_RAW_DATA,$iv);
        return $plain===false?'':$plain;
    }
    private function api_center_dashboard_card() {
        echo '<div class="japur-api-dashboard-card">';
        echo '<div class="japur-api-dashboard-icon"><span class="dashicons dashicons-admin-network"></span></div>';
        echo '<div class="japur-api-dashboard-copy"><div class="japur-api-dashboard-title">API Center <span class="japur-api-new">Baru</span></div>';
        echo '<p>Kelola Google API, OpenAI API, dan IndexNow di satu tempat. Satu pintu untuk semua modul.</p>';
        echo '<a class="japur-settings-link" href="'.esc_url(admin_url('admin.php?page=japur-api-center')).'">Kelola API</a></div>';
        echo '<a class="japur-api-dashboard-arrow" href="'.esc_url(admin_url('admin.php?page=japur-api-center')).'" aria-label="Buka API Center">&rsaquo;</a>';
        echo '</div>';
    }

    public function lead_domain_page() {
        if (class_exists('JapurSuite_Lead_Domain_Auto_Link')) { (new JapurSuite_Lead_Domain_Auto_Link())->settings_page(); }
    }

    public function popup_settings_page() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['japur_popup_save']) && check_admin_referer('japur_popup_settings')) {
            $popup_categories = isset($_POST['popup_categories']) && is_array($_POST['popup_categories'])
                ? array_values(array_unique(array_map('absint', $_POST['popup_categories'])))
                : [];
            $popup_categories = array_values(array_filter($popup_categories));
            update_option('japur_suite_popup_categories', $popup_categories);
            // Sinkronkan dengan option canonical Popup Promo Random v4.2.
            update_option('pgm_category_ids', $popup_categories);
            echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.JapurSuiteToast)window.JapurSuiteToast.show("Pengaturan Popup Promo disimpan.","success");});</script>';
        }
        $popup_categories = get_option('japur_suite_popup_categories', []);
        if (!is_array($popup_categories) || !$popup_categories) {
            $promo = get_category_by_slug('promo');
            $popup_categories = $promo ? [(int) $promo->term_id] : [];
        }
        $all_categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
        $module_on = self::module_enabled('popup');
        ?>
        <div class="wrap japur-popup-settings">
            <div class="japi-head">
                <div>
                    <div class="japi-eyebrow">JAPUR SUITE</div>
                    <h1>Popup Promo</h1>
                </div>
            </div>

            <div class="japi-status <?php echo $module_on ? 'is-on' : 'is-off'; ?>">
                <span class="japi-status-dot"></span>
                <div class="japi-status-label">
                    <strong>Modul <?php echo $module_on ? 'aktif' : 'nonaktif'; ?></strong>
                </div>
                <a class="japi-dashboard-link" href="<?php echo esc_url(admin_url('admin.php?page=japur-suite')); ?>">Dashboard</a>
            </div>

            <form method="post">
                <?php wp_nonce_field('japur_popup_settings'); ?>
                <div class="japi-panel">
                    <div class="japi-panel-head">
                        <div>
                            <h2>Kategori sumber popup</h2>
                            <p>Pilih satu atau beberapa kategori sebagai sumber artikel yang ditampilkan secara acak oleh Popup Promo.</p>
                        </div>
                        <span class="japi-badge"><?php echo esc_html(count($popup_categories)); ?> Kategori</span>
                    </div>

                    <div class="japi-fields">
                        <?php foreach ($all_categories as $cat):
                            $enabled = in_array((int) $cat->term_id, $popup_categories, true);
                            $toggle_id = 'japur-popup-category-' . (int) $cat->term_id;
                        ?>
                            <div class="japi-field-row <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
                                <div class="japi-field-copy">
                                    <div class="japi-field-title"><?php echo esc_html($cat->name); ?></div>
                                    <div class="japi-field-desc">Izinkan artikel dari kategori ini digunakan sebagai sumber popup promo.</div>
                                </div>
                                <label class="japi-switch" for="<?php echo esc_attr($toggle_id); ?>">
                                    <input id="<?php echo esc_attr($toggle_id); ?>" type="checkbox" name="popup_categories[]" value="<?php echo esc_attr($cat->term_id); ?>" <?php checked($enabled, true); ?> aria-label="<?php echo esc_attr($cat->name); ?>">
                                    <span class="japi-switch-track"><span class="japi-switch-thumb"></span></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="japi-panel-foot">
                        <div class="japi-help"><span></span><span>Popup dapat tampil di semua kategori artikel. Jika artikel yang dipilih secara acak sedang dibaca, popup tidak akan ditampilkan.</span></div>
                        <button type="submit" name="japur_popup_save" class="button button-primary button-large">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>

            <div class="japi-marker-box">
                <h3>Catatan</h3>
                <p>Hanya artikel dari kategori yang dipilih yang digunakan sebagai sumber Popup Promo. Pengaturan ini tidak mengubah mekanisme random, tampilan popup, pembatasan satu kali per sesi, atau aturan agar popup tidak menampilkan artikel yang sedang dibaca.</p>
            </div>

            <style>
                .japur-popup-settings{max-width:1080px}
                .japur-popup-settings .japi-head{display:flex;flex-direction:column;justify-content:flex-start;align-items:flex-start;gap:3px;margin:22px 0 18px;text-align:left}
                .japur-popup-settings .japi-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.4px;color:#2271b1;margin-bottom:2px}
                .japur-popup-settings .japi-head h1{font-size:23px;line-height:1.3;margin:0;font-weight:600;color:#1d2327}
                .japur-popup-settings .japi-status{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
                .japur-popup-settings .japi-status-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px;background:#d63638}
                .japur-popup-settings .japi-status.is-on .japi-status-dot{background:#00a32a;box-shadow:0 0 0 4px #edfaef}
                .japur-popup-settings .japi-status strong{display:block;font-size:14px;color:#1d2327}
                .japur-popup-settings .japi-status-label{min-width:0}
                .japur-popup-settings .japi-dashboard-link{margin-left:auto;text-decoration:none;font-weight:600;white-space:nowrap;flex:0 0 auto}
                .japur-popup-settings .japi-panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;box-shadow:0 2px 7px rgba(0,0,0,.04)}
                .japur-popup-settings .japi-panel-head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:22px 24px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}
                .japur-popup-settings .japi-panel-head h2{font-size:18px;margin:0 0 5px;color:#1d2327}
                .japur-popup-settings .japi-panel-head p{margin:0;color:#646970;font-size:13px}
                .japur-popup-settings .japi-badge{font-size:12px;font-weight:700;color:#50575e;background:#f0f0f1;border-radius:999px;padding:6px 10px;white-space:nowrap}
                .japur-popup-settings .japi-fields{padding:0 24px}
                .japur-popup-settings .japi-field-row{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:18px 0;border-bottom:1px solid #eee}
                .japur-popup-settings .japi-field-row:last-child{border-bottom:0}
                .japur-popup-settings .japi-field-copy{min-width:0}
                .japur-popup-settings .japi-field-title{font-size:15px;font-weight:700;color:#1d2327;margin-bottom:4px}
                .japur-popup-settings .japi-field-desc{font-size:13px;line-height:1.5;color:#646970}
                .japur-popup-settings .japi-switch{position:relative;display:block;width:48px;height:28px;flex:0 0 48px;cursor:pointer}
                .japur-popup-settings .japi-switch input{position:absolute;opacity:0;width:1px;height:1px}
                .japur-popup-settings .japi-switch-track{position:absolute;inset:0;border-radius:999px;background:#8c8f94;transition:.2s;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
                .japur-popup-settings .japi-switch-thumb{position:absolute;top:4px;left:4px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
                .japur-popup-settings .japi-switch input:checked + .japi-switch-track{background:#2271b1}
                .japur-popup-settings .japi-switch input:checked + .japi-switch-track .japi-switch-thumb{left:24px}
                .japur-popup-settings .japi-switch input:focus-visible + .japi-switch-track{outline:2px solid #72aee6;outline-offset:2px}
                .japur-popup-settings .japi-field-row.is-disabled .japi-field-title{color:#646970}
                .japur-popup-settings .japi-panel-foot{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 24px;background:#fbfbfc;border-top:1px solid #e2e4e7}
                .japur-popup-settings .japi-help{display:flex;gap:9px;align-items:flex-start;color:#646970;font-size:12px;line-height:1.5;max-width:700px}
                .japur-popup-settings .japi-help span:first-child{font-size:15px;line-height:1.2}
                .japur-popup-settings .japi-marker-box{margin-top:16px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px;padding:18px 20px}
                .japur-popup-settings .japi-marker-box h3{margin:0 0 12px;font-size:14px;color:#1d2327}
                .japur-popup-settings .japi-marker-box p{font-size:12px;color:#646970;line-height:1.55;margin:0}
                @media (max-width:700px){
                    .japur-popup-settings .japi-head{align-items:flex-start}.japur-popup-settings .japi-head h1{font-size:24px}
                    .japur-popup-settings .japi-panel-head,.japur-popup-settings .japi-panel-foot{display:block}.japur-popup-settings .japi-badge{display:inline-block;margin-top:12px}
                    .japur-popup-settings .japi-panel-foot .button{margin-top:15px}.japur-popup-settings .japi-status{align-items:flex-start}.japur-popup-settings .japi-dashboard-link{margin-left:auto}
                    .japur-popup-settings .japi-field-row{gap:14px}.japur-popup-settings .japi-field-desc{font-size:12px}
                }
            </style>
        </div>
        <?php
    }

    public function register_settings() {
        // Pengaturan modul dikendalikan langsung dari toggle pada dashboard Japur Suite.
    }

    public function japi_settings_page() {
        if (!current_user_can('manage_options')) return;

        $fields = [
            'title'   => ['label' => 'Judul', 'desc' => 'Menerapkan nilai dari marker [TITLE] ke judul artikel.'],
            'excerpt' => ['label' => 'Excerpt / Meta Description', 'desc' => 'Menerapkan [DESCRIPTION] ke excerpt dan meta description Yoast.'],
            'content' => ['label' => 'Konten', 'desc' => 'Menerapkan isi artikel dari marker [CONTENT].'],
            'category'=> ['label' => 'Kategori', 'desc' => 'Menerapkan kategori dari marker [CATEGORY].'],
            'tag'     => ['label' => 'Tag', 'desc' => 'Menerapkan tag dari marker [TAGS].'],
            'focus'   => ['label' => 'Focus Keyphrase', 'desc' => 'Menerapkan focus keyphrase dari marker [FOCUS_KEYPHRASE].'],
        ];

        if (isset($_POST['japi_field_save']) && check_admin_referer('japi_field_settings')) {
            foreach (array_keys($fields) as $key) {
                update_option('japur_japi_import_' . $key, isset($_POST['japi_field_' . $key]) ? 1 : 0);
            }
            echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.JapurSuiteToast)window.JapurSuiteToast.show("Pengaturan Javanese Auto Post Importer berhasil disimpan.","success");});</script>';
        }

        $module_on = self::module_enabled('japi');
        ?>
        <div class="wrap japur-japi-settings">
            <div class="japi-head">
                <div>
                    <div class="japi-eyebrow">JAPUR SUITE</div>
                    <h1>Javanese Auto Post Importer</h1>
                </div>
                <div class="japi-version">v2.5.3</div>
            </div>

            <div class="japi-status <?php echo $module_on ? 'is-on' : 'is-off'; ?>">
                <span class="japi-status-dot"></span>
                <div class="japi-status-label">
                    <strong>Modul <?php echo $module_on ? 'aktif' : 'nonaktif'; ?></strong>
                </div>
                <a class="japi-dashboard-link" href="<?php echo esc_url(admin_url('admin.php?page=japur-suite')); ?>">Dashboard</a>
            </div>

            <form method="post">
                <?php wp_nonce_field('japi_field_settings'); ?>
                <div class="japi-panel">
                    <div class="japi-panel-head">
                        <div>
                            <h2>Field yang diproses</h2>
                            <p>Matikan field tertentu jika nilainya tidak ingin menimpa data yang sudah ada pada artikel.</p>
                        </div>
                        <span class="japi-badge">6 Pengaturan</span>
                    </div>

                    <div class="japi-fields">
                        <?php foreach ($fields as $key => $field):
                            $enabled = (bool) get_option('japur_japi_import_' . $key, 1);
                            $toggle_id = 'japi-field-' . $key;
                        ?>
                            <div class="japi-field-row <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
                                <div class="japi-field-copy">
                                    <div class="japi-field-title"><?php echo esc_html($field['label']); ?></div>
                                    <div class="japi-field-desc"><?php echo esc_html($field['desc']); ?></div>
                                </div>
                                <label class="japi-switch" for="<?php echo esc_attr($toggle_id); ?>">
                                    <input id="<?php echo esc_attr($toggle_id); ?>" type="checkbox" name="japi_field_<?php echo esc_attr($key); ?>" value="1" <?php checked($enabled, true); ?> aria-label="<?php echo esc_attr($field['label']); ?>">
                                    <span class="japi-switch-track"><span class="japi-switch-thumb"></span></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="japi-panel-foot">
                        <div class="japi-help"><span></span><span>Marker tetap dibaca parser. Jika field OFF, nilainya hanya tidak diterapkan ke post sehingga data lama tetap dipertahankan.</span></div>
                        <button type="submit" name="japi_field_save" class="button button-primary button-large">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>

            <div class="japi-marker-box">
                <h3>Marker yang didukung</h3>
                <div class="japi-markers">
                    <code>[TITLE]</code>
                    <code>[DESCRIPTION]</code>
                    <code>[CATEGORY]</code>
                    <code>[TAGS]</code>
                    <code>[FOCUS_KEYPHRASE]</code>
                    <code>[CONTENT]</code>
                </div>
                <p>Pengaturan di atas hanya mengontrol penerapan hasil import. Mekanisme parser, AJAX, Gutenberg, nonce, dan integrasi Yoast JAPI v2.5.3 tetap dipertahankan.</p>
            </div>

            <style>
                .japur-japi-settings{max-width:1080px}
                .japi-head{display:flex;flex-direction:column;justify-content:flex-start;align-items:flex-start;gap:3px;margin:22px 0 18px;text-align:left}
                .japi-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.4px;color:#2271b1;margin-bottom:2px}
                .japi-head h1{font-size:23px;line-height:1.3;margin:0;font-weight:600;color:#1d2327}
                .japi-head p{display:none}
                .japi-version{background:#f0f6fc;color:#135e96;border:1px solid #c5d9ed;border-radius:999px;padding:7px 12px;font-weight:700;font-size:13px;white-space:nowrap;display:none}
                .japi-status{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
                .japi-status-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px;background:#d63638}
                .japi-status.is-on .japi-status-dot{background:#00a32a;box-shadow:0 0 0 4px #edfaef}
                .japi-status strong{display:block;font-size:14px;color:#1d2327}
                .japi-status-label{min-width:0}
                .japi-dashboard-link{margin-left:auto;text-decoration:none;font-weight:600;white-space:nowrap;flex:0 0 auto}
                .japi-panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;box-shadow:0 2px 7px rgba(0,0,0,.04)}
                .japi-panel-head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:22px 24px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}
                .japi-panel-head h2{font-size:18px;margin:0 0 5px;color:#1d2327}
                .japi-panel-head p{margin:0;color:#646970;font-size:13px}
                .japi-badge{font-size:12px;font-weight:700;color:#50575e;background:#f0f0f1;border-radius:999px;padding:6px 10px;white-space:nowrap}
                .japi-fields{padding:0 24px}
                .japi-field-row{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:18px 0;border-bottom:1px solid #eee}
                .japi-field-row:last-child{border-bottom:0}
                .japi-field-copy{min-width:0}
                .japi-field-title{font-size:15px;font-weight:700;color:#1d2327;margin-bottom:4px}
                .japi-field-desc{font-size:13px;line-height:1.5;color:#646970}
                .japi-switch{position:relative;display:block;width:48px;height:28px;flex:0 0 48px;cursor:pointer}
                .japi-switch input{position:absolute;opacity:0;width:1px;height:1px}
                .japi-switch-track{position:absolute;inset:0;border-radius:999px;background:#8c8f94;transition:.2s;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
                .japi-switch-thumb{position:absolute;top:4px;left:4px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
                .japi-switch input:checked + .japi-switch-track{background:#2271b1}
                .japi-switch input:checked + .japi-switch-track .japi-switch-thumb{left:24px}
                .japi-switch input:focus-visible + .japi-switch-track{outline:2px solid #72aee6;outline-offset:2px}
                .japi-field-row.is-disabled .japi-field-title{color:#646970}
                .japi-panel-foot{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 24px;background:#fbfbfc;border-top:1px solid #e2e4e7}
                .japi-help{display:flex;gap:9px;align-items:flex-start;color:#646970;font-size:12px;line-height:1.5;max-width:700px}
                .japi-help span:first-child{font-size:15px;line-height:1.2}
                .japi-marker-box{margin-top:16px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px;padding:18px 20px}
                .japi-marker-box h3{margin:0 0 12px;font-size:14px;color:#1d2327}
                .japi-markers{display:flex;flex-wrap:wrap;gap:8px}
                .japi-markers code{background:#fff;border:1px solid #dcdcde;border-radius:7px;padding:6px 9px;color:#3c434a;font-size:12px}
                .japi-marker-box p{font-size:12px;color:#646970;line-height:1.55;margin:13px 0 0}
                @media (max-width:700px){
                    .japi-head{align-items:flex-start}.japi-head h1{font-size:24px}.japi-version{display:none}
                    .japi-panel-head,.japi-panel-foot{display:block}.japi-badge{display:inline-block;margin-top:12px}
                    .japi-panel-foot .button{margin-top:15px}.japi-status{align-items:flex-start}.japi-dashboard-link{margin-left:auto}
                    .japi-field-row{gap:14px}.japi-field-desc{font-size:12px}
                }
            </style>
        </div>
        <?php
    }

    public function webp_settings_page() {
        if (!current_user_can('manage_options')) return;

        $defaults = [
            'max_size_kb'          => 50,
            'max_width'            => 1200,
            'convert_webp'         => 1,
            'watermark'            => 1,
            'watermark_text'       => '',
            'watermark_position'   => 'bottom_right',
            'watermark_transparency' => 8,
            'filename_title'       => 1,
            'alt_title'            => 1,
            'attachment_title'     => 1,
            'caption'              => 1,
            'description'          => 1,
        ];
        $saved = get_option('japur_webp_settings', []);
        $settings = wp_parse_args(is_array($saved) ? $saved : [], $defaults);

        if (isset($_POST['japur_webp_save']) && check_admin_referer('japur_webp_settings')) {
            $settings['max_size_kb'] = max(1, min(10240, absint($_POST['max_size_kb'] ?? $defaults['max_size_kb'])));
            $settings['max_width'] = max(100, min(10000, absint($_POST['max_width'] ?? $defaults['max_width'])));
            $settings['convert_webp'] = !empty($_POST['convert_webp']) ? 1 : 0;
            $settings['watermark'] = !empty($_POST['watermark']) ? 1 : 0;
            $settings['watermark_text'] = isset($_POST['watermark_text']) ? sanitize_text_field(wp_unslash($_POST['watermark_text'])) : '';
            $allowed_positions = ['bottom_right', 'bottom_left', 'top_right', 'top_left'];
            $settings['watermark_position'] = (isset($_POST['watermark_position']) && in_array($_POST['watermark_position'], $allowed_positions, true)) ? $_POST['watermark_position'] : $defaults['watermark_position'];
            $settings['watermark_transparency'] = max(0, min(100, absint($_POST['watermark_transparency'] ?? $defaults['watermark_transparency'])));
            foreach (['filename_title','alt_title','attachment_title','caption','description'] as $key) {
                $settings[$key] = !empty($_POST[$key]) ? 1 : 0;
            }
            update_option('japur_webp_settings', $settings);
            echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.JapurSuiteToast)window.JapurSuiteToast.show("Pengaturan Auto WebP + Watermark berhasil disimpan.","success");});</script>';
        }

        $module_on = self::module_enabled('webp');
        $watermark_label = $settings['watermark_text'] !== '' ? $settings['watermark_text'] : 'Nama website';
        ?>
        <div class="wrap japur-webp-settings">
            <style>
                .japur-webp-settings{max-width:1080px}
                .jwp-head{display:flex;flex-direction:column;justify-content:flex-start;align-items:flex-start;gap:3px;margin:22px 0 18px;text-align:left}
                .jwp-eyebrow{font-size:12px;font-weight:800;letter-spacing:.12em;color:#2271b1;margin-bottom:4px}
                .jwp-head h1{margin:0;font-size:23px;line-height:1.3;font-weight:600;color:#1d2327}
                .jwp-head p,.jwp-version{display:none}
                .jwp-status{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
                .jwp-status-dot{width:10px;height:10px;border-radius:50%;background:#d63638;box-shadow:none;flex:0 0 10px}
                .jwp-status.is-on .jwp-status-dot{background:#00a32a;box-shadow:0 0 0 4px #edfaef}
                .jwp-status-copy{min-width:0}.jwp-status-copy strong{display:block;font-size:14px;color:#1d2327}
                .jwp-status-copy span{display:none}.jwp-status a{margin-left:auto;text-decoration:none;font-weight:600;white-space:nowrap;flex:0 0 auto}
                .jwp-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.03);overflow:hidden;margin-bottom:16px}
                .jwp-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:18px 20px;border-bottom:1px solid #e2e4e7}
                .jwp-card-head h2{margin:0;font-size:16px;line-height:1.4;font-weight:600;color:#1d2327}.jwp-card-head p{margin:4px 0 0;color:#646970;font-size:12px;line-height:1.5}
                .jwp-badge{background:#f0f6fc;border:1px solid #c5d9ed;border-radius:7px;padding:5px 8px;font-size:11px;color:#2271b1;white-space:nowrap}
                .jwp-fields{padding:0 20px}.jwp-row{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:16px 0;border-bottom:1px solid #f0f0f1}.jwp-row:last-child{border-bottom:0}
                .jwp-copy{min-width:0}.jwp-title{font-weight:600;color:#1d2327;font-size:14px}.jwp-desc{margin-top:4px;color:#646970;font-size:12px;line-height:1.5}
                .jwp-input{width:100%;max-width:360px}.jwp-input input,.jwp-input select{width:100%;min-height:38px;border:1px solid #c3c4c7;border-radius:8px;padding:7px 10px}.jwp-input small{display:block;color:#646970;margin-top:5px;font-size:11px}
                .jwp-switch{position:relative;display:block;width:48px;height:28px;flex:0 0 48px;cursor:pointer}.jwp-switch input{position:absolute;opacity:0;width:1px;height:1px}.jwp-track{position:absolute;inset:0;border-radius:999px;background:#8c8f94;transition:.2s;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}.jwp-thumb{position:absolute;top:4px;left:4px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}.jwp-switch input:checked + .jwp-track{background:#2271b1}.jwp-switch input:checked + .jwp-track .jwp-thumb{left:24px}.jwp-switch input:focus-visible + .jwp-track{outline:2px solid #72aee6;outline-offset:2px}
                .jwp-info{padding:17px 20px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px;margin-bottom:16px}.jwp-info h3{margin:0 0 7px;font-size:14px}.jwp-info p{font-size:12px;line-height:1.55;color:#646970;margin:0}.jwp-badges{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}.jwp-badges .jwp-badge{background:#fff}
                .jwp-footer-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.03);overflow:hidden}
                .jwp-footer-note{padding:17px 20px}.jwp-footer-note h3{margin:0 0 6px;font-size:14px;color:#1d2327}.jwp-footer-note p{margin:0;font-size:12px;line-height:1.55;color:#646970}
                .jwp-actions{display:flex;align-items:center;justify-content:flex-end;gap:16px;padding:15px 20px;background:#fbfbfc;border-top:1px solid #e2e4e7}.jwp-actions .description{margin:0 auto 0 0;font-size:12px;color:#646970}.jwp-actions .button{margin:0}
                @media(max-width:650px){.jwp-status{align-items:flex-start}.jwp-status a{margin-left:auto}.jwp-card-head{padding:16px}.jwp-fields{padding:0 16px}.jwp-row{gap:14px}.jwp-input{max-width:55%}.jwp-actions{display:block}.jwp-actions .button{margin-top:12px;width:100%}}
            </style>

            <div class="jwp-head">
                <div>
                    <div class="jwp-eyebrow">JAPUR SUITE</div>
                    <h1>Auto WebP + Watermark</h1>
                </div>
            </div>

            <div class="japi-status <?php echo $module_on ? 'is-on' : 'is-off'; ?>">
                <span class="japi-status-dot"></span>
                <div class="japi-status-label">
                    <strong>Modul <?php echo $module_on ? 'aktif' : 'nonaktif'; ?></strong>
                </div>
                <a class="japi-dashboard-link" href="<?php echo esc_url(admin_url('admin.php?page=japur-suite')); ?>">Dashboard</a>
            </div>

            <form method="post">
                <?php wp_nonce_field('japur_webp_settings'); ?>
                <div class="jwp-card">
                    <div class="jwp-card-head"><div><h2>Optimasi Gambar</h2><p>Atur konversi WebP dan ukuran gambar saat upload.</p></div><span class="jwp-badge">3 Pengaturan</span></div>
                    <div class="jwp-fields">
                        <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Konversi WebP</div><div class="jwp-desc">Ubah gambar JPG/PNG menjadi WebP saat upload.</div></div><label class="jwp-switch"><input type="checkbox" name="convert_webp" value="1" <?php checked($settings['convert_webp'],1); ?>><span class="jwp-track"><span class="jwp-thumb"></span></span></label></div>
                        <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Maksimal ukuran WebP</div><div class="jwp-desc">Plugin menurunkan kualitas dari 80 sampai 10 untuk mengejar batas ini.</div></div><div class="jwp-input"><input type="number" name="max_size_kb" min="1" max="10240" value="<?php echo esc_attr($settings['max_size_kb']); ?>"><small>KB</small></div></div>
                        <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Maksimal lebar gambar</div><div class="jwp-desc">Gambar yang lebih lebar akan diperkecil secara proporsional.</div></div><div class="jwp-input"><input type="number" name="max_width" min="100" max="10000" value="<?php echo esc_attr($settings['max_width']); ?>"><small>piksel</small></div></div>
                    </div>
                </div>

                <div class="jwp-card">
                    <div class="jwp-card-head"><div><h2>Watermark</h2><p>Atur tampilan watermark pada gambar hasil pemrosesan.</p></div><span class="jwp-badge">4 Pengaturan</span></div>
                    <div class="jwp-fields">
                        <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Watermark</div><div class="jwp-desc">Tampilkan watermark pada gambar.</div></div><label class="jwp-switch"><input type="checkbox" name="watermark" value="1" <?php checked($settings['watermark'],1); ?>><span class="jwp-track"><span class="jwp-thumb"></span></span></label></div>
                        <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Teks watermark</div><div class="jwp-desc">Kosongkan untuk otomatis memakai nama website.</div></div><div class="jwp-input"><input type="text" name="watermark_text" value="<?php echo esc_attr($settings['watermark_text']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"></div></div>
                        <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Posisi</div><div class="jwp-desc">Tentukan letak watermark pada gambar.</div></div><div class="jwp-input"><select name="watermark_position"><option value="bottom_right" <?php selected($settings['watermark_position'],'bottom_right'); ?>>Kanan bawah</option><option value="bottom_left" <?php selected($settings['watermark_position'],'bottom_left'); ?>>Kiri bawah</option><option value="top_right" <?php selected($settings['watermark_position'],'top_right'); ?>>Kanan atas</option><option value="top_left" <?php selected($settings['watermark_position'],'top_left'); ?>>Kiri atas</option></select></div></div>
                        <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Transparansi</div><div class="jwp-desc">0% = solid, 100% = sangat transparan.</div></div><div class="jwp-input"><input type="number" name="watermark_transparency" min="0" max="100" value="<?php echo esc_attr($settings['watermark_transparency']); ?>"><small>%</small></div></div>
                    </div>
                </div>

                <div class="jwp-card">
                    <div class="jwp-card-head"><div><h2>Metadata & Nama File</h2><p>Pilih data attachment mana yang otomatis mengikuti artikel.</p></div><span class="jwp-badge">5 Pengaturan</span></div>
                    <div class="jwp-fields">
                        <?php
                        $meta_fields = [
                            'filename_title' => ['Nama file berdasarkan judul artikel','File upload diberi nama dari judul artikel jika konteks artikel tersedia.'],
                            'attachment_title' => ['Judul attachment','Judul media mengikuti judul artikel.'],
                            'alt_title' => ['ALT berdasarkan judul artikel','ALT image otomatis memakai judul artikel.'],
                            'caption' => ['Caption / Keterangan','Caption otomatis menggunakan format “Ilustrasi: Judul Artikel”.'],
                            'description' => ['Deskripsi','Deskripsi attachment menggunakan Excerpt artikel; jika kosong, memakai judul.'],
                        ];
                        foreach ($meta_fields as $key => $field):
                        ?>
                            <div class="jwp-row"><div class="jwp-copy"><div class="jwp-title"><?php echo esc_html($field[0]); ?></div><div class="jwp-desc"><?php echo esc_html($field[1]); ?></div></div><label class="jwp-switch"><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($settings[$key],1); ?>><span class="jwp-track"><span class="jwp-thumb"></span></span></label></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="jwp-info"><h3>Konfigurasi aktif</h3><p>Pengaturan yang sedang aktif untuk pemrosesan gambar berikutnya.</p><div class="jwp-badges"><span class="jwp-badge">Target <?php echo esc_html($settings['max_size_kb']); ?> KB</span><span class="jwp-badge">Lebar <?php echo esc_html($settings['max_width']); ?> px</span><span class="jwp-badge">Watermark: <?php echo esc_html($watermark_label); ?></span></div></div>

                <div class="jwp-footer-card">
                    <div class="jwp-footer-note"><h3>Catatan</h3><p>Perubahan pengaturan berlaku untuk upload gambar berikutnya. File yang sudah ada tidak diproses ulang otomatis.</p></div>
                    <div class="jwp-actions"><p class="description">Pastikan pengaturan sudah sesuai sebelum menyimpan.</p><button type="submit" name="japur_webp_save" class="button button-primary button-large">Simpan Pengaturan</button></div>
                </div>
            </form>
        </div>
        <?php
    }

    public function dashboard() {
        if (!current_user_can('manage_options')) return;
        $dashboard_saved = false;
        if (isset($_POST['japur_suite_save']) && check_admin_referer('japur_suite_dashboard')) {
            foreach (['japi','date','webp','popup','rpi','lead','index','extractor'] as $module_key) {
                update_option('japur_suite_module_' . $module_key, isset($_POST['module_' . $module_key]) ? 1 : 0);
            }
            $dashboard_saved = true;
        }
        $module_states = [];
        foreach (['japi','date','webp','popup','rpi','lead','index','extractor'] as $module_key) {
            $module_states[$module_key] = (int) get_option('japur_suite_module_' . $module_key, 1);
        }
        $popup_categories = get_option('japur_suite_popup_categories', []);
        if (!is_array($popup_categories) || !$popup_categories) {
            $promo = get_category_by_slug('promo');
            $popup_categories = $promo ? [(int) $promo->term_id] : [];
        }
        $all_categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
        ?>
        <div class="wrap japur-dashboard">
            <div class="japur-suite-hero">
                <div class="japur-suite-brand"><span>JaPur Suite Production</span></div>
                <div class="japur-suite-tagline">Malas Boleh, Asal Produktif.</div>
                <div class="japur-suite-subtitle">Tools WordPress untuk mempercepat pekerjaan tanpa bikin kerjaan tambah ribet.</div>
            </div>
            <?php if ($dashboard_saved): ?>
                <script>document.addEventListener('DOMContentLoaded',function(){if(window.JapurSuiteToast)window.JapurSuiteToast.show('Pengaturan Japur Suite disimpan.','success');});</script>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('japur_suite_dashboard'); ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;max-width:1100px;margin-top:20px">
                    <?php $this->api_center_dashboard_card(); ?>
                    <?php $this->module_card('date','Auto Update Post Date','Mengganti bulan dan tahun pada judul, SEO title, Open Graph, dan breadcrumb',admin_url('admin.php?page=auto-date-title'),$module_states['date']); ?>
                    <?php $this->module_card('rpi','RPI Pro','Menyisipkan hingga tiga related post berdasarkan kategori atau random pada posisi paragraf tertentu.',admin_url('admin.php?page=rpi-settings'),$module_states['rpi']); ?>
                    <?php $this->module_card('lead','Lead Domain Auto Link','Membuat nama domain menjadi link aktif pada lead/paragraf pertama artikel, termasuk proses massal bertahap.',admin_url('admin.php?page=lead-domain-auto-link'),$module_states['lead']); ?>
                    <?php $this->module_card('popup','Popup Promo Random','Menampilkan satu artikel secara acak dari kategori terpilih, satu kali per sesi.',admin_url('admin.php?page=popup-promo'),$module_states['popup']); ?>
                    <?php $this->module_card('japi','Javanese Auto Post Importer','Import artikel terstruktur ke Gutenberg, termasuk kategori, tag, focus keyphrase, meta description, dan konten.',admin_url('admin.php?page=japi-settings'), $module_states['japi']); ?>
                    <?php $this->module_card('webp','Auto WebP + Watermark','Resize maksimal 1200px, watermark, konversi WebP hingga target 50KB, rename, ALT, caption, dan deskripsi gambar.',admin_url('admin.php?page=webp-settings'),$module_states['webp']); ?>
                    <?php $this->module_card('index','Japur Auto Index PRO','Antrean URL, IndexNow, retry otomatis, log, Google Search Console, sitemap, URL Inspection, dan Search Analytics.',admin_url('admin.php?page=japur-auto-index-pro'),$module_states['index']); ?>
                    <?php $this->module_card('extractor','Japur Extractor AI','Ekstraksi artikel lokal tanpa AI, generator artikel, thumbnail, WordPress, dan Blogger.',admin_url('admin.php?page=jaf-extractor'),$module_states['extractor']); ?>
                </div>
                <p style="margin-top:18px"><button type="submit" name="japur_suite_save" class="button button-primary button-large">Simpan Pengaturan Modul</button></p>
            </form>
            <div class="japur-mageran-note">
                <div class="japur-note-title">Catatan Mageran</div>
                <div class="japur-note-quote">Kalau bisa cepat, kenapa harus lama? Kalau bisa mudah, kenapa harus susah? </div>
                <div class="japur-note-signature">JaPur Suite Production — kerja boleh serius, proses tetap seru.</div>
            </div>
            <style>
                .japur-dashboard{max-width:1100px}
                .japur-suite-hero{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:24px 26px;margin:15px 0 22px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
                .japur-suite-brand{font-size:23px;font-weight:700;line-height:1.25;color:#1d2327;letter-spacing:-.2px}
                .japur-suite-brand span{margin-left:2px}
                .japur-suite-tagline{margin-top:7px;font-size:16px;font-weight:600;color:#2271b1}
                .japur-suite-subtitle{margin-top:5px;font-size:13px;line-height:1.55;color:#646970}
                .japur-mageran-note{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px 20px;max-width:1060px;margin-top:22px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
                .japur-note-title{font-size:15px;font-weight:600;color:#1d2327;margin-bottom:6px}
                .japur-note-quote{font-size:13px;line-height:1.55;color:#50575e}
                .japur-note-signature{margin-top:8px;font-size:12px;font-weight:600;color:#2271b1}
                .japur-api-dashboard-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.05);display:flex;align-items:center;gap:14px;position:relative;min-height:100px}.japur-api-dashboard-icon{width:52px;height:52px;flex:0 0 52px;border-radius:13px;background:#eef6ff;color:#2271b1;display:flex;align-items:center;justify-content:center}.japur-api-dashboard-icon .dashicons{font-size:28px;width:28px;height:28px}.japur-api-dashboard-copy{min-width:0;padding-right:28px}.japur-api-dashboard-title{font-size:18px;font-weight:700;line-height:1.35;color:#1d2327}.japur-api-dashboard-copy p{margin:8px 0 6px;line-height:1.55;color:#50575e}.japur-api-new{display:inline-block;margin-left:5px;padding:3px 8px;border-radius:999px;background:#edfaef;color:#187a2f;font-size:11px;vertical-align:middle}.japur-api-dashboard-arrow{position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:34px;line-height:1;text-decoration:none;color:#50575e}.japur-api-dashboard-card:hover{border-color:#b8c7d6}.japur-api-dashboard-card:focus-within{outline:2px solid #72aee6;outline-offset:2px}@media(max-width:600px){.japur-api-dashboard-card{align-items:flex-start}.japur-api-dashboard-arrow{top:28px;transform:none}}
                .japur-toggle input{position:absolute;opacity:0;width:1px;height:1px;margin:0}
                .japur-toggle-track{position:absolute;inset:0;border-radius:24px;background:#8c8f94;transition:.2s;cursor:pointer}
                .japur-toggle-thumb{position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 2px rgba(0,0,0,.2)}
                .japur-toggle input:checked + .japur-toggle-track{background:#2271b1}
                .japur-toggle input:checked + .japur-toggle-track .japur-toggle-thumb{left:25px}
            </style>

        </div>
        <?php
    }

    public static function module_enabled($key) {
        return (bool) get_option('japur_suite_module_' . sanitize_key($key), 1);
    }

    private function module_card($key,$title,$desc,$settings_url,$enabled) {
        $state = $enabled ? 'ON' : 'OFF';
        $toggle_id = 'japur-toggle-' . sanitize_key($key);
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.05);display:flex;flex-direction:column;min-height:0">';
        echo '<div style="display:flex;justify-content:space-between;gap:12px;align-items:center">';
        echo '<h2 style="margin:0;font-size:18px;line-height:1.35">' . esc_html($title) . '</h2>';
        echo '<label for="' . esc_attr($toggle_id) . '" class="japur-toggle" style="position:relative;display:inline-block;width:46px;height:24px;flex:0 0 46px;cursor:pointer" title="' . esc_attr($state) . '">';
        echo '<input id="' . esc_attr($toggle_id) . '" type="checkbox" name="module_' . esc_attr($key) . '" value="1" ' . checked($enabled,1,false) . '>';
        echo '<span class="japur-toggle-track"><span class="japur-toggle-thumb"></span></span></label>';
        echo '</div>';
        echo '<p style="margin:10px 0 6px;line-height:1.55">' . esc_html($desc) . '</p>';
        echo '<div style="margin-top:0;padding-top:0">';
        if ($settings_url) {
            echo '<a href="' . esc_url($settings_url) . '" class="japur-settings-link">Pengaturan</a>';
        } else {
            echo '<span class="japur-settings-link japur-settings-empty">Pengaturan</span>';
        }
        echo '</div></div>';
    }

    private function card($title,$desc,$foot) {
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)">';
        echo '<h2 style="margin-top:0;font-size:18px">'.esc_html($title).'</h2>';
        echo '<p>'.esc_html($desc).'</p>';
        echo '<p><strong>'.esc_html($foot).'</strong></p></div>';
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
