<?php

/* =========================================================
 * CORE REPLACE FUNCTION
 * ========================================================= */
function JapurSuite_auto_date_replace_month_year($text) {
    if (!JapurSuite_Core::module_enabled('date')) return $text;

    $bulan = array(
        'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    );

    $bulan_sekarang = $bulan[date('n') - 1];
    $tahun_sekarang = date('Y');

    $text = preg_replace(
        '/Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember/i',
        $bulan_sekarang,
        $text
    );

    $text = preg_replace('/20[0-9]{2}/', $tahun_sekarang, $text);

    return $text;
}

/* =========================================================
 * MAIN TITLE FILTER (POST / ARCHIVE / HOME)
 * ========================================================= */
function JapurSuite_auto_update_post_month_year($title, $post_id) {
    if (!JapurSuite_Core::module_enabled('date')) return $title;

    if (is_admin()) return $title;

    if (!is_singular() && !is_home() && !is_archive()) return $title;

    if (!is_numeric($post_id)) return $title;

    if (get_post_type($post_id) !== 'post') return $title;

    if (get_post_meta($post_id, '_exclude_auto_date', true) === 'yes') return $title;

    $allowed = get_option('auto_date_allowed_categories', array());
    if (!empty($allowed)) {
        $post_categories = wp_get_post_categories($post_id);
        if (empty(array_intersect($allowed, $post_categories))) {
            return $title;
        }
    }

    return JapurSuite_auto_date_replace_month_year($title);
}
add_filter('the_title', 'JapurSuite_auto_update_post_month_year', 10, 2);
add_filter('get_the_title', 'JapurSuite_auto_update_post_month_year', 10, 2);

/* =========================================================
 * SEO SUPPORT (GOOGLE, YOAST)
 * ========================================================= */
function JapurSuite_auto_date_update_seo_title($title) {
    if (!JapurSuite_Core::module_enabled('date')) return $title;

    if (is_admin() || !is_singular('post')) return $title;

    global $post;
    if (!$post) return $title;

    if (get_post_meta($post->ID, '_exclude_auto_date', true) === 'yes') return $title;

    $allowed = get_option('auto_date_allowed_categories', array());
    if (!empty($allowed)) {
        $cats = wp_get_post_categories($post->ID);
        if (empty(array_intersect($allowed, $cats))) return $title;
    }

    return JapurSuite_auto_date_replace_month_year($title);
}

add_filter('pre_get_document_title', 'JapurSuite_auto_date_update_seo_title');
add_filter('wpseo_title', 'JapurSuite_auto_date_update_seo_title');
add_filter('wpseo_opengraph_title', 'JapurSuite_auto_date_update_seo_title');

/* =========================================================
 * YOAST BREADCRUMB
 * ========================================================= */
add_filter('wpseo_breadcrumb_single_link', function ($link_output, $link) {
    if (!JapurSuite_Core::module_enabled('date')) return $link_output;
    if (!is_singular('post')) return $link_output;
    return JapurSuite_auto_date_replace_month_year($link_output);
}, 10, 2);

/* =========================================================
 * METABOX (EXCLUDE POST)
 * ========================================================= */
function JapurSuite_auto_date_add_metabox() {
    if (!JapurSuite_Core::module_enabled('date')) return;
    add_meta_box(
        'exclude_auto_date',
        'Auto Update Judul',
        'JapurSuite_auto_date_metabox_callback',
        'post',
        'side'
    );
}
add_action('add_meta_boxes', 'JapurSuite_auto_date_add_metabox');

function JapurSuite_auto_date_metabox_callback($post) {
    $value = get_post_meta($post->ID, '_exclude_auto_date', true);
    wp_nonce_field('exclude_auto_date_action', 'exclude_auto_date_nonce');
    ?>
    <label>
        <input type="checkbox" name="exclude_auto_date" value="yes" <?php checked($value, 'yes'); ?>>
        Jangan update bulan & tahun
    </label>
    <?php
}

/* =========================================================
 * SAFE SAVE POST (NO QUICK EDIT BUG)
 * ========================================================= */
function JapurSuite_auto_date_save_post($post_id) {
    if (!JapurSuite_Core::module_enabled('date')) return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (!isset($_POST['exclude_auto_date_nonce'])) return;
    if (!wp_verify_nonce($_POST['exclude_auto_date_nonce'], 'exclude_auto_date_action')) return;

    if (isset($_POST['exclude_auto_date'])) {
        update_post_meta($post_id, '_exclude_auto_date', 'yes');
    } else {
        delete_post_meta($post_id, '_exclude_auto_date');
    }
}
add_action('save_post', 'JapurSuite_auto_date_save_post');

/* =========================================================
 * SETTINGS PAGE (CATEGORY FILTER)
 * ========================================================= */

function JapurSuite_auto_date_settings_page() {
    $categories = get_categories(array('hide_empty' => false));
    $saved = get_option('auto_date_allowed_categories', array());
    $saved = is_array($saved) ? array_map('intval', $saved) : array();
    $module_on = JapurSuite_Core::module_enabled('date');
    ?>
    <div class="wrap japur-auto-date-settings">
        <div class="japi-head">
            <div>
                <div class="japi-eyebrow">JAPUR SUITE</div>
                <h1>Auto Update Judul</h1>
            </div>
        </div>

        <div class="japi-status <?php echo $module_on ? 'is-on' : 'is-off'; ?>">
            <span class="japi-status-dot"></span>
            <div class="japi-status-label">
                <strong>Modul <?php echo $module_on ? 'aktif' : 'nonaktif'; ?></strong>
            </div>
            <a class="japi-dashboard-link" href="<?php echo esc_url(admin_url('admin.php?page=japur-suite')); ?>">Dashboard</a>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields('auto_date_settings'); ?>
            <div class="japi-panel">
                <div class="japi-panel-head">
                    <div>
                        <h2>Kategori yang diproses</h2>
                        <p>Pilih kategori artikel yang ingin diproses oleh fitur pembaruan bulan dan tahun pada judul.</p>
                    </div>
                    <span class="japi-badge"><?php echo count($saved); ?> Kategori</span>
                </div>

                <div class="japi-fields">
                    <?php foreach ($categories as $cat):
                        $enabled = in_array((int) $cat->term_id, $saved, true);
                        $toggle_id = 'auto-date-category-' . (int) $cat->term_id;
                    ?>
                        <div class="japi-field-row <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
                            <div class="japi-field-copy">
                                <div class="japi-field-title"><?php echo esc_html($cat->name); ?></div>
                                <div class="japi-field-desc">Izinkan pembaruan bulan dan tahun pada judul artikel.</div>
                            </div>
                            <label class="japi-switch" for="<?php echo esc_attr($toggle_id); ?>">
                                <input id="<?php echo esc_attr($toggle_id); ?>" type="checkbox" name="auto_date_allowed_categories[]" value="<?php echo (int) $cat->term_id; ?>" <?php checked($enabled, true); ?> aria-label="<?php echo esc_attr($cat->name); ?>">
                                <span class="japi-switch-track"><span class="japi-switch-thumb"></span></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="japi-panel-foot">
                    <div class="japi-help"><span></span><span>Hanya artikel dari kategori yang dipilih yang akan diproses oleh Auto Update Judul.</span></div>
                    <button type="submit" class="button button-primary button-large">Simpan Pengaturan</button>
                </div>
            </div>
        </form>

        <div class="japi-marker-box">
            <h3>Catatan</h3>
            <p>Pengaturan ini menentukan kategori artikel yang diproses. Artikel di luar kategori terpilih tidak akan diproses oleh fitur Auto Update Judul.</p>
        </div>

        <style>
            .japur-auto-date-settings{max-width:1080px}
            .japur-auto-date-settings .japi-head{display:flex;flex-direction:column;justify-content:flex-start;align-items:flex-start;gap:3px;margin:22px 0 18px;text-align:left}
            .japur-auto-date-settings .japi-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.4px;color:#2271b1;margin-bottom:2px}
            .japur-auto-date-settings .japi-head h1{font-size:23px;line-height:1.3;margin:0;font-weight:600;color:#1d2327}
            .japur-auto-date-settings .japi-status{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
            .japur-auto-date-settings .japi-status-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px;background:#d63638}
            .japur-auto-date-settings .japi-status.is-on .japi-status-dot{background:#00a32a;box-shadow:0 0 0 4px #edfaef}
            .japur-auto-date-settings .japi-status strong{display:block;font-size:14px;color:#1d2327}
            .japur-auto-date-settings .japi-status-label{min-width:0}
            .japur-auto-date-settings .japi-dashboard-link{margin-left:auto;text-decoration:none;font-weight:600;white-space:nowrap;flex:0 0 auto}
            .japur-auto-date-settings .japi-panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;box-shadow:0 2px 7px rgba(0,0,0,.04)}
            .japur-auto-date-settings .japi-panel-head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:22px 24px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}
            .japur-auto-date-settings .japi-panel-head h2{font-size:18px;margin:0 0 5px;color:#1d2327}
            .japur-auto-date-settings .japi-panel-head p{margin:0;color:#646970;font-size:13px}
            .japur-auto-date-settings .japi-badge{font-size:12px;font-weight:700;color:#50575e;background:#f0f0f1;border-radius:999px;padding:6px 10px;white-space:nowrap}
            .japur-auto-date-settings .japi-fields{padding:0 24px}
            .japur-auto-date-settings .japi-field-row{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:18px 0;border-bottom:1px solid #eee}
            .japur-auto-date-settings .japi-field-row:last-child{border-bottom:0}
            .japur-auto-date-settings .japi-field-copy{min-width:0}
            .japur-auto-date-settings .japi-field-title{font-size:15px;font-weight:700;color:#1d2327;margin-bottom:4px}
            .japur-auto-date-settings .japi-field-desc{font-size:13px;line-height:1.5;color:#646970}
            .japur-auto-date-settings .japi-switch{position:relative;display:block;width:48px;height:28px;flex:0 0 48px;cursor:pointer}
            .japur-auto-date-settings .japi-switch input{position:absolute;opacity:0;width:1px;height:1px}
            .japur-auto-date-settings .japi-switch-track{position:absolute;inset:0;border-radius:999px;background:#8c8f94;transition:.2s;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
            .japur-auto-date-settings .japi-switch-thumb{position:absolute;top:4px;left:4px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
            .japur-auto-date-settings .japi-switch input:checked + .japi-switch-track{background:#2271b1}
            .japur-auto-date-settings .japi-switch input:checked + .japi-switch-track .japi-switch-thumb{left:24px}
            .japur-auto-date-settings .japi-switch input:focus-visible + .japi-switch-track{outline:2px solid #72aee6;outline-offset:2px}
            .japur-auto-date-settings .japi-field-row.is-disabled .japi-field-title{color:#646970}
            .japur-auto-date-settings .japi-panel-foot{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 24px;background:#fbfbfc;border-top:1px solid #e2e4e7}
            .japur-auto-date-settings .japi-help{display:flex;gap:9px;align-items:flex-start;color:#646970;font-size:12px;line-height:1.5;max-width:700px}
            .japur-auto-date-settings .japi-help span:first-child{font-size:15px;line-height:1.2}
            .japur-auto-date-settings .japi-marker-box{margin-top:16px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px;padding:18px 20px}
            .japur-auto-date-settings .japi-marker-box h3{margin:0 0 8px;font-size:14px;color:#1d2327}
            .japur-auto-date-settings .japi-marker-box p{font-size:12px;color:#646970;line-height:1.55;margin:0}
            @media (max-width:700px){
                .japur-auto-date-settings .japi-head h1{font-size:23px}
                .japur-auto-date-settings .japi-panel-head,.japur-auto-date-settings .japi-panel-foot{display:block}
                .japur-auto-date-settings .japi-badge{display:inline-block;margin-top:12px}
                .japur-auto-date-settings .japi-panel-foot .button{margin-top:15px}
                .japur-auto-date-settings .japi-status{align-items:flex-start}
                .japur-auto-date-settings .japi-dashboard-link{margin-left:auto}
                .japur-auto-date-settings .japi-field-row{gap:14px}
                .japur-auto-date-settings .japi-field-desc{font-size:12px}
            }
        </style>
    </div>
    <?php
}

function JapurSuite_auto_date_register_settings() {
    register_setting('auto_date_settings', 'auto_date_allowed_categories');
}
add_action('admin_init', 'JapurSuite_auto_date_register_settings');
