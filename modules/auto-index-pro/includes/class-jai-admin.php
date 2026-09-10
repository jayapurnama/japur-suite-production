<?php
if (!defined('ABSPATH')) exit;

class JAI_Pro_Admin {
    public function __construct(){
        add_action('admin_menu',[$this,'menu']);
        add_action('admin_init',[$this,'settings']);
        add_action('admin_post_jai_pro_bulk',[$this,'bulk']);
        add_action('admin_post_jai_pro_run',[$this,'run']);
        add_action('admin_post_jai_pro_gsc_callback',[ 'JAI_Pro_GSC', 'handle_callback' ]);
        add_action('admin_post_jai_pro_gsc_disconnect',[$this,'gsc_disconnect']);
        add_action('admin_post_jai_pro_gsc_submit_sitemap',[$this,'gsc_submit_sitemap']);
        add_action('admin_post_jai_pro_gsc_inspect',[$this,'gsc_inspect']);
        add_action('admin_post_jai_pro_gsc_analytics',[$this,'gsc_analytics']);
        add_action('admin_post_jai_pro_clear_logs',[$this,'clear_logs']);
        add_action('wp_ajax_jai_pro_test_key',[$this,'ajax_test_key']);
        add_action('admin_enqueue_scripts',[$this,'assets']);
    }
    public function menu(){ add_submenu_page('japur-suite','Index Auto Pro','Index Auto Pro','manage_options','japur-auto-index-pro',[$this,'page']); }
    public function settings(){
        register_setting('jai_pro_settings','jai_pro_enabled',['type'=>'integer','sanitize_callback'=>function($v){return $v?1:0;}]);
        register_setting('jai_pro_settings','jai_pro_batch',['type'=>'integer','sanitize_callback'=>function($v){return max(1,min(50,(int)$v));}]);
        register_setting('jai_pro_settings','jai_pro_max_attempts',['type'=>'integer','sanitize_callback'=>function($v){return max(1,min(10,(int)$v));}]);
        register_setting('jai_pro_settings','jai_pro_post_types',['type'=>'array','sanitize_callback'=>function($v){return array_map('sanitize_key',(array)$v);}]);
        register_setting('jai_pro_gsc_settings','jai_pro_gsc_property',['type'=>'string','sanitize_callback'=>function($v){return sanitize_text_field(trim((string)$v));}]);
        register_setting('jai_pro_gsc_settings','jai_pro_gsc_sitemap',['type'=>'string','sanitize_callback'=>function($v){return esc_url_raw(trim((string)$v));}]);
        register_setting('jai_pro_gsc_settings','jai_pro_gsc_auto_submit',['type'=>'integer','sanitize_callback'=>function($v){return $v?1:0;}]);
        register_setting('jai_pro_gsc_settings','jai_pro_gsc_auto_inspect',['type'=>'integer','sanitize_callback'=>function($v){return $v?1:0;}]);
    }
    public function assets($hook){ if($hook==='japur-suite_page_japur-auto-index-pro'){ wp_enqueue_style('jai-pro',JAI_PRO_URL.'assets/css/admin.css',[],JAI_PRO_VERSION); wp_enqueue_script('jai-pro',JAI_PRO_URL.'assets/js/admin.js',['jquery'],JAI_PRO_VERSION,true); wp_localize_script('jai-pro','JAIPro',['ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('jai_pro_test_key')]); } }
    private function notice(){
        // Redirect/query feedback is handled centrally by JapurSuite_Core::admin_feedback_footer().
        // Keep this hook only for backward compatibility with the page renderer.
    }
    public function page(){
        if(!current_user_can('manage_options')) return;
        $gsc_result=get_transient('jai_gsc_result_'.get_current_user_id());
        if($gsc_result) delete_transient('jai_gsc_result_'.get_current_user_id());
        $c=JAI_Pro_DB::counts();
        $log_per_page=5;
        $log_page=isset($_GET['jai_log_page'])?max(1,absint($_GET['jai_log_page'])):1;
        $log_total=JAI_Pro_DB::logs_total('IndexNow');
        $log_pages=max(1,(int)ceil($log_total/$log_per_page)); if($log_page>$log_pages)$log_page=$log_pages;
        $logs=JAI_Pro_DB::recent_logs($log_per_page,($log_page-1)*$log_per_page,'IndexNow');
        $gsc_log_page=isset($_GET['jai_gsc_log_page'])?max(1,absint($_GET['jai_gsc_log_page'])):1;
        $gsc_log_total=JAI_Pro_DB::logs_total('Google Search Console');
        $gsc_log_pages=max(1,(int)ceil($gsc_log_total/$log_per_page)); if($gsc_log_page>$gsc_log_pages)$gsc_log_page=$gsc_log_pages;
        $gsc_logs=JAI_Pro_DB::recent_logs($log_per_page,($gsc_log_page-1)*$log_per_page,'Google Search Console'); $types=get_post_types(['public'=>true],'objects');
        $gsc_sites=[]; $gsc_error='';
        if(JAI_Pro_GSC::connected()) { $sites=JAI_Pro_GSC::sites(); if(is_wp_error($sites)) $gsc_error=$sites->get_error_message(); else $gsc_sites=$sites; }
        $gsc_property=JAI_Pro_GSC::property(); $gsc_sitemap=JAI_Pro_GSC::sitemap(); if(!$gsc_sitemap) $gsc_sitemap=JAI_Pro_Sitemap::discover(); $current_site=JAI_Pro_Site::home_url();
        ?>
    <div class="wrap jai-wrap">

      <?php if($gsc_result): ?>
      <script>document.addEventListener('DOMContentLoaded',function(){if(window.JapurSuiteToast){window.JapurSuiteToast.show(<?php echo wp_json_encode((string)($gsc_result['message']??'')); ?>,<?php echo wp_json_encode((string)($gsc_result['type']??'info')); ?>);}});</script>
      <?php endif; ?>
      <?php if($gsc_result && !empty($gsc_result['details'])): ?>
      <div class="jai-gsc-result-card" id="jai-gsc-result-card" style="position:relative;margin:0 0 18px;padding:16px 46px 16px 16px;background:#fff;border:1px solid #dcdcde;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.03);">
        <button type="button" class="button-link" aria-label="Tutup hasil data" onclick="this.closest('#jai-gsc-result-card').remove()" style="position:absolute;right:12px;top:10px;font-size:22px;text-decoration:none;line-height:1;">&times;</button>
        <h2 style="margin:0 0 8px;font-size:16px;"><?php echo esc_html($gsc_result['message']); ?></h2>
        <div style="white-space:pre-wrap;line-height:1.6;color:#50575e;font-size:13px;"><?php echo esc_html($gsc_result['details']); ?></div>
      </div>
      <?php endif; ?>
      <div class="japi-head">
        <div>
          <div class="japi-eyebrow">JAPUR SUITE</div>
          <h1>Japur Auto Index PRO</h1>
        </div>
      </div>

      <div class="japi-status <?php $module_on = class_exists('JapurSuite_Core') ? JapurSuite_Core::module_enabled('index') : (bool) get_option('jai_pro_enabled',1); echo $module_on ? 'is-on' : 'is-off'; ?>">
        <span class="japi-status-dot"></span>
        <div class="japi-status-label">
          <strong>Modul <?php echo $module_on ? 'aktif' : 'nonaktif'; ?></strong>
        </div>
        <a class="japi-dashboard-link" href="<?php echo esc_url(admin_url('admin.php?page=japur-suite')); ?>">Dashboard</a>
      </div>
      <?php $this->notice(); ?>

      <style>
        /* v1.3.21 — Header + status card mengikuti standar modul Japur Suite. */
        .jai-wrap .japi-head{display:flex!important;flex-direction:column!important;justify-content:flex-start!important;align-items:flex-start!important;gap:3px!important;margin:22px 0 18px!important;text-align:left!important}
        .jai-wrap .japi-eyebrow{font-size:11px!important;font-weight:700!important;letter-spacing:1.4px!important;color:#2271b1!important;margin:0 0 2px!important;line-height:1.3!important}
        .jai-wrap .japi-head h1{font-size:23px!important;line-height:1.3!important;margin:0!important;font-weight:600!important;color:#1d2327!important}
        .jai-wrap .japi-status{display:flex!important;align-items:center!important;gap:12px!important;background:#fff!important;border:1px solid #dcdcde!important;border-radius:12px!important;padding:14px 16px!important;margin:0 0 16px!important;box-shadow:0 1px 2px rgba(0,0,0,.03)!important;box-sizing:border-box!important}
        .jai-wrap .japi-status-dot{width:10px!important;height:10px!important;border-radius:50%!important;flex:0 0 10px!important;background:#d63638!important}
        .jai-wrap .japi-status.is-on .japi-status-dot{background:#00a32a!important;box-shadow:0 0 0 4px #edfaef!important}
        .jai-wrap .japi-status.is-off .japi-status-dot{background:#d63638!important;box-shadow:0 0 0 4px #fcf0f1!important}
        .jai-wrap .japi-status-label{min-width:0!important;margin:0!important}
        .jai-wrap .japi-status strong{display:block!important;font-size:14px!important;line-height:1.4!important;color:#1d2327!important;margin:0!important}
        .jai-wrap .japi-dashboard-link{margin-left:auto!important;text-decoration:none!important;font-weight:600!important;white-space:nowrap!important;flex:0 0 auto!important}
        @media(max-width:782px){
          .jai-wrap .japi-head{margin:18px 0 16px!important}
          .jai-wrap .japi-head h1{font-size:23px!important}
          .jai-wrap .japi-status{width:100%!important}
          .jai-wrap .japi-status .japi-dashboard-link{margin-left:auto!important}
        }
      </style>
      <div class="jai-cards"><div><b><?php echo $c['pending'];?></b><small>Menunggu</small></div><div><b><?php echo $c['processing'];?></b><small>Diproses</small></div><div><b><?php echo $c['success'];?></b><small>Berhasil</small></div><div><b><?php echo $c['failed'];?></b><small>Gagal</small></div></div>
      <div class="jai-grid"><div class="jai-panel"><h2>Pengaturan</h2><form id="jai-pro-settings-form" method="post" action="options.php"><?php settings_fields('jai_pro_settings'); ?>
        <p><label><input type="checkbox" name="jai_pro_enabled" value="1" <?php checked(get_option('jai_pro_enabled',1),1);?>> Aktifkan Auto Index</label></p>
        <p><label>Jumlah URL per proses<br><input type="number" min="1" max="50" name="jai_pro_batch" value="<?php echo esc_attr(get_option('jai_pro_batch',5));?>"></label></p>
        <p><label>Maksimal percobaan<br><input type="number" min="1" max="10" name="jai_pro_max_attempts" value="<?php echo esc_attr(get_option('jai_pro_max_attempts',5));?>"></label></p>
        <p><strong>Post type yang diproses</strong><br><?php foreach($types as $t): if($t->name==='attachment') continue; ?><label class="type"><input type="checkbox" name="jai_pro_post_types[]" value="<?php echo esc_attr($t->name);?>" <?php checked(in_array($t->name,(array)get_option('jai_pro_post_types',['post']),true));?>> <?php echo esc_html($t->labels->singular_name);?></label><?php endforeach;?></p>
        <?php submit_button('Simpan Pengaturan'); ?></form>
        <hr><p><strong>Antrean Massal</strong></p><p class="description">Masukkan artikel yang sudah terbit ke antrean Auto Index tanpa mengubah konfigurasi API.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('jai_pro_bulk'); ?><input type="hidden" name="action" value="jai_pro_bulk"><button class="button button-primary">Kirim Semua Artikel Terbaru</button></form></div>
      <div class="jai-panel jai-gsc"><h2>Google Search Console</h2>
        <p class="description"><strong>Auto Submit:</strong> <?php echo get_option('jai_pro_gsc_auto_submit',1) ? 'Aktif' : 'Nonaktif'; ?> — publish/update artikel dapat memicu submit sitemap otomatis.</p>
        <p class="description">Modul resmi Search Console: hubungkan akun Google, pilih property, kelola sitemap, inspeksi URL, dan lihat data performa. Modul ini <strong>tidak</strong> mengklaim bisa memaksa indexing artikel biasa.</p>
        <div class="jai-api-hub-note"><strong>Google OAuth dikelola API Center.</strong> Status koneksi, Client ID/Secret, OAuth ulang, dan token bersama untuk Search Console + Blogger berada di satu pintu. Pengaturan Property, Sitemap, Auto Submit, dan URL Inspection tetap berada di Auto Index PRO.</div>
        <div class="jai-actions"><span class="jai-connected"><?php echo JAI_Pro_GSC::connected() ? 'Google terhubung' : 'Google belum terhubung'; ?></span> <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=japur-api-center'));?>">Kelola Google di API Center</a></div>
        <form method="post" action="options.php"><input type="hidden" name="option_page" value="jai_pro_gsc_settings"><input type="hidden" name="action" value="update"><?php settings_fields('jai_pro_gsc_settings'); ?>
          <table class="form-table"><tr><th><label>Property</label></th><td><?php if(JAI_Pro_GSC::connected() && $gsc_sites): ?><select name="jai_pro_gsc_property"><option value="">— Pilih Property —</option><?php foreach($gsc_sites as $site): ?><option value="<?php echo esc_attr($site['siteUrl']);?>" <?php selected($gsc_property,$site['siteUrl']);?>><?php echo esc_html($site['siteUrl']);?> — <?php echo esc_html($site['permissionLevel']??'');?></option><?php endforeach;?></select><?php else: ?><input type="text" class="regular-text code" name="jai_pro_gsc_property" value="<?php echo esc_attr($gsc_property);?>" placeholder="https://contoh.com/ atau sc-domain:contoh.com"><?php endif; ?></td></tr>
          <tr><th><label>Sitemap</label></th><td><input type="url" class="regular-text code" name="jai_pro_gsc_sitemap" value="<?php echo esc_attr($gsc_sitemap);?>"><p class="description">Default otomatis mencoba <code>/wp-sitemap.xml</code> lalu <code>/sitemap_index.xml</code>.</p></td></tr>
          <tr><th><label>Auto Submit Google</label></th><td><label><input type="checkbox" name="jai_pro_gsc_auto_submit" value="1" <?php checked(get_option('jai_pro_gsc_auto_submit',1),1);?>> Otomatis submit/update sitemap ke Google setelah artikel baru dipublish atau artikel publish diperbarui.</label><p class="description">Submit memakai API resmi Search Console dan dibatasi sekitar sekali per 10 menit saat ada banyak publish/update. Indexing tetap keputusan Google.</p></td></tr>
          <tr><th><label>Auto URL Inspection</label></th><td><label><input type="checkbox" name="jai_pro_gsc_auto_inspect" value="1" <?php checked(get_option('jai_pro_gsc_auto_inspect',1),1);?>> Otomatis inspeksi URL artikel baru atau artikel publish yang diperbarui.</label><p class="description">Menggunakan URL Inspection API untuk membaca status URL. Ini bukan Request Indexing dan tidak menjamin artikel langsung diindeks.</p></td></tr></table>
          <?php submit_button('Simpan Pengaturan GSC','secondary'); ?></form>
        <?php if($gsc_error): ?><div class="notice notice-error inline"><p><?php echo esc_html($gsc_error);?></p></div><?php endif; ?>
        <?php if(JAI_Pro_GSC::connected() && $gsc_property): ?>
        <hr><div class="jai-gsc-tools"><div><h3>Sitemap Google</h3><p>Property: <code><?php echo esc_html($gsc_property);?></code></p><form class="jai-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('jai_pro_gsc_submit_sitemap'); ?><input type="hidden" name="action" value="jai_pro_gsc_submit_sitemap"><button class="button button-primary">Kirim / Perbarui Sitemap</button></form> <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'japur-auto-index-pro','jai_gsc_refresh'=>1],admin_url('admin.php')));?>">Refresh Status</a></div>
        <?php $maps=JAI_Pro_GSC::sitemaps($gsc_property); if(!is_wp_error($maps) && $maps): ?><div class="jai-sitemap-table-wrap"><table class="widefat striped"><thead><tr><th>Sitemap</th><th>Terakhir Dikirim</th><th>Terakhir Diunduh</th><th>Error</th><th>Peringatan</th></tr></thead><tbody><?php foreach($maps as $m): ?><tr><td><code><?php echo esc_html($m['path']??''); ?></code></td><td><?php echo esc_html($m['lastSubmitted']??'-'); ?></td><td><?php echo esc_html($m['lastDownloaded']??'-'); ?></td><td><?php echo (int)($m['errors']??0); ?></td><td><?php echo (int)($m['warnings']??0); ?></td></tr><?php endforeach; ?></tbody></table></div><?php elseif(is_wp_error($maps)): ?><p class="description">Gagal membaca sitemap: <?php echo esc_html($maps->get_error_message());?></p><?php else: ?><p class="description">Belum ada sitemap yang terdaftar untuk property ini.</p><?php endif; ?></div>
        <div class="jai-gsc-tools"><h3>Inspeksi URL Google</h3><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('jai_pro_gsc_inspect'); ?><input type="hidden" name="action" value="jai_pro_gsc_inspect"><input type="url" class="regular-text code" name="url" placeholder="https://domain.com/artikel/" value="<?php echo esc_attr(isset($_GET['jai_gsc_url'])?wp_unslash($_GET['jai_gsc_url']):'');?>"> <button class="button">Inspeksi URL</button></form><p class="description">Hasil inspeksi berasal dari URL Inspection API dan tidak sama dengan tombol Request Indexing.</p></div>
        <div class="jai-gsc-tools"><h3>Performa Penelusuran</h3><form class="jai-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('jai_pro_gsc_analytics'); ?><input type="hidden" name="action" value="jai_pro_gsc_analytics"><select name="days"><option value="7">7 hari</option><option value="28" selected>28 hari</option><option value="90">90 hari</option></select> <button class="button">Ambil Data</button></form></div>
        <?php endif; ?>
      </div>

      <style>.jai-api-hub-note{margin:12px 0 16px;padding:12px 14px;border:1px solid #dcdcde;border-radius:10px;background:#f6f7f7;color:#50575e;line-height:1.55}.jai-api-hub-note strong{color:#1d2327}</style>
      <div class="jai-panel">
        <h2>Log IndexNow</h2>
        <div class="jai-log-table-wrap"><table class="widefat striped"><thead><tr><th>Waktu</th><th>URL</th><th>Provider</th><th>Status</th><th>HTTP</th><th>Pesan</th></tr></thead><tbody><?php if(!$logs):?><tr><td colspan="6">Belum ada log IndexNow.</td></tr><?php else: foreach($logs as $l):?><tr><td><?php echo esc_html($l->created_at);?></td><td><a href="<?php echo esc_url($l->url);?>" target="_blank" rel="noopener">Lihat</a></td><td><?php echo esc_html($l->provider);?></td><td><span class="status <?php echo esc_attr($l->status);?>"><?php echo esc_html($l->status);?></span></td><td><?php echo (int)$l->response_code;?></td><td><?php echo esc_html(wp_trim_words($l->message,12));?></td></tr><?php endforeach; endif;?></tbody></table></div>
        <?php if($log_pages>1): ?><div class="jai-pagination" aria-label="Halaman Log IndexNow"><?php for($i=1;$i<=$log_pages;$i++): ?><a class="button <?php echo $i===$log_page?'button-primary':''; ?>" href="<?php echo esc_url(add_query_arg(['page'=>'japur-auto-index-pro','jai_log_page'=>$i],admin_url('admin.php')));?>"><?php echo (int)$i; ?></a><?php endfor; ?></div><?php endif; ?>
      </div>
      <div class="jai-panel">
        <h2>Log Google Search Console</h2>
        <div class="jai-log-table-wrap"><table class="widefat striped"><thead><tr><th>Waktu</th><th>URL / Target</th><th>Status</th><th>HTTP</th><th>Pesan</th></tr></thead><tbody><?php if(!$gsc_logs):?><tr><td colspan="5">Belum ada log Google Search Console.</td></tr><?php else: foreach($gsc_logs as $l):?><tr><td><?php echo esc_html($l->created_at);?></td><td><?php if($l->url): ?><a href="<?php echo esc_url($l->url);?>" target="_blank" rel="noopener">Lihat</a><?php else: ?>-<?php endif; ?></td><td><span class="status <?php echo esc_attr($l->status);?>"><?php echo esc_html($l->status);?></span></td><td><?php echo (int)$l->response_code;?></td><td><?php echo esc_html(wp_trim_words($l->message,16));?></td></tr><?php endforeach; endif;?></tbody></table></div>
        <?php if($gsc_log_pages>1): ?><div class="jai-pagination" aria-label="Halaman Log Google Search Console"><?php for($i=1;$i<=$gsc_log_pages;$i++): ?><a class="button <?php echo $i===$gsc_log_page?'button-primary':''; ?>" href="<?php echo esc_url(add_query_arg(['page'=>'japur-auto-index-pro','jai_gsc_log_page'=>$i],admin_url('admin.php')));?>"><?php echo (int)$i; ?></a><?php endfor; ?></div><?php endif; ?>
        <div class="jai-log-actions">
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" id="jai-clear-logs-form">
            <?php wp_nonce_field('jai_pro_clear_logs'); ?><input type="hidden" name="action" value="jai_pro_clear_logs">
            <button class="button button-secondary">Hapus Semua Log</button>
          </form>
          <script>document.addEventListener('DOMContentLoaded',function(){var f=document.getElementById('jai-clear-logs-form');if(!f)return;f.addEventListener('submit',function(e){if(window.JapurSuiteConfirm){e.preventDefault();var form=this;window.JapurSuiteConfirm('Hapus semua log IndexNow dan Google Search Console? Pengaturan, token, dan antrean tidak akan dihapus.',function(){form.submit();},{title:'Hapus Semua Log',confirmText:'Hapus Log'});}});});</script>
          <span class="description">Menghapus histori log IndexNow dan GSC saja. Konfigurasi serta antrean tetap aman.</span>
        </div>
      </div>
      <div class="jai-panel"><h2>Tentang</h2><p><strong>Japur Auto Index PRO</strong> dibuat oleh <strong>Japur Ganteng</strong>. Fitur: antrean URL, IndexNow, retry otomatis, log, bulk submit, Google Search Console OAuth, sitemap submission, URL Inspection, dan Search Analytics.</p><p><strong>Catatan:</strong> Google Search Console API dapat mengelola sitemap dan membaca data/inspeksi, tetapi tidak menyediakan API umum untuk memaksa indexing artikel biasa.</p></div>
    </div><?php }
    public function clear_logs(){
        if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_die('Modul Japur Auto Index PRO sedang nonaktif.');
        if(!current_user_can('manage_options')||!check_admin_referer('jai_pro_clear_logs')) wp_die('Akses ditolak.');
        JAI_Pro_DB::clear_logs();
        wp_safe_redirect(add_query_arg(['page'=>'japur-auto-index-pro','jai_msg'=>rawurlencode('Semua log IndexNow dan Google Search Console berhasil dihapus.')],admin_url('admin.php')));
        exit;
    }
    public function bulk(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_die('Modul Japur Auto Index PRO sedang nonaktif.'); if(!current_user_can('manage_options')||!check_admin_referer('jai_pro_bulk')) wp_die('Akses ditolak.'); $types=(array)get_option('jai_pro_post_types',['post']); $q=new WP_Query(['post_type'=>$types,'post_status'=>'publish','posts_per_page'=>100,'fields'=>'ids','orderby'=>'date','order'=>'DESC']); $n=0; foreach($q->posts as $id){$u=get_permalink($id);if($u&&JAI_Pro_DB::enqueue($u,$id))$n++;} wp_safe_redirect(add_query_arg('jai_msg',rawurlencode("$n URL dimasukkan ke antrean."),admin_url('admin.php?page=japur-auto-index-pro')));exit; }
    public function ajax_test_key(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_send_json_error(['message'=>'Modul Japur Auto Index PRO sedang nonaktif.'],403); if(!current_user_can('manage_options')||!check_ajax_referer('jai_pro_test_key','nonce',false)) wp_send_json_error(['message'=>'Akses ditolak.'],403); $r=JAI_Pro_IndexNow::verify_key_location(); if($r['ok']) wp_send_json_success($r); wp_send_json_error($r,$r['code']?$r['code']:500); }
    public function run(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_die('Modul Japur Auto Index PRO sedang nonaktif.'); if(!current_user_can('manage_options')||!check_admin_referer('jai_pro_run')) wp_die('Akses ditolak.'); JAI_Pro_Queue::process(); wp_safe_redirect(admin_url('admin.php?page=japur-auto-index-pro'));exit; }
    public function gsc_disconnect(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_die('Modul Japur Auto Index PRO sedang nonaktif.'); if(!current_user_can('manage_options')||!check_admin_referer('jai_pro_gsc_disconnect')) wp_die('Akses ditolak.'); JAI_Pro_GSC::disconnect(); wp_safe_redirect(add_query_arg(['page'=>'japur-auto-index-pro','jai_gsc_msg'=>rawurlencode('Google Search Console diputuskan.'),'jai_gsc_type'=>'success'],admin_url('admin.php'))); exit; }
    public function gsc_submit_sitemap(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_die('Modul Japur Auto Index PRO sedang nonaktif.'); if(!current_user_can('manage_options')||!check_admin_referer('jai_pro_gsc_submit_sitemap')) wp_die('Akses ditolak.'); $site=JAI_Pro_GSC::property(); $sitemap=JAI_Pro_GSC::sitemap(); $r=JAI_Pro_GSC::submit_sitemap($site,$sitemap); $ok=!is_wp_error($r); $http_code = 0; if (!$ok) { $ed = $r->get_error_data(); if (is_array($ed) && isset($ed['status'])) $http_code = (int) $ed['status']; } JAI_Pro_DB::log($sitemap,0,'Google Search Console',$ok?'success':'failed',$ok?200:$http_code,$ok?'Sitemap berhasil dikirim ke Search Console.':$r->get_error_message()); $this->gsc_result($ok?'success':'error',$ok?'Sitemap berhasil dikirim ke Google Search Console.':'Gagal mengirim sitemap: '.$r->get_error_message()); }
    public function gsc_inspect(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_die('Modul Japur Auto Index PRO sedang nonaktif.'); if(!current_user_can('manage_options')||!check_admin_referer('jai_pro_gsc_inspect')) wp_die('Akses ditolak.'); $url=isset($_POST['url'])?esc_url_raw(wp_unslash($_POST['url'])):''; $site=JAI_Pro_GSC::property(); $r=JAI_Pro_GSC::inspect($site,$url); if(is_wp_error($r)){ $ed=$r->get_error_data(); $code=(is_array($ed)&&isset($ed['status']))?(int)$ed['status']:0; JAI_Pro_DB::log($url,0,'Google Search Console','failed',$code,'Inspeksi URL gagal: '.$r->get_error_message()); $this->gsc_result('error','Inspeksi gagal: '.$r->get_error_message()); return; } $data=$r['data']['inspectionResult']??[]; $index=$data['indexStatusResult']??[]; $verdict=$index['verdict']??'UNKNOWN'; $coverage=$index['coverageState']??'-'; $robots=$index['robotsTxtState']??'-'; $details="Verdict: $verdict\nCoverage: $coverage\nRobots.txt: $robots\nLast Crawl: ".($index['lastCrawlTime']??'-')."\nGoogle Canonical: ".($index['googleCanonical']??'-'); JAI_Pro_DB::log($url,0,'Google Search Console',$verdict==='PASS'?'success':($verdict==='NEUTRAL'?'neutral':'failed'),200,'Inspeksi URL: '.$details); $this->gsc_result($verdict==='PASS'?'success':'warning','Hasil inspeksi URL: '.$url,$details); }
    public function gsc_analytics(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) wp_die('Modul Japur Auto Index PRO sedang nonaktif.'); if(!current_user_can('manage_options')||!check_admin_referer('jai_pro_gsc_analytics')) wp_die('Akses ditolak.'); $days=isset($_POST['days'])?absint($_POST['days']):28; $r=JAI_Pro_GSC::analytics(JAI_Pro_GSC::property(),$days); if(is_wp_error($r)){ $ed=$r->get_error_data(); $code=(is_array($ed)&&isset($ed['status']))?(int)$ed['status']:0; JAI_Pro_DB::log(JAI_Pro_GSC::property(),0,'Google Search Console','failed',$code,'Gagal mengambil performa: '.$r->get_error_message()); $this->gsc_result('error','Gagal mengambil performa: '.$r->get_error_message()); return; } $rows=$r['data']['rows']??[]; $row=$rows[0]??[]; $clicks=(float)($row['clicks']??0); $impressions=(float)($row['impressions']??0); $ctr=(float)($row['ctr']??($impressions>0?$clicks/$impressions:0)); if($ctr<1 && $impressions>0) $ctr*=100; $avg=(float)($row['position']??0); $details="Periode: $days hari\nKlik: ".number_format_i18n($clicks)."\nImpresi: ".number_format_i18n($impressions)."\nCTR: ".number_format_i18n($ctr,2)."%\nPosisi rata-rata: ".number_format_i18n($avg,2); JAI_Pro_DB::log(JAI_Pro_GSC::property(),0,'Google Search Console','success',200,'Performa Search Console berhasil diambil. '.$details); $this->gsc_result('success','Data performa Google Search Console berhasil diambil.',$details); }
    private function gsc_result($type,$message,$details=''){ set_transient('jai_gsc_result_'.get_current_user_id(),['type'=>$type,'message'=>$message,'details'=>$details],MINUTE_IN_SECONDS); wp_safe_redirect(admin_url('admin.php?page=japur-auto-index-pro')); exit; }
}
