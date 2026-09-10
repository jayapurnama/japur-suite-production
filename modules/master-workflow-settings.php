<?php
/**
 * Module: JaPur Master Workflow Settings
 * Description: Pengaturan terpusat untuk perilaku aman workflow JaPur Suite.
 * Module Version: 1.4.1
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Japur_Master_Workflow_Settings')) {
class Japur_Master_Workflow_Settings {
    const OPTION = 'japur_master_workflow_settings';

    public static function defaults() {
        return [
            'workflow_profile' => 'custom',
            'background_button_label' => 1,
            'background_auto_publish_single' => 1,
            'single_hide_thumbnail_after_success' => 1,
            'single_hide_publish_after_success' => 1,
            'single_wp_background_hide_workflow_after_success' => 0,
            'single_wp_foreground_hide_workflow_after_success' => 0,
            'single_blogger_background_hide_workflow_after_success' => 0,
            'single_blogger_foreground_hide_workflow_after_success' => 0,
            'reset_preflight_on_rebuild' => 1,
            'background_resume_after_reload' => 1,
            'safe_mode' => 1,
            'retry_enabled' => 1,
            'retry_article' => 1,
            'retry_thumbnail' => 1,
            'retry_publish' => 1,
            'retry_max_attempts' => 1,
            'diagnostic_enabled' => 1,
            'diagnostic_level' => 'standard',
            'diagnostic_show_http' => 1,
            'diagnostic_show_stage' => 1,
            'diagnostic_show_target' => 1,
            'diagnostic_show_retry' => 1,
            'diagnostic_show_message' => 1,
            'stage_controller_enabled' => 1,
            'stage_show_extract' => 1,
            'stage_show_article' => 1,
            'stage_show_thumbnail' => 1,
            'stage_show_publish' => 1,
            'multi_failure_policy' => 'continue',
            'safety_guard_enabled' => 1,
            'safety_require_preflight' => 1,
            'safety_prevent_duplicate_click' => 1,
            'safety_block_active_background' => 1,
            'safety_validate_before_start' => 1,
            'developer_mode_enabled' => 0,
            'developer_show_run_id' => 1,
            'developer_show_job_id' => 1,
            'developer_show_target' => 1,
            'developer_show_stage' => 1,
            'developer_show_retry_count' => 1,
            'developer_show_http' => 1,
            'developer_show_timestamp' => 1,
            'developer_show_job_state' => 1,
            'developer_show_diagnostic' => 1,
            'developer_copy_button' => 1,
            'developer_max_log_entries' => 50,
        ];
    }

    public static function get_all() {
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    public static function enabled($key) {
        $o = self::get_all();
        return !empty($o[$key]);
    }

    public static function profile_presets() {
        $d = self::defaults();
        return [
            'standard' => $d,
            'safe' => array_merge($d, [
                'workflow_profile' => 'safe',
                'background_auto_publish_single' => 0,
                'background_resume_after_reload' => 1,
                'retry_enabled' => 1,
                'retry_article' => 1,
                'retry_thumbnail' => 1,
                'retry_publish' => 1,
                'retry_max_attempts' => 2,
                'diagnostic_enabled' => 1,
                'diagnostic_level' => 'detailed',
                'diagnostic_show_http' => 1,
                'diagnostic_show_stage' => 1,
                'diagnostic_show_target' => 1,
                'diagnostic_show_retry' => 1,
                'diagnostic_show_message' => 1,
                'stage_controller_enabled' => 1,
                'stage_show_extract' => 1,
                'stage_show_article' => 1,
                'stage_show_thumbnail' => 1,
                'stage_show_publish' => 1,
                'multi_failure_policy' => 'continue',
                'safety_guard_enabled' => 1,
                'safety_require_preflight' => 1,
                'safety_prevent_duplicate_click' => 1,
                'safety_block_active_background' => 1,
                'safety_validate_before_start' => 1,
            ]),
            'fast' => array_merge($d, [
                'workflow_profile' => 'fast',
                'diagnostic_level' => 'basic',
                'diagnostic_show_http' => 0,
                'diagnostic_show_message' => 1,
                'stage_controller_enabled' => 1,
                'stage_show_extract' => 0,
                'stage_show_article' => 1,
                'stage_show_thumbnail' => 1,
                'stage_show_publish' => 1,
                'safety_guard_enabled' => 1,
            ]),
            'auto' => array_merge($d, [
                'workflow_profile' => 'auto',
                'background_button_label' => 1,
                'background_auto_publish_single' => 1,
                'background_resume_after_reload' => 1,
                'retry_enabled' => 1,
                'retry_article' => 1,
                'retry_thumbnail' => 1,
                'retry_publish' => 1,
                'retry_max_attempts' => 2,
                'diagnostic_enabled' => 1,
                'diagnostic_level' => 'standard',
                'diagnostic_show_http' => 1,
                'diagnostic_show_stage' => 1,
                'diagnostic_show_target' => 1,
                'diagnostic_show_retry' => 1,
                'diagnostic_show_message' => 1,
                'multi_failure_policy' => 'continue',
                'safety_guard_enabled' => 1,
                'safety_require_preflight' => 1,
                'safety_prevent_duplicate_click' => 1,
                'safety_block_active_background' => 1,
                'safety_validate_before_start' => 1,
            ]),
        ];
    }

    private static function normalize_profile($profile) {
        $profile = sanitize_key((string)$profile);
        return in_array($profile, ['standard','safe','fast','auto','custom'], true) ? $profile : 'custom';
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'menu'], 12);
        add_action('admin_init', [$this, 'save']);
    }

    public function menu() {
        add_submenu_page('japur-suite', 'Master Workflow', 'Master Workflow', 'manage_options', 'japur-master-workflow', [$this, 'page']);
    }

    public function save() {
        if (!current_user_can('manage_options')) return;

        if (!empty($_POST['japur_master_workflow_apply_profile'])) {
            check_admin_referer('japur_master_workflow_apply_profile');
            $profile = self::normalize_profile($_POST['japur_master_workflow_profile'] ?? 'custom');
            if ($profile === 'custom') {
                add_settings_error('japur_master_workflow', 'profile_custom', 'Profil Custom menggunakan pengaturan yang sedang aktif.', 'updated');
                return;
            }
            $presets = self::profile_presets();
            $preset = $presets[$profile] ?? self::defaults();
            update_option(self::OPTION, $preset, false);
            add_settings_error('japur_master_workflow', 'profile_applied', 'Profil workflow berhasil diterapkan: ' . ucfirst($profile) . '.', 'updated');
            return;
        }

        if (!empty($_POST['japur_master_workflow_reset'])) {
            check_admin_referer('japur_master_workflow_reset');
            update_option(self::OPTION, self::defaults(), false);
            add_settings_error('japur_master_workflow', 'reset', 'Master Workflow dikembalikan ke konfigurasi aman bawaan.', 'updated');
            return;
        }

        if (empty($_POST['japur_master_workflow_save'])) return;
        check_admin_referer('japur_master_workflow_save');
        $keys = array_keys(self::defaults());
        $out = [];
        foreach ($keys as $key) $out[$key] = !empty($_POST['japur_master_workflow'][$key]) ? 1 : 0;
        $max = absint($_POST['japur_master_workflow']['retry_max_attempts'] ?? 1);
        $out['retry_max_attempts'] = in_array($max, [1,2,3], true) ? $max : 1;
        $level = sanitize_key($_POST['japur_master_workflow']['diagnostic_level'] ?? 'standard');
        $out['diagnostic_level'] = in_array($level, ['basic','standard','detailed'], true) ? $level : 'standard';
        $policy = sanitize_key($_POST['japur_master_workflow']['multi_failure_policy'] ?? 'continue');
        $out['multi_failure_policy'] = in_array($policy, ['continue','stop'], true) ? $policy : 'continue';
        $devmax = absint($_POST['japur_master_workflow']['developer_max_log_entries'] ?? 50);
        $out['developer_max_log_entries'] = in_array($devmax, [25,50,100], true) ? $devmax : 50;
        $out['workflow_profile'] = 'custom';
        update_option(self::OPTION, $out, false);
        add_settings_error('japur_master_workflow', 'saved', 'Pengaturan Master Workflow berhasil disimpan.', 'updated');
    }

    private function toggle($key, $title, $desc) {
        $o = self::get_all();
        $checked = !empty($o[$key]) ? ' checked' : '';
        echo '<label class="jmw-toggle"><input type="checkbox" name="japur_master_workflow[' . esc_attr($key) . ']" value="1"' . $checked . '><span class="jmw-track"><span class="jmw-thumb"></span></span><span class="jmw-copy"><strong>' . esc_html($title) . '</strong><small>' . esc_html($desc) . '</small></span></label>';
    }

    private function retry_max_select() {
        $o = self::get_all();
        $value = absint($o['retry_max_attempts'] ?? 1);
        echo '<div class="jmw-select-row"><div><strong>Maksimal percobaan Retry</strong><small>Jumlah percobaan ulang manual yang diizinkan untuk satu item setelah gagal.</small></div><select name="japur_master_workflow[retry_max_attempts]"><option value="1"' . selected($value,1,false) . '>1 kali</option><option value="2"' . selected($value,2,false) . '>2 kali</option><option value="3"' . selected($value,3,false) . '>3 kali</option></select></div>';
    }

    private function failure_policy_select() {
        $o = self::get_all();
        $value = (string)($o['multi_failure_policy'] ?? 'continue');
        echo '<div class="jmw-select-row"><div><strong>Kebijakan saat Multi gagal</strong><small>Atur apakah batch Multi melanjutkan tujuan berikutnya atau menghentikan seluruh batch saat satu tujuan gagal.</small></div><select name="japur_master_workflow[multi_failure_policy]"><option value="continue"' . selected($value,'continue',false) . '>Lewati yang gagal, lanjutkan berikutnya</option><option value="stop"' . selected($value,'stop',false) . '>Hentikan seluruh batch</option></select></div>';
    }

    private function diagnostic_level_select() {
        $o = self::get_all();
        $value = (string)($o['diagnostic_level'] ?? 'standard');
        echo '<div class="jmw-select-row"><div><strong>Level Diagnostics</strong><small>Basic menampilkan inti error, Standard menampilkan detail utama, Detailed menampilkan semua informasi yang tersedia.</small></div><select name="japur_master_workflow[diagnostic_level]"><option value="basic"' . selected($value,'basic',false) . '>Basic</option><option value="standard"' . selected($value,'standard',false) . '>Standard</option><option value="detailed"' . selected($value,'detailed',false) . '>Detailed</option></select></div>';
    }

    private function section($icon, $title, $desc, $body) {
        echo '<section class="jmw-card"><div class="jmw-head"><div class="jmw-icon">' . esc_html($icon) . '</div><div><h2>' . esc_html($title) . '</h2><p>' . esc_html($desc) . '</p></div></div><div class="jmw-body">' . $body . '</div></section>';
    }

    private function status_card($icon, $title, $value, $detail, $on = true) {
        $state_class = $on ? 'is-on' : 'is-off';
        $state_badge = $on ? 'is-on' : 'is-off';
        echo '<div class="jmw-status-card ' . esc_attr($state_class) . '"><div class="jmw-status-icon">' . esc_html($icon) . '</div><div class="jmw-status-copy"><strong>' . esc_html($title) . '</strong><span class="jmw-badge ' . esc_attr($state_badge) . '"><i></i>' . esc_html($value) . '</span><small>' . esc_html($detail) . '</small></div></div>';
    }

    public function page() {
        if (!current_user_can('manage_options')) wp_die('Akses ditolak.');
        settings_errors('japur_master_workflow');
        $o = self::get_all();
        echo '<div class="wrap jmw-wrap">';
        echo '<style>
        .jmw-profile{display:flex;align-items:center;justify-content:space-between;gap:20px;background:#fff;border:1px solid #dcdcde;border-radius:16px;padding:18px 20px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.035)}.jmw-profile>div{min-width:0}.jmw-profile strong{display:block;color:#1d2327;font-size:15px;line-height:1.3}.jmw-profile span{display:block;color:#646970;font-size:11px;line-height:1.5;margin-top:4px}.jmw-profile-form{display:flex;align-items:center;gap:9px;flex:0 0 auto}.jmw-profile-form select{min-width:145px;height:40px}.jmw-profile-form .button{min-height:40px;padding:7px 16px}.jmw-wrap{max-width:1100px;margin:24px 20px 40px 0}.jmw-hero{background:#fff;border:1px solid #dcdcde;border-radius:16px;padding:24px 26px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.04)}.jmw-eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em;color:#2271b1;margin-bottom:7px}.jmw-hero h1{margin:0 0 7px;font-size:27px;color:#1d2327}.jmw-hero p{margin:0;color:#646970;line-height:1.6;max-width:760px}.jmw-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.jmw-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.03)}.jmw-head{display:flex;gap:13px;align-items:flex-start;padding:19px 20px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}.jmw-icon{width:40px;height:40px;flex:0 0 40px;border-radius:11px;background:#f0f6fc;color:#2271b1;display:flex;align-items:center;justify-content:center;font-size:19px}.jmw-head h2{margin:0 0 4px;font-size:16px}.jmw-head p{margin:0;color:#646970;font-size:12px;line-height:1.45}.jmw-body{padding:8px 18px}.jmw-toggle{display:grid;grid-template-columns:44px minmax(0,1fr);gap:12px;align-items:center;padding:14px 2px;border-bottom:1px solid #f0f0f1;cursor:pointer}.jmw-toggle:last-child{border-bottom:0}.jmw-toggle input{position:absolute;opacity:0;width:1px;height:1px}.jmw-track{position:relative;width:44px;height:25px;border-radius:999px;background:#8c8f94;transition:.18s;display:block}.jmw-thumb{position:absolute;top:4px;left:4px;width:17px;height:17px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:.18s}.jmw-toggle input:checked + .jmw-track{background:#2271b1}.jmw-toggle input:checked + .jmw-track .jmw-thumb{left:23px}.jmw-copy strong{display:block;color:#1d2327;font-size:13px;line-height:1.35}.jmw-select-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 2px}.jmw-select-row>div{min-width:0}.jmw-select-row strong{display:block;color:#1d2327;font-size:13px}.jmw-select-row small{display:block;color:#646970;font-size:11px;line-height:1.45;margin-top:3px}.jmw-select-row select{min-width:110px}.jmw-copy small{display:block;color:#646970;font-size:11px;line-height:1.45;margin-top:3px}.jmw-safe{grid-column:1/-1;background:#f0fbf3;border:1px solid #b7dfc0;border-radius:14px;padding:16px 18px;color:#285c31}.jmw-safe strong{display:block;margin-bottom:4px;color:#1d5d2a}.jmw-foot{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-top:16px;background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px 18px}.jmw-foot p{margin:0;color:#646970;font-size:12px;line-height:1.5}.jmw-foot .button-primary{min-height:40px;padding:7px 18px}.jmw-status-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.jmw-status-card{display:flex;align-items:flex-start;gap:12px;min-height:82px;box-sizing:border-box;background:#fff;border:1px solid #dcdcde;border-radius:15px;padding:15px;box-shadow:0 2px 7px rgba(0,0,0,.035);min-width:0}.jmw-status-icon{width:40px;height:40px;flex:0 0 40px;border-radius:11px;background:#f0f6fc;color:#2271b1;display:flex;align-items:center;justify-content:center;font-size:18px}.jmw-status-card.is-off .jmw-status-icon{background:#f5f5f6;color:#646970}.jmw-status-copy{min-width:0;display:flex;flex-direction:column;align-items:flex-start;gap:5px}.jmw-status-copy strong{display:block;width:100%;font-size:12px;line-height:1.3;color:#1d2327;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.jmw-status-copy small{display:block;width:100%;font-size:10px;line-height:1.35;color:#8c8f94;white-space:normal;overflow-wrap:anywhere}.jmw-badge{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:4px 9px;border-radius:999px;font-size:10px;font-weight:800;line-height:1.15}.jmw-badge i{display:block;width:6px;height:6px;border-radius:50%;background:currentColor}.jmw-badge.is-on{background:#edfaef;color:#18723a}.jmw-badge.is-off{background:#f0f0f1;color:#646970}.jmw-control-note{background:#f6f8fa;border:1px solid #e2e4e7;border-radius:12px;padding:12px 14px;margin:0 0 16px;color:#50575e;font-size:12px;line-height:1.55}.jmw-control-note strong{color:#1d2327}.jmw-defaults{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.jmw-defaults form{margin:0}.jmw-defaults .button{min-height:40px;padding:7px 15px}.jmw-defaults .jmw-reset{border-color:#dcdcde;color:#50575e;background:#fff}.jmw-defaults .jmw-reset:hover{border-color:#8c8f94;color:#1d2327}@media(max-width:900px){.jmw-status-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.jmw-wrap{margin:16px 12px 36px 0}.jmw-hero{padding:22px 20px;border-radius:16px}.jmw-hero h1{font-size:25px}.jmw-status-grid{grid-template-columns:1fr;gap:10px}.jmw-status-card{padding:14px}.jmw-status-copy small{white-space:normal}.jmw-profile{align-items:stretch;flex-direction:column;gap:14px;padding:17px}.jmw-profile-form{width:100%;display:grid;grid-template-columns:minmax(0,1fr) auto}.jmw-profile-form select{width:100%;min-width:0}.jmw-profile-form .button{white-space:nowrap}.jmw-grid{grid-template-columns:1fr;gap:14px}.jmw-safe{grid-column:auto}.jmw-foot{align-items:stretch;flex-direction:column}.jmw-foot .button-primary{width:100%}.jmw-defaults{display:block}.jmw-defaults form{margin-top:8px}.jmw-defaults .button{width:100%}}@media(max-width:420px){.jmw-status-grid{grid-template-columns:1fr}}
        </style>';
        echo '<div class="jmw-hero"><div class="jmw-eyebrow">JAPUR SUITE • MASTER CONTROL</div><h1>Master Workflow</h1><p>Atur perilaku workflow JaPur Suite dari satu tempat. Pengaturan ini mengendalikan perilaku UI dan workflow yang memang sudah tersedia, tanpa mengganti mesin inti.</p></div>';
        echo '<div class="jmw-status-grid">';
        $this->status_card('⚡','Background Auto Publish', !empty($o['background_auto_publish_single']) ? 'Aktif' : 'Nonaktif', 'Single Background', !empty($o['background_auto_publish_single']));
        $this->status_card('↻','Resume Background', !empty($o['background_resume_after_reload']) ? 'Aktif' : 'Nonaktif', 'Pulihkan job setelah reload', !empty($o['background_resume_after_reload']));
        $this->status_card('🛡️','Mode Aman', !empty($o['safe_mode']) ? 'Aktif' : 'Nonaktif', 'Proteksi konfigurasi inti', !empty($o['safe_mode']));
        $this->status_card('🎛️','Control Center', 'Siap', 'Kontrol fitur existing', true);
        echo '</div>';
        echo '<div class="jmw-profile"><div><strong>🎛️ Workflow Profile</strong><span>Pilih preset perilaku siap pakai. Profil hanya mengubah konfigurasi Master Workflow yang sudah tersedia.</span></div><form method="post" class="jmw-profile-form">';
        wp_nonce_field('japur_master_workflow_apply_profile');
        echo '<input type="hidden" name="japur_master_workflow_apply_profile" value="1">';
        echo '<select name="japur_master_workflow_profile">';
        $profile = self::normalize_profile($o['workflow_profile'] ?? 'custom');
        foreach (['standard'=>'Standard','safe'=>'Safe','fast'=>'Fast','auto'=>'Auto','custom'=>'Custom'] as $pk=>$pn) echo '<option value="'.esc_attr($pk).'"'.selected($profile,$pk,false).'>'.esc_html($pn).'</option>';
        echo '</select><button type="submit" class="button button-secondary">Terapkan Profil</button></form></div>';
        echo '<div class="jmw-control-note"><strong>Workflow Profiles.</strong> Preset membantu mengatur banyak fitur sekaligus tanpa mengubah engine inti. Setelah pengaturan manual disimpan, profil otomatis menjadi Custom.</div>';
        echo '<form method="post">'; wp_nonce_field('japur_master_workflow_save'); echo '<input type="hidden" name="japur_master_workflow_save" value="1">';
        echo '<div class="jmw-grid">';
        $this->section('🖥️','Background','Pengaturan aman untuk mode Background.',
            $this->capture(function(){
                $this->toggle('background_button_label','Ubah tombol menjadi “Buat & Publikasikan”','Saat Background aktif, label tombol mengikuti mode otomatis.');
                $this->toggle('background_auto_publish_single','Publikasikan Single secara otomatis','Setelah Artikel + Thumbnail selesai, Single langsung masuk tahap Publish.');
                $this->toggle('background_resume_after_reload','Resume Background setelah reload','Job Background yang masih aktif boleh dipulihkan saat halaman dibuka lagi.');
            })
        );
        $this->section('📝','Single Post','Perilaku hasil akhir untuk WordPress dan Blogger.',
            $this->capture(function(){
                $this->toggle('single_hide_thumbnail_after_success','Sembunyikan Preview Thumbnail setelah sukses','Thumbnail tetap diproses internal; hanya kartu preview yang disembunyikan.');
                $this->toggle('single_hide_publish_after_success','Sembunyikan “Terapkan & Publish” setelah sukses','Mencegah tombol publikasi muncul lagi setelah Background sudah selesai.');
            })
        );
        $this->section('🧭','Alur Kerja Setelah Publish','Atur apakah kartu “Alur kerja” disembunyikan setelah Single Post berhasil diterbitkan. Pengaturan dipisahkan berdasarkan platform dan mode.',
            $this->capture(function(){
                $this->toggle('single_wp_background_hide_workflow_after_success','WordPress • Background — Sembunyikan “Alur kerja”','ON: kartu Alur kerja disembunyikan setelah Single WordPress berhasil dipublikasikan di Background.');
                $this->toggle('single_wp_foreground_hide_workflow_after_success','WordPress • Foreground — Sembunyikan “Alur kerja”','ON: kartu Alur kerja disembunyikan setelah Single WordPress berhasil dipublikasikan di layar.');
                $this->toggle('single_blogger_background_hide_workflow_after_success','Blogger • Background — Sembunyikan “Alur kerja”','ON: kartu Alur kerja disembunyikan setelah Single Blogger berhasil dipublikasikan di Background.');
                $this->toggle('single_blogger_foreground_hide_workflow_after_success','Blogger • Foreground — Sembunyikan “Alur kerja”','ON: kartu Alur kerja disembunyikan setelah Single Blogger berhasil dipublikasikan di layar.');
            })
        );
        $this->section('🔄','Retry & Recovery','Kontrol Smart Retry untuk Foreground dan Background Multi yang sudah tersedia.',
            $this->capture(function(){
                $this->toggle('retry_enabled','Aktifkan Retry','Tampilkan dan izinkan mekanisme Retry pada item yang gagal.');
                $this->toggle('retry_article','Retry tahap Artikel','Jika Artikel gagal, izinkan pembuatan Artikel ulang.');
                $this->toggle('retry_thumbnail','Retry tahap Thumbnail','Jika Thumbnail gagal, gunakan hasil Artikel yang sudah tersedia.');
                $this->toggle('retry_publish','Retry tahap Publish','Jika Publish gagal, gunakan hasil Artikel/Thumbnail yang sudah tersedia.');
                $this->retry_max_select();
            })
        );
        $this->section('🩺','Diagnostics Controller','Atur informasi kegagalan yang ditampilkan pada workflow Single dan Multi.',
            $this->capture(function(){
                $this->toggle('diagnostic_enabled','Aktifkan Diagnostics','Tampilkan kartu diagnosis ketika workflow mengalami kegagalan.');
                $this->diagnostic_level_select();
                $this->toggle('diagnostic_show_stage','Tampilkan Tahap','Tampilkan tahap Article, Thumbnail, atau Publish.');
                $this->toggle('diagnostic_show_target','Tampilkan Website','Tampilkan target website atau profil tujuan yang mengalami kegagalan.');
                $this->toggle('diagnostic_show_http','Tampilkan HTTP','Tampilkan kode HTTP jika tersedia.');
                $this->toggle('diagnostic_show_message','Tampilkan Pesan','Tampilkan pesan error dari proses yang gagal.');
                $this->toggle('diagnostic_show_retry','Tampilkan Tombol Retry','Tampilkan tombol Retry/Coba Lagi pada item yang gagal dan memenuhi aturan Retry.');
            })
        );
        $this->section('🧩','Stage Controller','Atur tahap yang ditampilkan pada visual progress workflow. Pengaturan ini hanya mengubah tampilan; urutan dan mesin Article → Thumbnail → Publish tetap berjalan seperti sebelumnya.',
            $this->capture(function(){
                $this->toggle('stage_controller_enabled','Aktifkan Stage Controller','Gunakan pengaturan tahap di bawah untuk visual progress workflow.');
                $this->toggle('stage_show_extract','Tampilkan tahap Ekstrak','Tampilkan tahap Ekstrak pada indikator workflow saat tersedia.');
                $this->toggle('stage_show_article','Tampilkan tahap Artikel','Tampilkan tahap pembuatan Artikel pada indikator workflow.');
                $this->toggle('stage_show_thumbnail','Tampilkan tahap Thumbnail','Tampilkan tahap pembuatan Thumbnail pada indikator workflow.');
                $this->toggle('stage_show_publish','Tampilkan tahap Terbitkan','Tampilkan tahap Publish pada indikator workflow.');
                $this->failure_policy_select();
            })
        );
        $this->section('🛡️','Safety Guard Controller','Lapisan pengaman untuk mencegah proses dimulai dalam kondisi yang belum siap atau terjadi proses ganda.',
            $this->capture(function(){
                $this->toggle('safety_guard_enabled','Aktifkan Safety Guard','Aktifkan pemeriksaan pengaman sebelum workflow dimulai.');
                $this->toggle('safety_require_preflight','Wajib lolos System Check','Workflow tidak boleh dimulai sebelum pemeriksaan sistem berhasil.');
                $this->toggle('safety_prevent_duplicate_click','Cegah Double-Click / Start Ganda','Mencegah tombol mulai menjalankan workflow lebih dari sekali pada sesi yang sama.');
                $this->toggle('safety_block_active_background','Blokir Start jika Background masih aktif','Mencegah membuat workflow baru ketika masih ada Background Job aktif pada browser ini.');
                $this->toggle('safety_validate_before_start','Validasi Ulang sebelum Start','Memastikan target, jenis artikel, materi, dan konfigurasi tujuan masih valid tepat sebelum proses dimulai.');
            })
        );
        $this->section('🧪','Developer Mode / Advanced Diagnostics','Mode teknis read-only untuk melihat Run ID, Job ID, tahap, retry, HTTP, timestamp, state, detail diagnosis, dan Developer Log. OFF secara default; tidak menyediakan tombol mutasi berbahaya.',
            $this->capture(function(){
                $this->toggle('developer_mode_enabled','Aktifkan Developer Mode','Tampilkan panel informasi teknis workflow. Mode ini hanya membaca state yang sudah tersedia.');
                $this->toggle('developer_show_run_id','Tampilkan Workflow Run ID','Tampilkan identitas run workflow pada panel teknis.');
                $this->toggle('developer_show_job_id','Tampilkan Job ID','Tampilkan ID Background/Job jika tersedia.');
                $this->toggle('developer_show_target','Tampilkan Target','Tampilkan website/profil tujuan.');
                $this->toggle('developer_show_stage','Tampilkan Current Stage','Tampilkan tahap aktif workflow.');
                $this->toggle('developer_show_retry_count','Tampilkan Retry Count','Tampilkan jumlah retry yang tersedia/tercatat.');
                $this->toggle('developer_show_http','Tampilkan HTTP Status','Tampilkan HTTP status jika tersedia.');
                $this->toggle('developer_show_timestamp','Tampilkan Timestamp','Tampilkan waktu event teknis.');
                $this->toggle('developer_show_job_state','Tampilkan Job State','Tampilkan state job seperti running, done, failed, atau cancelled.');
                $this->toggle('developer_show_diagnostic','Tampilkan Diagnostic Detail','Tampilkan detail diagnosis teknis dari state yang sudah ada.');
                $this->toggle('developer_copy_button','Aktifkan Copy Diagnostic','Izinkan menyalin informasi teknis ke clipboard.');
                echo '<div class="jmw-row"><div><strong>Developer Log maksimum</strong><small>Log sementara disimpan di browser, bukan database. Nilai dibatasi 10–100.</small></div><select name="japur_master_workflow_settings[developer_max_log_entries]"><option value="25" '.selected((int)$o['developer_max_log_entries'],25,false).'>25</option><option value="50" '.selected((int)$o['developer_max_log_entries'],50,false).'>50</option><option value="100" '.selected((int)$o['developer_max_log_entries'],100,false).'>100</option></select></div>';
            })
        );
        $this->section('🔍','System Check','Pengaturan siklus pemeriksaan.',
            $this->capture(function(){
                $this->toggle('reset_preflight_on_rebuild','Reset System Check saat “Buat Ulang”','Hasil pemeriksaan sesi lama tidak dibawa ke sesi artikel baru.');
            })
        );
        echo '<section class="jmw-card"><div class="jmw-head"><div class="jmw-icon">📊</div><div><h2>Publication Dashboard</h2><p>Riwayat dan Retry akan dihubungkan ke sini setelah mesin histori publikasi siap.</p></div></div><div class="jmw-body"><div style="padding:14px 2px;color:#646970;font-size:12px;line-height:1.55"><strong style="color:#1d2327">Fondasi disiapkan, mesin tidak dipaksa.</strong><br>Bagian ini sengaja belum menyediakan checkbox palsu. Saat Publication Dashboard dan Retry sudah stabil, pengaturannya akan ditambahkan di sini.</div></div></section>';
        echo '<div class="jmw-safe"><strong>🛡️ Mode Aman: ' . (!empty($o['safe_mode']) ? 'AKTIF' : 'NONAKTIF') . '</strong><span>Mode Aman dikunci pada rilis ini. Pengaturan Master Workflow hanya mengubah perilaku yang sudah memiliki jalur internal; tidak mengganti worker inti, API, atau proses publish.</span></div>';
        echo '</div><div class="jmw-foot"><p>Perubahan berlaku untuk sesi workflow berikutnya. Klik Simpan setelah mengubah checkbox.</p><div class="jmw-defaults"><button type="submit" class="button button-primary">Simpan Pengaturan</button></div></div></form><form method="post" style="margin-top:10px">' . wp_nonce_field('japur_master_workflow_reset', '_wpnonce', true, false) . '<input type="hidden" name="japur_master_workflow_reset" value="1"><button type="submit" class="button jmw-reset" onclick="return confirm(\'Kembalikan Master Workflow ke konfigurasi aman bawaan?\');">↺ Kembalikan Default Aman</button></form></div>';
    }

    private function capture($fn) { ob_start(); $fn(); return ob_get_clean(); }
}
new Japur_Master_Workflow_Settings();
}
