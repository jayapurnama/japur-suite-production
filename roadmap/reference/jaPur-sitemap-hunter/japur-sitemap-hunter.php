<?php
/**
 * Plugin Name: JaPur Sitemap Hunter
 * Description: Discovery dan pembacaan sitemap untuk menemukan URL artikel/post. Mendukung sitemap.xml, wp-sitemap.xml, sitemap_index.xml, sitemap-posts.xml dan follow sitemap index.
 * Version: 2.0.1
 * Author: Japur Ganteng
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

final class JaPur_Sitemap_Hunter {
    const VER = '2.0.1';
    const NONCE = 'japur_sh_nonce';

    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_enqueue_scripts', [$this,'assets']);
        add_action('wp_ajax_japur_sh_test_domain', [$this,'ajax_test_domain']);
    }

    public function menu() {
        add_management_page('JaPur Sitemap Hunter','JaPur Sitemap Hunter','manage_options','japur-sitemap-hunter',[$this,'page']);
    }

    public function assets($hook) {
        if ($hook !== 'tools_page_japur-sitemap-hunter') return;
        wp_register_script('japur-sh', false, ['jquery'], self::VER, true);
        wp_enqueue_script('japur-sh');
        wp_add_inline_script('japur-sh', $this->js());
        wp_register_style('japur-sh', false, [], self::VER);
        wp_enqueue_style('japur-sh');
        wp_add_inline_style('japur-sh', $this->css());
    }

    public function page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap jsh">
            <h1>JaPur Sitemap Hunter <span>v<?php echo esc_html(self::VER); ?></span></h1>
            <p class="description">Mesin discovery-ready untuk menguji sitemap dan menemukan post. Tidak mengubah artikel WordPress.</p>

            <div class="jsh-grid">
                <section class="jsh-card">
                    <h2>Discovery</h2>
                    <label>Niche</label>
                    <select id="jsh-niche">
                        <option value="otomotif">Otomotif</option>
                        <option value="gadget">Gadget / Teknologi</option>
                        <option value="investasi">Investasi / Finance</option>
                    </select>

                    <label>Pola sitemap</label>
                    <select id="jsh-map">
                        <option value="sitemap.xml">/sitemap.xml</option>
                        <option value="wp-sitemap.xml">/wp-sitemap.xml</option>
                        <option value="sitemap_index.xml">/sitemap_index.xml</option>
                        <option value="sitemap-posts.xml">/sitemap-posts.xml</option>
                    </select>

                    <label>Jumlah kandidat</label>
                    <select id="jsh-limit">
                        <option>10</option><option selected>25</option><option>50</option>
                    </select>

                    <p class="jsh-help">Discovery otomatis dari mesin pencari membutuhkan API/connector pencarian. Versi ini menyediakan endpoint discovery-ready dan pengujian URL kandidat secara aman.</p>
                    <button class="button button-primary button-hero" id="jsh-run">Mulai Hunter</button>
                </section>

                <section class="jsh-card">
                    <h2>Quick Test</h2>
                    <p>Tempel domain saja. Sitemap akan ditambahkan otomatis.</p>
                    <textarea id="jsh-domains" rows="9" placeholder="contoh.com&#10;https://contoh2.com"></textarea>
                    <button class="button" id="jsh-test">Tes Kandidat</button>
                    <button class="button" id="jsh-clear">Bersihkan</button>
                </section>
            </div>

            <section class="jsh-card" id="jsh-status" style="display:none"></section>
            <section class="jsh-card" id="jsh-result" style="display:none">
                <div class="jsh-head">
                    <h2>Hasil Hunter</h2>
                    <div>
                        <button class="button" id="jsh-direct">Tampilkan Direct</button>
                        <button class="button" id="jsh-all">Tampilkan Semua</button>
                    </div>
                </div>
                <div id="jsh-summary"></div>
                <div class="jsh-table">
                    <table class="widefat striped">
                        <thead>
                            <tr><th>Situs</th><th>Sitemap</th><th>Tipe</th><th>URL</th><th>Post</th><th>Status</th></tr>
                        </thead>
                        <tbody id="jsh-body"></tbody>
                    </table>
                </div>
            </section>

            <section class="jsh-card">
                <h2>Mesin yang sudah disiapkan</h2>
                <div class="jsh-pills">
                    <b>URL normalizer</b><b>Safe HTTP</b><b>XML parser</b><b>Direct URLSET</b><b>Sitemap index follower</b><b>Post detector</b><b>Lastmod</b><b>Limit</b>
                </div>
                <p>Langkah integrasi berikutnya dapat menghubungkan discovery provider ke mesin ini tanpa mengubah reader.</p>
            </section>
        </div>
        <?php
    }

    private function normalize($v) {
        $v = trim($v);
        if (!$v) return '';
        if (!preg_match('~^https?://~i',$v)) $v='https://'.$v;
        return untrailingslashit(esc_url_raw($v));
    }

    private function public_url($url) {
        $p=wp_parse_url($url);
        if (!$p || empty($p['host']) || empty($p['scheme']) || !in_array(strtolower($p['scheme']),['http','https'],true)) return false;
        $host=strtolower($p['host']);
        if ($host==='localhost' || $host==='::1' || filter_var($host,FILTER_VALIDATE_IP)) {
            if ($host==='127.0.0.1'||$host==='::1'||preg_match('/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2\\d|3[0-1])\\.)/',$host)) return false;
        }
        return true;
    }

    private function fetch($url) {
        if (!$this->public_url($url)) return new WP_Error('bad_url','Alamat tidak valid/privat.');
        $r=wp_safe_remote_get($url,[
            'timeout'=>12,'redirection'=>3,'limit_response_size'=>5*1024*1024,
            'headers'=>['Accept'=>'application/xml,text/xml,text/plain;q=0.9,*/*;q=0.5'],
            'user-agent'=>'JaPur-Sitemap-Hunter/'.self::VER
        ]);
        if (is_wp_error($r)) return $r;
        $c=wp_remote_retrieve_response_code($r);
        if ($c<200||$c>=300) return new WP_Error('http','HTTP '.$c);
        return wp_remote_retrieve_body($r);
    }

    private function xml($body) {
        if (!function_exists('simplexml_load_string')) return new WP_Error('simplexml','PHP SimpleXML tidak tersedia.');
        libxml_use_internal_errors(true);
        $x=simplexml_load_string($body,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);
        if ($x===false) return new WP_Error('xml','XML tidak valid.');
        return $x;
    }

    private function parse($x) {
        $root=strtolower($x->getName());
        $out=['root'=>$root,'urls'=>[],'children'=>[]];
        if ($root==='urlset') {
            foreach($x->url as $u) {
                $loc=trim((string)$u->loc);
                if($loc && filter_var($loc,FILTER_VALIDATE_URL))
                    $out['urls'][]=['loc'=>esc_url_raw($loc),'lastmod'=>trim((string)$u->lastmod)];
            }
        } elseif ($root==='sitemapindex') {
            foreach($x->sitemap as $s) {
                $loc=trim((string)$s->loc);
                if($loc && filter_var($loc,FILTER_VALIDATE_URL))
                    $out['children'][]=['loc'=>esc_url_raw($loc),'lastmod'=>trim((string)$s->lastmod)];
            }
        }
        return $out;
    }

    private function post_like($url) {
        $path=strtolower((string)wp_parse_url($url,PHP_URL_PATH));
        if (!$path || $path==='/') return false;
        foreach(['/category/','/tag/','/author/','/page/','/feed/','/search/','/wp-content/','/wp-admin/'] as $bad)
            if(strpos($path,$bad)!==false) return false;
        $ext=pathinfo($path,PATHINFO_EXTENSION);
        if($ext && !in_array($ext,['html','htm','php'],true)) return false;
        return true;
    }

    private function inspect($site,$type,$follow=true) {
        $map=$site.'/'.$type;
        $body=$this->fetch($map);
        if(is_wp_error($body)) return ['site'=>$site,'sitemap'=>$map,'root'=>'-','total'=>0,'posts'=>0,'status'=>'Gagal: '.$body->get_error_message(),'children'=>[]];
        $x=$this->xml($body);
        if(is_wp_error($x)) return ['site'=>$site,'sitemap'=>$map,'root'=>'-','total'=>0,'posts'=>0,'status'=>'XML gagal: '.$x->get_error_message(),'children'=>[]];
        $p=$this->parse($x);
        if($p['root']==='urlset') {
            $posts=0; foreach($p['urls'] as $u) if($this->post_like($u['loc'])) $posts++;
            return ['site'=>$site,'sitemap'=>$map,'root'=>'urlset','total'=>count($p['urls']),'posts'=>$posts,'status'=>'DIRECT POST SITEMAP','children'=>[]];
        }
        if($p['root']==='sitemapindex') {
            return ['site'=>$site,'sitemap'=>$map,'root'=>'sitemapindex','total'=>count($p['children']),'posts'=>0,'status'=>$follow?'INDEX — child sitemap terdeteksi':'INDEX','children'=>$p['children']];
        }
        return ['site'=>$site,'sitemap'=>$map,'root'=>$p['root']?:'unknown','total'=>0,'posts'=>0,'status'=>'Format XML tidak dikenal','children'=>[]];
    }

    public function ajax_test_domain() {
        if(!current_user_can('manage_options')) wp_send_json_error(['message'=>'Tidak diizinkan.'],403);
        check_ajax_referer(self::NONCE,'nonce');
        $type=sanitize_text_field(wp_unslash($_POST['type']??'sitemap.xml'));
        $allowed=['sitemap.xml','wp-sitemap.xml','sitemap_index.xml','sitemap-posts.xml'];
        if(!in_array($type,$allowed,true)) wp_send_json_error(['message'=>'Tipe sitemap tidak valid.'],400);
        $raw=(string)wp_unslash($_POST['domains']??'');
        $lines=preg_split('/\R+/',$raw); $sites=[];
        foreach($lines as $line){$s=$this->normalize($line);if($s&&!in_array($s,$sites,true))$sites[]=$s;}
        $sites=array_slice($sites,0,50);
        if(!$sites) wp_send_json_error(['message'=>'Masukkan kandidat domain.'],400);
        $rows=[];
        foreach($sites as $site){
            $r=$this->inspect($site,$type,true);
            if($r['root']==='sitemapindex' && !empty($r['children'])){
                $children=$r['children']; $totalPosts=0;
                foreach(array_slice($children,0,20) as $child){
                    $b=$this->fetch($child['loc']); if(is_wp_error($b)) continue;
                    $x=$this->xml($b); if(is_wp_error($x)) continue;
                    $p=$this->parse($x);
                    if($p['root']==='urlset'){
                        $r['child_direct'] = ($r['child_direct']??0)+1;
                        foreach($p['urls'] as $u) if($this->post_like($u['loc'])) $totalPosts++;
                    }
                }
                $r['posts']=$totalPosts;
                $r['status']='INDEX — child urlset: '.intval($r['child_direct']??0).' | post terdeteksi: '.$totalPosts;
            }
            unset($r['children']);
            $rows[]=$r;
        }
        wp_send_json_success(['rows'=>$rows]);
    }

    private function js() {
        $ajax = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce(self::NONCE);
        $ajax_js = wp_json_encode($ajax);
        $nonce_js = wp_json_encode($nonce);
        return 'var AJAX_URL = ' . $ajax_js . '; var AJAX_NONCE = ' . $nonce_js . "\\n" . <<<'JAPUR_JS'
        ;
    }

    private function css() {
        return '.jsh{max-width:1250px}.jsh h1 span{font-size:12px;background:#eee;border-radius:12px;padding:3px 8px}.jsh-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.jsh-card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:20px;margin:18px 0;box-shadow:0 1px 2px rgba(0,0,0,.04)}.jsh-card h2{margin-top:0}.jsh label{display:block;font-weight:600;margin:13px 0 5px}.jsh select,.jsh textarea{width:100%;max-width:100%;box-sizing:border-box}.jsh textarea{font-family:monospace}.jsh-actions{margin-top:15px}.jsh-help{color:#646970}.jsh-table{overflow:auto}.jsh-table table{min-width:950px}.jsh-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.jsh-pills{display:flex;flex-wrap:wrap;gap:8px}.jsh-pills b{background:#f0f0f1;padding:7px 10px;border-radius:20px}.err{color:#b32d2e;font-weight:600}@media(max-width:900px){.jsh-grid{grid-template-columns:1fr}}';
    }
}
new JaPur_Sitemap_Hunter();
