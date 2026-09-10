<?php

if (!defined('ABSPATH')) exit;

/* =========================
   SETTINGS DEFAULT
========================= */
function JapurSuite_rpi_default_settings() {
    return array(
        'mode' => 'category', // category | random
        'count' => 4,
        'show_thumbnail' => 1,
        'related_title' => 'Baca Juga',
    );
}

/* =========================
   GET SETTINGS
========================= */
function JapurSuite_rpi_get_settings() {
    $defaults = JapurSuite_rpi_default_settings();
    $saved = get_option('rpi_settings', array());
    return wp_parse_args($saved, $defaults);
}

/* =========================
   QUERY RELATED POSTS
========================= */
function JapurSuite_rpi_get_related_posts($post_id, $mode = 'category', $count = 4) {

    $args = array(
        'post_type' => 'post',
        'posts_per_page' => max(1, min(5, (int) $count)),
        'post__not_in' => array($post_id),
        'ignore_sticky_posts' => true
    );

    if ($mode === 'category') {
        $category_ids = wp_get_post_categories($post_id);
        if (!empty($category_ids)) {
            $args['category__in'] = $category_ids;
        }
    } elseif ($mode === 'tag') {
        $tag_ids = wp_get_post_tags($post_id, array('fields' => 'ids'));
        if (!empty($tag_ids)) {
            $args['tag__in'] = $tag_ids;
        }
    } else {
        $args['orderby'] = 'rand';
    }

    return new WP_Query($args);
}

/* =========================
   RENDER HTML
========================= */
function JapurSuite_rpi_render_post($post, $show_thumb = true, $settings_title = 'Baca Juga') {

    $html = '<div class="rpi-card">';
    $html .= '<a href="' . get_permalink($post) . '">';

    if ($show_thumb && has_post_thumbnail($post)) {
        $html .= get_the_post_thumbnail($post, 'medium');
    }

    $html .= '<div class="rpi-content">';
    $html .= '<span class="rpi-label">' . esc_html(!empty($settings_title) ? $settings_title : 'Baca Juga') . '</span>';
    $html .= '<h4>' . get_the_title($post) . '</h4>';
    $html .= '</div>';

    $html .= '</a></div>';

    return $html;
}

/* =========================
   INJECT KE CONTENT
========================= */
function JapurSuite_rpi_inject_content($content) {
    if (!JapurSuite_Core::module_enabled('rpi')) return $content;

    if (!is_single() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    global $post;
    $settings = JapurSuite_rpi_get_settings();

    $count = isset($settings['count']) ? max(1, min(5, (int) $settings['count'])) : 4;
    $query = JapurSuite_rpi_get_related_posts($post->ID, $settings['mode'], $count);

    if (!$query->have_posts()) return $content;

    $related_posts = $query->posts;

    $paragraphs = explode('</p>', $content);
    $paragraph_count = 0;

    foreach ($paragraphs as $paragraph) {
        if (trim(wp_strip_all_tags($paragraph)) !== '') {
            $paragraph_count++;
        }
    }

    if ($paragraph_count < 1) {
        return $content;
    }

    $actual_count = min($count, count($related_posts), $paragraph_count);
    $target_indexes = array();

    for ($slot = 1; $slot <= $actual_count; $slot++) {
        $target = (int) round(($paragraph_count * $slot) / ($actual_count + 1));
        $target = max(1, min($paragraph_count, $target));
        $target_indexes[] = $target;
    }

    $target_indexes = array_values(array_unique($target_indexes));
    $paragraph_index = 0;
    $related_index = 0;

    foreach ($paragraphs as $index => $paragraph) {
        if (trim(wp_strip_all_tags($paragraph)) === '') {
            continue;
        }

        $paragraph_index++;

        if (in_array($paragraph_index, $target_indexes, true) && isset($related_posts[$related_index])) {
            $html = JapurSuite_rpi_render_post(
                $related_posts[$related_index],
                $settings['show_thumbnail'],
                isset($settings['related_title']) ? $settings['related_title'] : 'Baca Juga'
            );

            $paragraphs[$index] .= $html;
            $related_index++;
        }

        if ($related_index >= $actual_count) {
            break;
        }
    }

    return implode('</p>', $paragraphs);
}

add_filter('the_content', 'JapurSuite_rpi_inject_content');

/* =========================
   ADMIN MENU
========================= */

/* =========================
   SETTINGS PAGE UI
========================= */
function JapurSuite_rpi_settings_page() {
    if (isset($_POST['rpi_save'])) {
        $allowed_modes = array('category', 'tag', 'random');
        $mode = isset($_POST['mode']) && in_array($_POST['mode'], $allowed_modes, true) ? $_POST['mode'] : 'category';
        $count = isset($_POST['count']) ? max(1, min(5, absint($_POST['count']))) : 4;
        $related_title = isset($_POST['related_title']) ? sanitize_text_field(wp_unslash($_POST['related_title'])) : 'Baca Juga';
        if ($related_title === '') {
            $related_title = 'Baca Juga';
        }

        update_option('rpi_settings', array(
            'mode' => $mode,
            'count' => $count,
            'show_thumbnail' => isset($_POST['show_thumbnail']) ? 1 : 0,
            'related_title' => $related_title,
        ));

        echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.JapurSuiteToast)window.JapurSuiteToast.show("Pengaturan RPI Pro disimpan.","success");});</script>';
    }

    $settings = JapurSuite_rpi_get_settings();
    $module_on = JapurSuite_Core::module_enabled('rpi');
    ?>

    <div class="wrap japur-rpi-settings">
        <div class="japi-head">
            <div>
                <div class="japi-eyebrow">JAPUR SUITE</div>
                <h1>RPI Pro</h1>
            </div>
        </div>

        <div class="japi-status <?php echo $module_on ? 'is-on' : 'is-off'; ?>">
            <span class="japi-status-dot"></span>
            <div class="japi-status-label">
                <strong><?php echo $module_on ? 'Modul aktif' : 'Modul nonaktif'; ?></strong>
            </div>
            <a class="japi-dashboard-link" href="<?php echo esc_url(admin_url('admin.php?page=japur-suite')); ?>">Dashboard</a>
        </div>

        <form method="post">
            <div class="japi-panel">
                <div class="japi-panel-head">
                    <div>
                        <h2>Pengaturan RPI Pro</h2>
                        <p>Atur sumber artikel terkait, posisi penyisipan, dan tampilan thumbnail.</p>
                    </div>
                    <span class="japi-badge">4 Pengaturan</span>
                </div>

                <div class="japi-fields">
                    <div class="japi-field-row">
                        <div class="japi-field-copy">
                            <div class="japi-field-title">Mode Artikel Terkait</div>
                            <div class="japi-field-desc">Pilih artikel terkait berdasarkan kategori, tag artikel saat ini, atau secara acak.</div>
                        </div>
                        <div class="japi-field-control">
                            <select name="mode" class="japi-select">
                                <option value="category" <?php selected($settings['mode'], 'category'); ?>>Kategori</option>
                                <option value="tag" <?php selected($settings['mode'], 'tag'); ?>>Tag</option>
                                <option value="random" <?php selected($settings['mode'], 'random'); ?>>Random</option>
                            </select>
                        </div>
                    </div>

                    <div class="japi-field-row">
                        <div class="japi-field-copy">
                            <div class="japi-field-title">Jumlah Related Post</div>
                            <div class="japi-field-desc">Pilih jumlah artikel terkait. Related post akan ditempatkan otomatis dan dibagi rata mengikuti panjang body artikel.</div>
                        </div>
                        <div class="japi-field-control">
                            <select name="count" class="japi-select">
                                <?php for ($i = 1; $i <= 5; $i++) : ?>
                                    <option value="<?php echo $i; ?>" <?php selected((int) $settings['count'], $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="japi-field-row">
                        <div class="japi-field-copy">
                            <div class="japi-field-title">Judul Related Post</div>
                            <div class="japi-field-desc">Atur judul yang ditampilkan pada setiap kartu related post di dalam artikel.</div>
                        </div>
                        <div class="japi-field-control">
                            <input type="text" name="related_title" class="japi-text" value="<?php echo esc_attr(isset($settings['related_title']) ? $settings['related_title'] : 'Baca Juga'); ?>" maxlength="80">
                        </div>
                    </div>

                    <div class="japi-field-row">
                        <div class="japi-field-copy">
                            <div class="japi-field-title">Thumbnail</div>
                            <div class="japi-field-desc">Tampilkan gambar unggulan pada kartu artikel terkait.</div>
                        </div>
                        <div class="rpi-toggle-control">
                            <label class="rpi-toggle" for="rpi-show-thumbnail">
                                <input id="rpi-show-thumbnail" type="checkbox" name="show_thumbnail" value="1" <?php checked($settings['show_thumbnail'], 1); ?> aria-label="Thumbnail">
                                <span class="rpi-toggle-track"><span class="rpi-toggle-thumb"></span></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="japi-panel-foot">
                    <div class="japi-foot-help">Pengaturan RPI Pro berlaku saat artikel ditampilkan pada halaman tunggal.</div>
                    <button type="submit" name="rpi_save" class="button button-primary button-large">Simpan Pengaturan</button>
                </div>
            </div>
        </form>

        <div class="japi-marker-box">
            <h3>Catatan</h3>
            <p>RPI Pro menampilkan jumlah artikel terkait sesuai pengaturan dan menempatkannya otomatis secara merata mengikuti panjang body artikel. Artikel yang sedang dibaca tidak digunakan sebagai artikel terkait.</p>
        </div>
    </div>

    <style>
        .japur-rpi-settings{max-width:1080px}
        .japur-rpi-settings .japi-head{display:flex;flex-direction:column;justify-content:flex-start;align-items:flex-start;gap:3px;margin:22px 0 18px;text-align:left}
        .japur-rpi-settings .japi-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.4px;color:#2271b1;margin-bottom:2px}
        .japur-rpi-settings .japi-head h1{font-size:23px;line-height:1.3;margin:0;font-weight:600;color:#1d2327}
        .japur-rpi-settings .japi-status{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
        .japur-rpi-settings .japi-status-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px;background:#d63638}
        .japur-rpi-settings .japi-status.is-on .japi-status-dot{background:#00a32a;box-shadow:0 0 0 4px #edfaef}
        .japur-rpi-settings .japi-status-label{min-width:0}
        .japur-rpi-settings .japi-status strong{display:block;font-size:14px;color:#1d2327}
        .japur-rpi-settings .japi-dashboard-link{margin-left:auto;text-decoration:none;font-weight:600;white-space:nowrap;flex:0 0 auto}
        .japur-rpi-settings .japi-panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;box-shadow:0 2px 7px rgba(0,0,0,.04)}
        .japur-rpi-settings .japi-panel-head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:22px 24px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}
        .japur-rpi-settings .japi-panel-head h2{font-size:18px;margin:0 0 5px;color:#1d2327}
        .japur-rpi-settings .japi-panel-head p{margin:0;color:#646970;font-size:13px}
        .japur-rpi-settings .japi-badge{font-size:12px;font-weight:700;color:#50575e;background:#f0f0f1;border-radius:999px;padding:6px 10px;white-space:nowrap}
        .japur-rpi-settings .japi-fields{padding:0 24px}
        .japur-rpi-settings .japi-field-row{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:18px 0;border-bottom:1px solid #eee}
        .japur-rpi-settings .japi-field-copy{min-width:0;flex:1}
        .japur-rpi-settings .japi-field-title{font-size:15px;font-weight:700;color:#1d2327;margin-bottom:4px}
        .japur-rpi-settings .japi-field-desc{font-size:13px;line-height:1.5;color:#646970}
        .japur-rpi-settings .japi-field-control{flex:0 0 auto}
        .japur-rpi-settings .japi-select,.japur-rpi-settings .japi-text{min-height:38px;border:1px solid #8c8f94;border-radius:6px;padding:5px 10px;background:#fff}
        .japur-rpi-settings .japi-select{min-width:145px}
        .japur-rpi-settings .japi-text{width:145px}
        .japur-rpi-settings .japi-position-control{display:flex;align-items:center;gap:8px}
        .japur-rpi-settings .japi-control-help{font-size:12px;color:#646970;white-space:nowrap}
        .japur-rpi-settings .rpi-toggle-control{display:flex;align-items:center;justify-content:flex-end;flex:0 0 48px;width:48px;height:28px;margin:0;padding:0}
        .japur-rpi-settings .rpi-toggle{position:relative;display:block;width:48px;height:28px;min-width:48px;max-width:48px;flex:0 0 48px;margin:0;padding:0;border:0;cursor:pointer;box-sizing:border-box;line-height:0}
        .japur-rpi-settings .rpi-toggle input{position:absolute!important;opacity:0!important;width:1px!important;height:1px!important;margin:0!important;padding:0!important;border:0!important;appearance:none;-webkit-appearance:none}
        .japur-rpi-settings .rpi-toggle-track{position:absolute;top:0;right:0;bottom:0;left:0;display:block;width:48px;height:28px;margin:0;padding:0;border-radius:999px;background:#8c8f94;transition:background .2s;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08);box-sizing:border-box}
        .japur-rpi-settings .rpi-toggle-thumb{position:absolute;top:4px;left:4px;display:block;width:20px;height:20px;margin:0;padding:0;border:0;border-radius:50%;background:#fff;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.25);box-sizing:border-box}
        .japur-rpi-settings .rpi-toggle input:checked + .rpi-toggle-track{background:#2271b1}
        .japur-rpi-settings .rpi-toggle input:checked + .rpi-toggle-track .rpi-toggle-thumb{left:24px}
        .japur-rpi-settings .rpi-toggle input:focus-visible + .rpi-toggle-track{outline:2px solid #72aee6;outline-offset:2px}
        .japur-rpi-settings .japi-panel-foot{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 24px;background:#fbfbfc}
        .japur-rpi-settings .japi-foot-help{font-size:12px;color:#646970;line-height:1.5}
        .japur-rpi-settings .japi-marker-box{margin-top:16px;padding:16px 18px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px}
        .japur-rpi-settings .japi-marker-box h3{margin:0 0 5px;font-size:14px;color:#1d2327}
        .japur-rpi-settings .japi-marker-box p{margin:0;font-size:13px;line-height:1.55;color:#646970}
        @media(max-width:782px){
            .japur-rpi-settings .japi-panel-head,.japur-rpi-settings .japi-panel-foot{align-items:flex-start;flex-direction:column}
            .japur-rpi-settings .japi-field-row{align-items:flex-start;flex-direction:column}
            .japur-rpi-settings .japi-field-control,.japur-rpi-settings .japi-position-control{width:100%}
            .japur-rpi-settings .japi-select,.japur-rpi-settings .japi-text{width:100%;box-sizing:border-box}
            .japur-rpi-settings .japi-position-control{align-items:flex-start;flex-direction:column}
            .japur-rpi-settings .rpi-toggle-control{align-self:flex-end}
        }
    </style>
    <?php
}

/* =========================
   LOAD CSS
========================= */
function JapurSuite_rpi_load_assets() {
    if (!JapurSuite_Core::module_enabled('rpi')) return;
    wp_enqueue_style('japur-suite-rpi-style', JAPUR_SUITE_URL . 'assets/rpi-pro/style.css', [], JAPUR_SUITE_VERSION);
}

add_action('wp_enqueue_scripts', 'JapurSuite_rpi_load_assets');