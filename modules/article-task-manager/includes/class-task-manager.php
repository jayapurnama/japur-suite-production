<?php
if (!defined('ABSPATH')) exit;

class JAT_Task_Manager {
    private static $instance = null;
    private $table = '';

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'japur_article_tasks';
        add_action('admin_menu', [$this, 'menu'], 32);
        add_action('admin_init', [$this, 'maybe_install']);
        add_action('japur_article_published', [$this, 'record_publish'], 10, 1);
        add_action('wp_ajax_jat_refresh_dashboard', [$this, 'ajax_refresh_dashboard']);
    }

    public function maybe_install() {
        if (get_option('jat_db_version') === JAT_VER) return;
        $this->install();
    }

    private function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_key varchar(190) NOT NULL,
            published_at datetime NOT NULL,
            target_key varchar(190) NOT NULL,
            platform varchar(30) NOT NULL,
            target_name text NOT NULL,
            article_title text NOT NULL,
            post_id varchar(100) NOT NULL DEFAULT '',
            article_url text NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY event_key (event_key),
            KEY published_at (published_at),
            KEY target_day (target_key, published_at)
        ) $charset;";
        dbDelta($sql);
        update_option('jat_db_version', JAT_VER, false);
    }

    public function menu() {
        add_submenu_page('japur-suite', 'Manajemen Artikel', 'Manajemen Artikel', 'edit_posts', 'japur-article-tasks', [$this, 'page']);
    }

    private function get_targets() {
        $targets = [];
        $local = (bool)get_option('jaf_local_adsense', 0);
        if ($local) {
            $targets['local:jayapurnama'] = [
                'key'=>'local:jayapurnama', 'platform'=>'wordpress', 'type'=>'local',
                'id'=>'', 'name'=>get_bloginfo('name'), 'domain'=>$this->local_domain()
            ];
        }
        $o = get_option('jaf_options', []); if (!is_array($o)) $o=[];
        foreach ((array)($o['wordpress_profiles'] ?? []) as $id=>$p) {
            if (!is_array($p) || empty($p['adsense'])) continue;
            $name=trim((string)($p['name']??'Website WordPress'));
            $targets['wordpress:'.sanitize_key($id)] = ['key'=>'wordpress:'.sanitize_key($id),'platform'=>'wordpress','type'=>'profile','id'=>sanitize_key($id),'name'=>$name,'domain'=>$this->host($p['site_url']??'')];
        }
        foreach ((array)($o['blogger_profiles'] ?? []) as $id=>$p) {
            if (!is_array($p) || empty($p['adsense'])) continue;
            $name=trim((string)($p['name']??'Profil Blogger'));
            $targets['blogger:'.sanitize_key($id)] = ['key'=>'blogger:'.sanitize_key($id),'platform'=>'blogger','type'=>'profile','id'=>sanitize_key($id),'name'=>$name,'domain'=>$this->host($p['blog_url']??'')];
        }
        return $targets;
    }

    private function local_domain() { return $this->host(home_url('/')); }
    private function host($url) {
        $host=wp_parse_url(trim((string)$url), PHP_URL_HOST);
        if (!$host) $host=trim((string)$url);
        $host=strtolower((string)$host); $host=preg_replace('/^www\./','',$host); return trim($host,"/ \t\n\r\0\x0B");
    }

    private function settings() {
        $s=get_option('jat_settings', []); if (!is_array($s)) $s=[];
        $s['default_target']=max(0,absint($s['default_target']??0));
        $s['targets']=is_array($s['targets']??null)?$s['targets']:[];
        return $s;
    }

    private function target_goal($key,$settings) {
        if (array_key_exists($key,$settings['targets'])) return max(0,absint($settings['targets'][$key]));
        return max(0,absint($settings['default_target']));
    }

    public function record_publish($event) {
        if (!is_array($event)) return;
        $key=sanitize_text_field($event['event_key']??'');
        $target=sanitize_text_field($event['target_key']??'');
        if ($key==='' || $target==='') return;
        if (!isset($this->get_targets()[$target])) return; // only marked Web Adsense targets count
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$this->table} (event_key,published_at,target_key,platform,target_name,article_title,post_id,article_url,user_id) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%d)",
            $key, current_time('mysql'), $target, sanitize_key($event['platform']??''), sanitize_text_field($event['target_name']??''), sanitize_text_field($event['article_title']??''), sanitize_text_field($event['post_id']??''), esc_url_raw($event['article_url']??''), absint($event['user_id']??get_current_user_id())
        ));
    }

    private function range_counts($date) {
        global $wpdb;
        $start=$date.' 00:00:00'; $end=date('Y-m-d H:i:s', strtotime($date.' +1 day'));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT target_key, COUNT(*) c FROM {$this->table} WHERE published_at >= %s AND published_at < %s GROUP BY target_key",$start,$end), ARRAY_A);
        $out=[]; foreach($rows as $r) $out[(string)$r['target_key']]=(int)$r['c']; return $out;
    }

    private function today_events_count($date) {
        global $wpdb;
        $start=$date.' 00:00:00';
        $end=date('Y-m-d H:i:s', strtotime($date.' +1 day'));
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE published_at >= %s AND published_at < %s",$start,$end));
    }

    private function today_events($date,$limit=10,$offset=0) {
        global $wpdb;
        $start=$date.' 00:00:00';
        $end=date('Y-m-d H:i:s', strtotime($date.' +1 day'));
        $limit=max(1,absint($limit));
        $offset=max(0,absint($offset));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE published_at >= %s AND published_at < %s ORDER BY published_at DESC, id DESC LIMIT %d OFFSET %d",$start,$end,$limit,$offset), ARRAY_A);
    }

    public function page() {
        if (!current_user_can('edit_posts')) wp_die('Akses ditolak.');
        if (isset($_POST['jat_save']) && check_admin_referer('jat_save_settings')) {
            $s=$this->settings(); $s['default_target']=max(0,absint($_POST['default_target']??0));
            $targets=$this->get_targets(); $incoming=is_array($_POST['target']??null)?$_POST['target']:[]; $s['targets']=[];
            foreach($targets as $key=>$t) $s['targets'][$key]=max(0,absint($incoming[$key]??$s['default_target']));
            update_option('jat_settings',$s,false);
            echo '<div class="notice notice-success is-dismissible"><p>Target artikel harian berhasil disimpan.</p></div>';
        }
        $date=current_time('Y-m-d'); $targets=$this->get_targets(); $settings=$this->settings(); $counts=$this->range_counts($date);
        $event_total=$this->today_events_count($date);
        $per_page=10;
        $event_pages=max(1,(int)ceil($event_total/$per_page));
        $event_page=max(1,min($event_pages,absint($_GET['jat_page']??1)));
        $event_offset=($event_page-1)*$per_page;
        $events=$this->today_events($date,$per_page,$event_offset);
        $total_goal=0;$total_done=0;
        echo '<div class="wrap"><h1>Manajemen Artikel</h1><p>Target dihitung <strong>hanya setelah publish benar-benar berhasil</strong> ke WordPress atau Blogger.</p>';
        echo '<style id="jat-target-grid-css">.jat-target-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:12px!important;margin:18px 0!important}.jat-target-card{min-width:0!important;box-sizing:border-box!important;overflow:hidden!important}.jat-target-card strong{overflow-wrap:anywhere!important;word-break:break-word!important}@media(max-width:782px){.jat-target-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:6px!important}.jat-target-card{padding:9px!important;border-radius:9px!important}.jat-target-card strong{font-size:11px!important;line-height:1.2!important}.jat-target-card div{font-size:9px!important;line-height:1.2!important}.jat-target-card div:nth-of-type(2){font-size:15px!important;line-height:1.15!important;margin-top:4px!important}.jat-target-card div:nth-of-type(3){height:5px!important;margin:6px 0!important}}@media(max-width:420px){.jat-target-grid{gap:4px!important}.jat-target-card{padding:7px!important}.jat-target-card strong{font-size:10px!important}.jat-target-card div{font-size:8px!important}.jat-target-card div:nth-of-type(2){font-size:13px!important}.jat-target-card div:nth-of-type(3){height:4px!important;margin:5px 0!important}}</style>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:18px 0;">';
        echo '<div class="jat-target-grid">';
        foreach($targets as $key=>$t){$goal=$this->target_goal($key,$settings);$done=(int)($counts[$key]??0);$total_goal+=$goal;$total_done+=$done;$pct=$goal>0?min(100,round($done/$goal*100)):0;$doneMark=($goal>0&&$done>=$goal); echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px;"><strong>'.esc_html($t['name']).'</strong><div style="color:#646970;margin:5px 0 12px;">'.esc_html(ucfirst($t['platform'])).'</div><div style="font-size:24px;font-weight:700;">'.esc_html($done).' / '.esc_html($goal).'</div><div style="height:8px;background:#f0f0f1;border-radius:99px;overflow:hidden;margin:10px 0;"><div style="height:100%;width:'.$pct.'%;background:#2271b1;"></div></div><div>'.($doneMark?'🎉 <strong>Selesai hari ini</strong>':($goal>0?'Kurang '.max(0,$goal-$done).' artikel':'Target belum diatur')).'</div></div>';}
        echo '</div>';
        echo '</div>';
                if($total_goal>0 && $total_done>=$total_goal) echo '<div style="padding:16px 18px;border:1px solid #b7dfc0;background:#f0fbf3;border-radius:12px;margin:16px 0;font-size:16px;"><strong>🎉 Selamat!</strong> Semua target artikel Web Adsense hari ini sudah selesai ('.esc_html($total_done).' / '.esc_html($total_goal).').</div>';
        echo '<h2>Target Harian</h2><form method="post">'.wp_nonce_field('jat_save_settings').'<input type="hidden" name="jat_save" value="1">';
        // v1.3.192 — Target Harian dibuat responsive dengan horizontal scroll di layar sempit.
        // Struktur form dan field tetap sama; hanya pembungkus/tampilan tabel yang diperbaiki.
        echo '<div class="jat-table-scroll" style="width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #dcdcde;border-radius:8px;">';
        echo '<table class="widefat striped jat-target-table" style="min-width:760px;border:0;margin:0;"><thead><tr><th>Web Adsense</th><th>Platform</th><th>Target/hari</th><th>Hari ini</th></tr></thead><tbody>';
        echo '<tr><td><strong>Default</strong></td><td>Semua Web Adsense</td><td><input type="number" min="0" name="default_target" value="'.esc_attr($settings['default_target']).'" style="width:90px"></td><td>Dipakai jika target situs tidak diatur khusus</td></tr>';
        foreach($targets as $key=>$t){$goal=$this->target_goal($key,$settings);$done=(int)($counts[$key]??0); echo '<tr><td>'.esc_html($t['name']).'</td><td>'.esc_html(ucfirst($t['platform'])).'</td><td><input type="number" min="0" name="target['.esc_attr($key).']" value="'.esc_attr($goal).'" style="width:90px"></td><td>'.esc_html($done).' / '.esc_html($goal).'</td></tr>';}
        if(!$targets) echo '<tr><td colspan="4">Belum ada Website Adsense yang ditandai.</td></tr>';
        echo '</tbody></table></div><p><button class="button button-primary">Simpan Target Harian</button></p></form>';
        echo '<h2>Artikel Selesai Hari Ini</h2>';
        echo '<div style="overflow-x:auto;width:100%;max-width:100%;-webkit-overflow-scrolling:touch;border:1px solid #dcdcde;border-radius:8px;">';
        echo '<table class="widefat striped" style="min-width:760px;border:0;margin:0;"><thead><tr><th>Waktu</th><th>Web</th><th>Judul</th><th>Platform</th><th>Hasil</th></tr></thead><tbody>';
        if(!$events) echo '<tr><td colspan="5">Belum ada artikel yang berhasil dipublish hari ini.</td></tr>'; else foreach($events as $e){echo '<tr><td>'.esc_html(mysql2date('H:i', $e['published_at'], true)).'</td><td>'.esc_html($e['target_name']).'</td><td>'.esc_html($e['article_title']).'</td><td>'.esc_html(ucfirst($e['platform'])).'</td><td>'.(!empty($e['article_url'])?'<a href="'.esc_url($e['article_url']).'" target="_blank" rel="noopener">Lihat artikel</a>':'—').'</td></tr>';}
        echo '</tbody></table></div>';
        if($event_total > $per_page){
            // v1.3.191 — pagination dibuat satu baris dan angka dipadatkan agar tidak turun di mobile.
            echo '<div style="margin:14px 0 4px;">';
            echo '<div style="overflow-x:auto;max-width:100%;-webkit-overflow-scrolling:touch;scrollbar-width:thin;">';
            echo '<div class="tablenav bottom" style="margin:0;padding:0;float:none;min-width:max-content;white-space:nowrap;">';
            echo '<div class="tablenav-pages" style="margin:0;display:flex;align-items:center;gap:4px;flex-wrap:nowrap;white-space:nowrap;">';
            if($event_page>1){
                $url=add_query_arg(['page'=>'japur-article-tasks','jat_page'=>$event_page-1],admin_url('admin.php'));
                echo '<a class="button" style="margin:0;flex:0 0 auto;" href="'.esc_url($url).'">‹ Sebelumnya</a>';
            }

            $render_page=function($i) use ($event_page){
                $url=add_query_arg(['page'=>'japur-article-tasks','jat_page'=>$i],admin_url('admin.php'));
                if($i===$event_page){
                    return '<span class="tablenav-pages-navspan button disabled" style="margin:0;min-width:34px;text-align:center;padding-left:8px;padding-right:8px;" aria-current="page">'.esc_html($i).'</span>';
                }
                return '<a class="button" style="margin:0;min-width:34px;text-align:center;padding-left:8px;padding-right:8px;" href="'.esc_url($url).'">'.esc_html($i).'</a>';
            };

            if($event_pages<=5){
                for($i=1;$i<=$event_pages;$i++) echo $render_page($i);
            }else{
                // Pola ringkas: 1 2 3 ••• (N-2) (N-1) N.
                for($i=1;$i<=3;$i++) echo $render_page($i);
                echo '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;padding:0 2px;color:#646970;font-weight:600;">•••</span>';
                for($i=max(4,$event_pages-2);$i<=$event_pages;$i++) echo $render_page($i);
            }

            if($event_page<$event_pages){
                $url=add_query_arg(['page'=>'japur-article-tasks','jat_page'=>$event_page+1],admin_url('admin.php'));
                echo '<a class="button" style="margin:0;flex:0 0 auto;" href="'.esc_url($url).'">Berikutnya ›</a>';
            }
            echo '</div></div></div>';
            echo '<div style="color:#646970;margin-top:8px;white-space:nowrap;">Halaman '.esc_html($event_page).' dari '.esc_html($event_pages).' · '.esc_html($event_total).' artikel</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function ajax_refresh_dashboard() {
        if(!current_user_can('edit_posts')) wp_send_json_error(['message'=>'Akses ditolak.'],403);
        wp_send_json_success(['date'=>current_time('Y-m-d')]);
    }
}
