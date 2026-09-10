<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Lead Domain Auto Link module for Japur Suite.
 * Keeps the legacy lda_settings option for compatibility.
 */
final class JapurSuite_Lead_Domain_Auto_Link {
    const OPTION = 'lda_settings';

    public function __construct() {
        if (!JapurSuite_Core::module_enabled('lead')) return;
        add_action('admin_init', [$this, 'register_settings']);
        add_action('save_post', [$this, 'save_post'], 20, 3);
        add_action('add_meta_boxes', [$this, 'meta_box']);
        add_action('admin_post_lda_process_post', [$this, 'process_one']);
        add_action('wp_ajax_lda_process_batch', [$this, 'process_batch']);
    }

    private function site_defaults() {
        $site_url = home_url('/');
        $parsed   = wp_parse_url($site_url);
        $domain   = isset($parsed['host']) ? strtolower($parsed['host']) : '';

        return [
            'domain' => $domain,
            'url'    => $site_url,
            'auto'   => 1,
            'target' => '_self',
            'rel'    => '',
        ];
    }

    private function defaults() {
        return $this->site_defaults();
    }

    private function settings() {
        $saved = (array) get_option(self::OPTION, []);
        $defaults = $this->defaults();

        // Jika konfigurasi masih memakai default lama dari plugin gabungan,
        // otomatis pindahkan ke domain situs WordPress tempat plugin dipasang.
        if (empty($saved['domain']) || isset($saved['domain']) && strtolower(trim((string) $saved['domain'])) === 'suratkami.com') {
            $saved['domain'] = $defaults['domain'];
        }
        if (empty($saved['url']) || isset($saved['url']) && untrailingslashit((string) $saved['url']) === 'https://suratkami.com') {
            $saved['url'] = $defaults['url'];
        }

        return wp_parse_args($saved, $defaults);
    }

    public function register_settings() {
        register_setting('lda_settings_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $d = $this->defaults();
        $domain = isset($input['domain']) ? sanitize_text_field($input['domain']) : $d['domain'];
        $domain = strtolower(trim((string) preg_replace('#^https?://#i', '', $domain)));
        $domain = preg_replace('#/.*$#', '', $domain);
        $url = isset($input['url']) ? esc_url_raw(trim($input['url'])) : $d['url'];
        if (!$url) { $url = 'https://' . $domain; }
        $allowed = ['nofollow', 'sponsored', 'ugc', 'noopener', 'noreferrer'];
        $rels = isset($input['rel']) ? preg_split('/[\s,]+/', strtolower(sanitize_text_field($input['rel']))) : [];
        return [
            'domain' => $domain,
            'url'    => $url,
            'auto'   => empty($input['auto']) ? 0 : 1,
            'target' => (isset($input['target']) && $input['target'] === '_blank') ? '_blank' : '_self',
            'rel'    => implode(' ', array_unique(array_intersect($rels, $allowed))),
        ];
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $s = $this->settings();
        $module_on = JapurSuite_Core::module_enabled('lead');
        ?>
        <div class="wrap japur-lead-settings">
            <div class="japi-head">
                <div>
                    <div class="japi-eyebrow">JAPUR SUITE</div>
                    <h1>Lead Domain Auto Link</h1>
                </div>
            </div>

            <div class="japi-status <?php echo $module_on ? 'is-on' : 'is-off'; ?>">
                <span class="japi-status-dot"></span>
                <div class="japi-status-label">
                    <strong><?php echo $module_on ? 'Modul aktif' : 'Modul nonaktif'; ?></strong>
                </div>
                <a class="japi-dashboard-link" href="<?php echo esc_url(admin_url('admin.php?page=japur-suite')); ?>">Dashboard</a>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('lda_settings_group'); ?>
                <div class="japi-panel">
                    <div class="japi-panel-head">
                        <div>
                            <h2>Pengaturan Lead Domain</h2>
                            <p>Atur domain, URL tujuan, proses otomatis, target tab, dan atribut rel.</p>
                        </div>
                        <span class="japi-badge">7 Pengaturan</span>
                    </div>

                    <div class="japi-fields">
                        <div class="japi-field-row">
                            <div class="japi-field-copy">
                                <div class="japi-field-title">Nama Domain</div>
                                <div class="japi-field-desc">Nama domain yang akan dikenali dan dijadikan link pada lead artikel.</div>
                            </div>
                            <div class="japi-field-control">
                                <input class="japi-text" name="<?php echo esc_attr(self::OPTION); ?>[domain]" value="<?php echo esc_attr($s['domain']); ?>" placeholder="contoh.com">
                            </div>
                        </div>

                        <div class="japi-field-row">
                            <div class="japi-field-copy">
                                <div class="japi-field-title">URL Tujuan</div>
                                <div class="japi-field-desc">Alamat yang dibuka ketika nama domain pada lead diklik.</div>
                            </div>
                            <div class="japi-field-control">
                                <input class="japi-text japi-url" type="url" name="<?php echo esc_attr(self::OPTION); ?>[url]" value="<?php echo esc_attr($s['url']); ?>" placeholder="https://contoh.com">
                            </div>
                        </div>

                        <div class="japi-field-row">
                            <div class="japi-field-copy">
                                <div class="japi-field-title">Proses Otomatis</div>
                                <div class="japi-field-desc">Buat link domain otomatis ketika artikel disimpan.</div>
                            </div>
                            <label class="japi-switch" for="lda-auto-toggle">
                                <input id="lda-auto-toggle" type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[auto]" value="1" <?php checked($s['auto'], 1); ?> aria-label="Proses otomatis">
                                <span class="japi-switch-track"><span class="japi-switch-thumb"></span></span>
                            </label>
                        </div>

                        <div class="japi-field-row">
                            <div class="japi-field-copy">
                                <div class="japi-field-title">Target Link</div>
                                <div class="japi-field-desc">Tentukan apakah link dibuka pada tab yang sama atau tab baru.</div>
                            </div>
                            <div class="japi-field-control">
                                <select name="<?php echo esc_attr(self::OPTION); ?>[target]" class="japi-select">
                                    <option value="_self" <?php selected($s['target'], '_self'); ?>>Tab yang sama</option>
                                    <option value="_blank" <?php selected($s['target'], '_blank'); ?>>Tab baru</option>
                                </select>
                            </div>
                        </div>

                        <div class="japi-field-row">
                            <div class="japi-field-copy">
                                <div class="japi-field-title">Rel</div>
                                <div class="japi-field-desc">Atribut rel untuk link. Kosongkan jika tidak diperlukan; saat tab baru dipilih, noopener diterapkan otomatis.</div>
                            </div>
                            <div class="japi-field-control">
                                <input class="japi-text" name="<?php echo esc_attr(self::OPTION); ?>[rel]" value="<?php echo esc_attr($s['rel']); ?>" placeholder="nofollow">
                            </div>
                        </div>

                        <div class="japi-field-row">
                            <div class="japi-field-copy">
                                <div class="japi-field-title">Pemrosesan Tampilan</div>
                                <div class="japi-field-desc">Proses lead juga saat ditampilkan di halaman depan, berguna untuk artikel lama.</div>
                            </div>
                            <label class="japi-switch" for="lda-frontend-toggle">
                                <input id="lda-frontend-toggle" type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[frontend]" value="1" <?php checked($s['frontend'], 1); ?> aria-label="Pemrosesan tampilan">
                                <span class="japi-switch-track"><span class="japi-switch-thumb"></span></span>
                            </label>
                        </div>

                        <div class="japi-field-row">
                            <div class="japi-field-copy">
                                <div class="japi-field-title">Huruf Besar/Kecil</div>
                                <div class="japi-field-desc">Bedakan huruf besar dan kecil saat mencari nama domain. Biasanya sebaiknya dimatikan.</div>
                            </div>
                            <label class="japi-switch" for="lda-case-toggle">
                                <input id="lda-case-toggle" type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[case_sensitive]" value="1" <?php checked($s['case_sensitive'], 1); ?> aria-label="Bedakan huruf besar dan kecil">
                                <span class="japi-switch-track"><span class="japi-switch-thumb"></span></span>
                            </label>
                        </div>
                    </div>

                    <div class="japi-panel-foot">
                        <div class="japi-help"><span></span><span>Link hanya diproses pada lead/paragraf pertama artikel.</span></div>
                        <button type="submit" class="button button-primary button-large">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>

            <div class="japi-panel japi-batch-panel">
                <div class="japi-panel-head">
                    <div>
                        <h2>Terapkan ke Semua Artikel</h2>
                        <p>Proses artikel terbit secara bertahap, maksimal 25 artikel setiap langkah.</p>
                    </div>
                    <span class="japi-badge">Proses Massal</span>
                </div>
                <div class="japi-batch-body">
                    <button type="button" class="button button-primary button-large" id="lda-start-all">Terapkan ke Semua Artikel</button>
                    <div id="lda-progress" class="japi-progress" style="display:none">
                        <div class="japi-progress-track"><div id="lda-bar" class="japi-progress-bar"></div></div>
                        <p id="lda-status">Memulai...</p>
                    </div>
                </div>
            </div>

            <div class="japi-marker-box">
                <h3>Catatan</h3>
                <p>Fitur ini mencari nama domain pada lead/paragraf pertama artikel dan menjadikannya link aktif sesuai pengaturan di atas. Proses massal berjalan bertahap agar tidak membebani server.</p>
            </div>
        </div>
        <script>
        (function(){
            var btn=document.getElementById('lda-start-all'),box=document.getElementById('lda-progress'),bar=document.getElementById('lda-bar'),status=document.getElementById('lda-status');
            if(!btn)return;
            btn.addEventListener('click',function(){
                if(window.JapurSuiteConfirm){ window.JapurSuiteConfirm('Yakin menerapkan link domain ke semua artikel yang sudah terbit?',function(){ startBatch(); },{title:'Terapkan ke Semua Artikel',confirmText:'Mulai Proses'}); return; }
                startBatch();
            });
            function startBatch(){
                btn.disabled=true;box.style.display='block';bar.style.width='0%';status.textContent='Menyiapkan artikel...';
                var data=new FormData();data.append('action','lda_process_batch');data.append('nonce','<?php echo esc_js(wp_create_nonce('lda_process_batch')); ?>');data.append('offset','0');run(data);
            }
            function run(data){
                fetch(ajaxurl,{method:'POST',body:data,credentials:'same-origin'}).then(function(r){
                    return r.text().then(function(text){
                        var res;
                        try{res=JSON.parse(text);}catch(e){throw new Error('Respons server tidak valid (HTTP '+r.status+').');}
                        return res;
                    });
                }).then(function(res){
                    if(!res.success) throw new Error((res.data&&res.data.message)?res.data.message:(typeof res.data==='string'?res.data:'Terjadi galat.'));
                    var d=res.data,pct=d.total?Math.round((d.next_offset/d.total)*100):100;if(pct>100)pct=100;
                    bar.style.width=pct+'%';status.textContent='Diproses '+d.next_offset+' dari '+d.total+' artikel. Diperbarui: '+d.changed+'.';
                    if(d.done){bar.style.width='100%';status.textContent='Selesai! '+d.total+' artikel diperiksa, '+d.changed+' artikel diperbarui.';if(window.JapurSuiteToast){window.JapurSuiteToast.show('Proses Lead Domain selesai.','success',d.total+' artikel diperiksa, '+d.changed+' artikel diperbarui.');}else{var n=document.createElement('div');n.className='notice notice-success is-dismissible';n.innerHTML='<p>Proses Lead Domain selesai. '+d.total+' artikel diperiksa, '+d.changed+' artikel diperbarui.</p>';document.querySelector('.japur-lead-settings')?.prepend(n);}btn.disabled=false;return;}
                    var next=new FormData();next.append('action','lda_process_batch');next.append('nonce','<?php echo esc_js(wp_create_nonce('lda_process_batch')); ?>');next.append('offset',String(d.next_offset));next.append('changed',String(d.changed));setTimeout(function(){run(next);},150);
                }).catch(function(e){status.textContent='Galat: '+e.message;if(window.JapurSuiteToast)window.JapurSuiteToast.show('Proses Lead Domain gagal.','error',e.message);btn.disabled=false;});
            }
        })();
        </script>
        <style>
            .japur-lead-settings{max-width:1080px}
            .japur-lead-settings .japi-head{display:flex;flex-direction:column;justify-content:flex-start;align-items:flex-start;gap:3px;margin:22px 0 18px;text-align:left}
            .japur-lead-settings .japi-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.4px;color:#2271b1;margin-bottom:2px}
            .japur-lead-settings .japi-head h1{font-size:23px;line-height:1.3;margin:0;font-weight:600;color:#1d2327}
            .japur-lead-settings .japi-status{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
            .japur-lead-settings .japi-status-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px;background:#d63638}
            .japur-lead-settings .japi-status.is-on .japi-status-dot{background:#00a32a;box-shadow:0 0 0 4px #edfaef}
            .japur-lead-settings .japi-status-label{min-width:0}
            .japur-lead-settings .japi-status strong{display:block;font-size:14px;color:#1d2327}
            .japur-lead-settings .japi-dashboard-link{margin-left:auto;text-decoration:none;font-weight:600;white-space:nowrap;flex:0 0 auto}
            .japur-lead-settings .japi-panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;box-shadow:0 2px 7px rgba(0,0,0,.04);margin-bottom:16px}
            .japur-lead-settings .japi-panel-head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:22px 24px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}
            .japur-lead-settings .japi-panel-head h2{font-size:18px;margin:0 0 5px;color:#1d2327}
            .japur-lead-settings .japi-panel-head p{margin:0;color:#646970;font-size:13px}
            .japur-lead-settings .japi-badge{font-size:12px;font-weight:700;color:#50575e;background:#f0f0f1;border-radius:999px;padding:6px 10px;white-space:nowrap}
            .japur-lead-settings .japi-fields{padding:0 24px}
            .japur-lead-settings .japi-field-row{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:18px 0;border-bottom:1px solid #eee}
            .japur-lead-settings .japi-field-row:last-child{border-bottom:0}
            .japur-lead-settings .japi-field-copy{min-width:0;flex:1}
            .japur-lead-settings .japi-field-title{font-size:15px;font-weight:700;color:#1d2327;margin-bottom:4px}
            .japur-lead-settings .japi-field-desc{font-size:13px;line-height:1.5;color:#646970}
            .japur-lead-settings .japi-field-control{flex:0 0 auto}
            .japur-lead-settings .japi-text,.japur-lead-settings .japi-select{min-height:38px;border:1px solid #8c8f94;border-radius:6px;padding:5px 10px;background:#fff;box-sizing:border-box}
            .japur-lead-settings .japi-text{width:260px}
            .japur-lead-settings .japi-url{width:320px}
            .japur-lead-settings .japi-select{min-width:160px}
            .japur-lead-settings .japi-switch{position:relative;display:block !important;width:48px;height:28px;min-width:48px;flex:0 0 48px;cursor:pointer;margin:0;padding:0;line-height:28px;box-sizing:border-box;vertical-align:middle}
            .japur-lead-settings .japi-switch input{position:absolute !important;opacity:0 !important;width:1px !important;height:1px !important;margin:0 !important;padding:0 !important;border:0 !important;box-shadow:none !important}
            .japur-lead-settings .japi-switch-track{position:absolute;inset:0;width:48px;height:28px;display:block;box-sizing:border-box;border-radius:999px;background:#8c8f94;transition:.2s;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
            .japur-lead-settings .japi-switch-thumb{position:absolute;top:4px;left:4px;width:20px;height:20px;display:block;box-sizing:border-box;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
            .japur-lead-settings .japi-switch input:checked + .japi-switch-track{background:#2271b1}
            .japur-lead-settings .japi-switch input:checked + .japi-switch-track .japi-switch-thumb{left:24px}
            .japur-lead-settings .japi-switch input:focus-visible + .japi-switch-track{outline:2px solid #72aee6;outline-offset:2px}
            .japur-lead-settings .japi-panel-foot{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 24px;background:#fbfbfc;border-top:1px solid #e2e4e7}
            .japur-lead-settings .japi-help{display:flex;gap:9px;align-items:flex-start;color:#646970;font-size:12px;line-height:1.5;max-width:700px}
            .japur-lead-settings .japi-batch-body{padding:22px 24px}
            .japur-lead-settings .japi-progress{max-width:700px;margin-top:18px}
            .japur-lead-settings .japi-progress-track{height:24px;border-radius:999px;overflow:hidden;background:#f0f0f1;border:1px solid #dcdcde}
            .japur-lead-settings .japi-progress-bar{height:100%;width:0%;background:#2271b1;transition:width .2s}
            .japur-lead-settings .japi-progress p{margin:10px 0 0;color:#646970;font-size:13px}
            .japur-lead-settings .japi-marker-box{margin-top:16px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px;padding:18px 20px}
            .japur-lead-settings .japi-marker-box h3{margin:0 0 5px;font-size:14px;color:#1d2327}
            .japur-lead-settings .japi-marker-box p{margin:0;font-size:13px;line-height:1.55;color:#646970}
            @media(max-width:782px){
                .japur-lead-settings .japi-panel-head,.japur-lead-settings .japi-panel-foot{align-items:flex-start;flex-direction:column}
                .japur-lead-settings .japi-field-row{align-items:flex-start;flex-direction:column}
                .japur-lead-settings .japi-field-control{width:100%}
                .japur-lead-settings .japi-text,.japur-lead-settings .japi-url,.japur-lead-settings .japi-select{width:100%;max-width:none}
                .japur-lead-settings .japi-switch{align-self:flex-end}
            }
        </style>
        <?php
    }

    private function split_lead($content) {
        if (preg_match('/^\s*(<p\b[^>]*>.*?<\/p>)/is', $content, $m)) return [$m[1], substr($content, strlen($m[0]))];
        $parts = preg_split("/\r\n|\r|\n/", $content, 2);
        if (count($parts) === 2 && trim(wp_strip_all_tags($parts[0])) !== '') return [$parts[0], $parts[1]];
        return [$content, ''];
    }

    private function link_domain($html) {
        $s = $this->settings();
        $domain = trim($s['domain']);
        if (!$domain) return $html;
        $pattern = '/(?<![A-Za-z0-9._-])' . preg_quote($domain, '/') . '(?![A-Za-z0-9._-])/iu';
        $attrs = 'href="' . esc_url($s['url']) . '" target="' . esc_attr($s['target']) . '"';
        $rel = trim($s['rel']);
        if ($s['target'] === '_blank' && !$rel) $rel = 'noopener';
        if ($rel) $attrs .= ' rel="' . esc_attr($rel) . '"';
        $link = '<a ' . $attrs . '>$0</a>';
        $tokens = preg_split('/(<!--.*?-->|<script\b[^>]*>.*?<\/script\s*>|<style\b[^>]*>.*?<\/style\s*>|<a\b[^>]*>.*?<\/a\s*>|<[^>]+>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($tokens as $token) {
            if ($token === '') continue;
            if (preg_match('/^<!--|^<script\b|^<style\b|^<a\b|^</is', $token)) { $out .= $token; continue; }
            $out .= preg_replace($pattern, $link, $token);
        }
        return $out;
    }

    private function process_content($content) {
        [$lead, $rest] = $this->split_lead($content);
        return $this->link_domain($lead) . $rest;
    }

    public function save_post($post_id, $post, $update) {
        $s = $this->settings();
        if (empty($s['auto']) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$post || $post->post_type !== 'post' || !current_user_can('edit_post', $post_id)) return;
        $new = $this->process_content($post->post_content);
        if ($new !== $post->post_content) {
            remove_action('save_post', [$this, 'save_post'], 20);
            wp_update_post(['ID' => $post_id, 'post_content' => $new]);
            add_action('save_post', [$this, 'save_post'], 20, 3);
        }
    }

    public function meta_box() { add_meta_box('japur_suite_lda_box', 'Lead Domain Auto Link', [$this, 'meta_box_html'], 'post', 'side'); }

    public function meta_box_html($post) {
        $url = wp_nonce_url(admin_url('admin-post.php?action=lda_process_post&post_id=' . absint($post->ID)), 'lda_process_post_' . $post->ID);
        echo '<p>Proses domain pada lead artikel.</p><a class="button button-primary" href="' . esc_url($url) . '">Proses Link Lead</a>';
    }

    public function process_one() {
        $id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (!$id || !current_user_can('edit_post', $id)) wp_die('Tidak diizinkan.');
        check_admin_referer('lda_process_post_' . $id);
        $post = get_post($id);
        if ($post) {
            $new = $this->process_content($post->post_content);
            if ($new !== $post->post_content) wp_update_post(['ID' => $id, 'post_content' => $new]);
        }
        wp_safe_redirect(admin_url('post.php?post=' . $id . '&action=edit'));
        exit;
    }

    public function process_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('Anda tidak memiliki izin.', 403);
        check_ajax_referer('lda_process_batch', 'nonce');
        @ignore_user_abort(true); @set_time_limit(20);
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $changed = isset($_POST['changed']) ? absint($_POST['changed']) : 0;
        $ids = get_posts(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>25,'offset'=>$offset,'orderby'=>'ID','order'=>'ASC','fields'=>'ids','no_found_rows'=>true,'suppress_filters'=>true]);
        foreach ($ids as $id) {
            $post = get_post($id); if (!$post) continue;
            $new = $this->process_content($post->post_content);
            if ($new !== $post->post_content) { wp_update_post(['ID'=>$id,'post_content'=>$new]); $changed++; }
        }
        $count = count($ids); $next = $offset + $count;
        $total = $count < 25 ? $next : (int) wp_count_posts('post')->publish;
        wp_send_json_success(['next_offset'=>$next,'total'=>$total,'changed'=>$changed,'done'=>$count < 25]);
    }
}

new JapurSuite_Lead_Domain_Auto_Link();
