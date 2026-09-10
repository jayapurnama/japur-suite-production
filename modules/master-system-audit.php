<?php
/**
 * Module: JaPur Master System Audit
 * Description: Audit terproteksi untuk membandingkan instalasi JaPur Suite dengan baseline resmi.
 * Module Version: 1.0.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Japur_Master_System_Audit')) {
class Japur_Master_System_Audit {
    const BASELINE_VERSION = '1.3.263';
    const OPTION_LAST_SCAN = 'japur_master_system_audit_last_scan';
    const OPTION_HISTORY = 'japur_master_system_audit_history';
    const AUDIT_FILE = 'modules/master-system-audit.php';

    private static function baseline_manifest() {
        return [
            'assets/japur-suite-admin-menu.js' => ['sha256' => '69c875b46dff246b5292023fc7a85cd36dd8d280f9fcbd14c1adfd6390c77852', 'size' => 937],
            'assets/javanese-auto-post-importer/editor.css' => ['sha256' => '8802d586bf5957a2c62b21ffb886bd6b8f8ac0b2ccd02bd6be1797ea7170d807', 'size' => 268],
            'assets/javanese-auto-post-importer/editor.js' => ['sha256' => '6ccb7053bfe4bb6a0b8e3ddae288ddf927bfe0bd45f05c9a11f90f4db5490c05', 'size' => 18979],
            'assets/rpi-pro/style.css' => ['sha256' => '89bedb605a38e827275554f0d0fd81ac4a9be5202a31a088aa7de53d90a2d0e4', 'size' => 2667],
            'japur-suite.php' => ['sha256' => '32fed1d87eae8919ae770ac8a98cbb0f228c826fb795ba5298daaf28d5682b83', 'size' => 84238],
            'modules/article-task-manager/includes/class-task-manager.php' => ['sha256' => 'd66126dea41c6fa5f90e05db24c23c3cd40e58c4f4157ba5d7dd0deecf62c8be', 'size' => 15714],
            'modules/article-task-manager/module.php' => ['sha256' => 'ecd4cbc19bc3993a1c62386b35afe4ce4eb5896c5a8d96e6e682f2d5db933bba', 'size' => 593],
            'modules/auto-index-pro.php' => ['sha256' => 'c9824b01e4b17d845174484897d01176d179acddea5c56cebbfc9af2cb9a7e47', 'size' => 2167],
            'modules/auto-index-pro/assets/css/admin.css' => ['sha256' => '4fed4e9208165067e11e5b377b52e337304b4ddc4227bd460e5d255e30f8b2bf', 'size' => 4468],
            'modules/auto-index-pro/assets/js/admin.js' => ['sha256' => '7fd9e42ac28f12ea2d02793ebbcf74f61f8e08575d00b0bf6b12a06e21a5727b', 'size' => 660],
            'modules/auto-index-pro/includes/class-jai-admin.php' => ['sha256' => '7ab63273361db25a831d066369aac102c47b32a54075d67465ab462c98f37f71', 'size' => 27353],
            'modules/auto-index-pro/includes/class-jai-db.php' => ['sha256' => '508bfac34ad7e7308fded925bbccd3c9787068ae72eee290f0387ce31332e85e', 'size' => 5545],
            'modules/auto-index-pro/includes/class-jai-gsc.php' => ['sha256' => 'd4845b4d1feb94117db0769c2dddd516afe4d47d0af9da1923b5371fe2505f59', 'size' => 11356],
            'modules/auto-index-pro/includes/class-jai-indexnow.php' => ['sha256' => 'c2b0be4177eac2c78598ec57145e3c1052c98fea19065e1407f302699d613868', 'size' => 2399],
            'modules/auto-index-pro/includes/class-jai-plugin.php' => ['sha256' => '4003b2dc8f139d486fc9428c52b19ae7f8c434bd9b1ef37db5dca393c3ff631a', 'size' => 7324],
            'modules/auto-index-pro/includes/class-jai-queue.php' => ['sha256' => 'ccd60313e88e8bcfdd90335dcd7d00d447af73cdc5e658b2aa5354fc9b9a20d2', 'size' => 1236],
            'modules/auto-index-pro/includes/class-jai-site.php' => ['sha256' => '44b17e7c61e84034e8bf1aec44dd9ee81d897a0a2dd369ee870ee0f124fffd97', 'size' => 851],
            'modules/auto-index-pro/includes/class-jai-sitemap.php' => ['sha256' => '2d3ceea751021a3e51714775e478e91bf9b7acf2ba4d60553630f753f0f4b79a', 'size' => 604],
            'modules/auto-update-post-date.php' => ['sha256' => 'a25a607a4b7e767cfe9508ba6670c93e9fb91ad6fe4932051c0c49227dbee855', 'size' => 14104],
            'modules/auto-webp-watermark.php' => ['sha256' => '1c4a07735af17d7aa6704b45a4b40576d8605b87802db15dc775d0e18aa12ff5', 'size' => 18857],
            'modules/japur-extractor-ai/assets/css/admin.css' => ['sha256' => '7261332456b757d4d5969cd616010ac44c4fd922f5188279a625a8a756431781', 'size' => 62550],
            'modules/japur-extractor-ai/assets/js/admin.js' => ['sha256' => '423327bb083b9c2c1519fe598336877ddfafa2ae2f8c65413d6b298e01d8026e', 'size' => 86285],
            'modules/japur-extractor-ai/includes/class-admin.php' => ['sha256' => '641a45ff6f713330987e36855ef526419d02089d916da4e213eea0577b4a8e51', 'size' => 200823],
            'modules/japur-extractor-ai/includes/class-extractor.php' => ['sha256' => 'e6de535dbcf30011570dffafe4969f928741de8b38201e143c86b5016ece37f5', 'size' => 15373],
            'modules/japur-extractor-ai/includes/class-openai.php' => ['sha256' => 'dab90db69063e59ccfbb4e3fadcf717c942fbd18557cada059a95b749988eaaa', 'size' => 3675],
            'modules/japur-extractor-ai/includes/class-prompt.php' => ['sha256' => '5c57066439a76ecbf67062c09a75d519fc739521559dbc1608c5e9ad82d9101b', 'size' => 14856],
            'modules/japur-extractor-ai/module.php' => ['sha256' => '8ddb29bc5dcb438f844a3b23821e910b426a0f6ff33f543caf30e36420c92a2e', 'size' => 2082],
            'modules/japur-extractor-ai/readme.txt' => ['sha256' => '5d87b550984a894beab30fbaee2821548a7318bd930ef2ca02aa4a4e9fcb5ee5', 'size' => 3705],
            'modules/javanese-auto-post-importer.php' => ['sha256' => 'fa324bb11b9de173620bea3b5f56ac31cd5a56a825116695e04a27643536815a', 'size' => 30333],
            'modules/lead-domain-auto-link.php' => ['sha256' => '5fdd33f364618f2a827bf1e05412d28bf2f0fe42ea2d718857e364125e2df5d2', 'size' => 25134],
            'modules/master-workflow-settings.php' => ['sha256' => 'e500e2bb16061ae1e095ac97e26e69beab6af2588d51a5ad99704a8004e7cac6', 'size' => 10389],
            'modules/openai-cost/module.php' => ['sha256' => '055c63a49d8afc0a714df64bd3f702ad92072ad3dcb8d14a399de4e797fc0d8f', 'size' => 43426],
            'modules/popup-promo.php' => ['sha256' => '6b26e92b48bf351429a05018cbe49811616fb5a24b0a3c9bc2a54b3df72a973d', 'size' => 4908],
            'modules/rpi-pro.php' => ['sha256' => '04b2b25abb6a1ba838e0d0d273040365bc1072a4916fa06dab1e07424e50d23c', 'size' => 16691],
            'readme.txt' => ['sha256' => '4570f6e91fe62e9a3197f2ee38a9f4c858a26c2f3063611282da5650b6246e81', 'size' => 3780],
        ];
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'menu'], 13);
        add_action('admin_init', [$this, 'handle_scan']);
    }

    public function menu() {
        add_submenu_page('japur-suite', 'Master System Audit', 'Master System Audit', 'manage_options', 'japur-master-system-audit', [$this, 'page']);
    }

    private function plugin_root() { return trailingslashit(JAPUR_SUITE_DIR); }

    private function current_manifest() {
        $root=$this->plugin_root(); $out=[];
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file){
            if(!$file->isFile()) continue;
            $path=$file->getPathname();
            $rel=ltrim(str_replace($root,'',$path),'/\\');
            if($rel===self::AUDIT_FILE) continue;
            $out[$rel]=['sha256'=>hash_file('sha256',$path),'size'=>(int)$file->getSize()];
        }
        ksort($out,SORT_STRING); return $out;
    }

    private function audit() {
        $base=self::baseline_manifest(); $current=$this->current_manifest();
        $changed=[]; $missing=[]; $unchanged=[]; $added=[];
        foreach($base as $path=>$meta){
            if(!isset($current[$path])) $missing[$path]=$meta;
            elseif(hash_equals($meta['sha256'],$current[$path]['sha256'])) $unchanged[$path]=$current[$path];
            else $changed[$path]=['baseline'=>$meta,'current'=>$current[$path]];
        }
        foreach($current as $path=>$meta) if(!isset($base[$path])) $added[$path]=$meta;
        $header=get_file_data(JAPUR_SUITE_FILE,['Version'=>'Version','Plugin Name'=>'Plugin Name'],'plugin');
        return [
            'timestamp'=>current_time('mysql'),'baseline_version'=>self::BASELINE_VERSION,
            'installed_version'=>isset($header['Version'])?$header['Version']:'',
            'constant_version'=>defined('JAPUR_SUITE_VERSION')?JAPUR_SUITE_VERSION:'',
            'counts'=>['baseline'=>count($base),'current'=>count($current),'unchanged'=>count($unchanged),'changed'=>count($changed),'missing'=>count($missing),'added'=>count($added)],
            'changed'=>$changed,'missing'=>$missing,'added'=>$added,
        ];
    }

    public function handle_scan() {
        if(!is_admin()||!current_user_can('manage_options')) return;
        if(empty($_POST['japur_master_system_audit_scan'])) return;
        check_admin_referer('japur_master_system_audit_scan');
        $result=$this->audit(); update_option(self::OPTION_LAST_SCAN,$result,false);
        $history=get_option(self::OPTION_HISTORY,[]); if(!is_array($history)) $history=[];
        array_unshift($history,['timestamp'=>$result['timestamp'],'counts'=>$result['counts'],'installed_version'=>$result['installed_version']]);
        update_option(self::OPTION_HISTORY,array_slice($history,0,10),false);
        add_settings_error('japur_master_system_audit','scanned','Audit selesai. Hasil terbaru sudah disimpan.','updated');
    }

    private function status($r) {
        if(!$r) return ['BELUM DIAUDIT','neutral']; $c=$r['counts'];
        if($c['missing']||$c['changed']) return ['PERUBAHAN TERDETEKSI','bad'];
        if($c['added']) return ['ADA FILE TAMBAHAN','warn'];
        if($r['installed_version']!==$r['constant_version']) return ['IDENTITAS VERSI TIDAK SINKRON','bad'];
        return ['CORE BASELINE UTUH','good'];
    }

    public function page() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        settings_errors('japur_master_system_audit'); $r=get_option(self::OPTION_LAST_SCAN,null); $history=get_option(self::OPTION_HISTORY,[]); if(!is_array($history)) $history=[];
        [$label,$tone]=$this->status($r); $c=$r?$r['counts']:['baseline'=>count(self::baseline_manifest()),'current'=>0,'unchanged'=>0,'changed'=>0,'missing'=>0,'added'=>0];
        echo '<div class="wrap msa-wrap"><style>'.$this->css().'</style>';
        echo '<div class="msa-hero"><div class="msa-eyebrow">JAPUR SUITE • INTEGRITY CONTROL</div><h1>Master System Audit</h1><p>Memeriksa file JaPur Suite terhadap baseline resmi. Audit ini mendeteksi file yang diubah, dihapus, atau ditambahkan tanpa mengubah mesin plugin.</p><div class="msa-meta"><span>Baseline terkunci: <strong>v'.esc_html(self::BASELINE_VERSION).'</strong></span><span>Instalasi: <strong>'.esc_html($r?$r['installed_version']:'—').'</strong></span><span>Audit: <strong>'.esc_html($r?$r['timestamp']:'Belum dijalankan').'</strong></span></div></div>';
        echo '<div class="msa-status '.esc_attr($tone).'"><strong>'.esc_html($label).'</strong><span>Baseline core tidak otomatis diubah oleh audit.</span></div>';
        echo '<div class="msa-grid">';
        $cards=[['Baseline',$c['baseline'],'File inti yang dilindungi'],['Tidak berubah',$c['unchanged'],'SHA-256 cocok dengan baseline'],['Diubah',$c['changed'],'Isi file berbeda'],['Dihapus',$c['missing'],'File baseline tidak ditemukan'],['Ditambahkan',$c['added'],'File baru di luar baseline'],['Terpasang',$c['current'],'Total file yang diaudit']];
        foreach($cards as $card) echo '<div class="msa-card"><div class="msa-num">'.esc_html($card[1]).'</div><strong>'.esc_html($card[0]).'</strong><small>'.esc_html($card[2]).'</small></div>';
        echo '</div>';
        echo '<div class="msa-actions"><form method="post">'.wp_nonce_field('japur_master_system_audit_scan','_wpnonce',true,false).'<input type="hidden" name="japur_master_system_audit_scan" value="1"><button class="button button-primary button-hero" type="submit">🔎 Jalankan Audit Sekarang</button></form><div><strong>Aturan audit:</strong> file baseline tidak boleh hilang atau berubah diam-diam. File tambahan akan ditampilkan agar bisa diperiksa.</div></div>';
        if($r){
            echo '<section class="msa-panel"><div class="msa-panel-head"><h2>Temuan Audit</h2><p>Perubahan ditampilkan berdasarkan hash SHA-256.</p></div><div class="msa-body">';
            if(!$r['counts']['changed']&&!$r['counts']['missing']&&!$r['counts']['added']) echo '<div class="msa-ok">✅ Tidak ada perubahan terhadap file baseline v'.esc_html(self::BASELINE_VERSION).'.</div>';
            else {
                echo '<table class="widefat striped msa-table"><thead><tr><th>Status</th><th>File</th><th>Keterangan</th></tr></thead><tbody>';
                foreach($r['changed'] as $path=>$meta) echo '<tr class="msa-bad"><td><strong>DIUBAH</strong></td><td><code>'.esc_html($path).'</code></td><td>SHA-256 berbeda dari baseline.</td></tr>';
                foreach($r['missing'] as $path=>$meta) echo '<tr class="msa-bad"><td><strong>DIHAPUS</strong></td><td><code>'.esc_html($path).'</code></td><td>File ada di baseline tetapi tidak ditemukan.</td></tr>';
                foreach($r['added'] as $path=>$meta) echo '<tr class="msa-warn"><td><strong>DITAMBAHKAN</strong></td><td><code>'.esc_html($path).'</code></td><td>File tidak ada pada baseline resmi. Periksa apakah ini memang fitur yang diizinkan.</td></tr>';
                echo '</tbody></table>';
            }
            echo '</div></section>';
        }
        echo '<section class="msa-panel"><div class="msa-panel-head"><h2>Riwayat Audit</h2><p>Menyimpan hingga 10 hasil audit terakhir.</p></div><div class="msa-body">';
        if(!$history) echo '<p class="description">Belum ada riwayat.</p>'; else { echo '<table class="widefat striped msa-table"><thead><tr><th>Waktu</th><th>Versi</th><th>Diubah</th><th>Dihapus</th><th>Ditambahkan</th><th>Tidak berubah</th></tr></thead><tbody>'; foreach($history as $h){$hc=$h['counts']; echo '<tr><td>'.esc_html($h['timestamp']).'</td><td><strong>'.esc_html($h['installed_version']).'</strong></td><td>'.esc_html($hc['changed']).'</td><td>'.esc_html($hc['missing']).'</td><td>'.esc_html($hc['added']).'</td><td>'.esc_html($hc['unchanged']).'</td></tr>';} echo '</tbody></table>'; }
        echo '</div></section>';
        echo '<div class="msa-note"><strong>🛡️ Prinsip Master System Audit</strong><br>Audit hanya membaca file dan menyimpan hasil pemeriksaan. Audit tidak menghapus, memperbaiki, atau mengganti file otomatis. Keputusan atas setiap perubahan tetap di tangan kakak.</div></div>';
    }

    private function css() {
        return '.msa-wrap{max-width:1180px;margin:24px 20px 40px 0}.msa-hero{background:#fff;border:1px solid #dcdcde;border-radius:16px;padding:24px 26px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.04)}.msa-eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em;color:#2271b1;margin-bottom:7px}.msa-hero h1{margin:0 0 7px;font-size:27px;color:#1d2327}.msa-hero p{margin:0;color:#646970;line-height:1.6;max-width:850px}.msa-meta{display:flex;gap:18px;flex-wrap:wrap;margin-top:15px;color:#646970;font-size:12px}.msa-meta strong{color:#1d2327}.msa-status{padding:16px 18px;border-radius:13px;border:1px solid;margin-bottom:16px}.msa-status strong{display:block;font-size:15px;margin-bottom:3px}.msa-status span{font-size:12px}.msa-status.good{background:#f0fbf3;border-color:#b7dfc0;color:#285c31}.msa-status.warn{background:#fff9e6;border-color:#ead79a;color:#6b5300}.msa-status.bad{background:#fff5f5;border-color:#f0b8b8;color:#8a2424}.msa-status.neutral{background:#f6f7f7;border-color:#dcdcde;color:#50575e}.msa-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:16px}.msa-card{background:#fff;border:1px solid #dcdcde;border-radius:13px;padding:14px;min-height:105px;box-sizing:border-box}.msa-num{font-size:26px;font-weight:800;color:#2271b1;line-height:1.1;margin-bottom:8px}.msa-card strong{display:block;color:#1d2327;font-size:12px}.msa-card small{display:block;color:#646970;font-size:10px;line-height:1.4;margin-top:4px}.msa-actions{display:flex;align-items:center;gap:18px;background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px 18px;margin-bottom:16px}.msa-actions .button-hero{min-height:42px;padding:8px 18px}.msa-actions>div{font-size:12px;color:#646970;line-height:1.5}.msa-panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;overflow:hidden;margin-bottom:16px}.msa-panel-head{padding:17px 19px;border-bottom:1px solid #e2e4e7;background:#fbfbfc}.msa-panel-head h2{margin:0 0 4px;font-size:16px}.msa-panel-head p{margin:0;color:#646970;font-size:12px}.msa-body{padding:16px 18px}.msa-ok{padding:14px 16px;border-radius:10px;background:#f0fbf3;border:1px solid #b7dfc0;color:#285c31;font-weight:600}.msa-table{border:1px solid #dcdcde}.msa-table th,.msa-table td{font-size:12px}.msa-table code{font-size:11px;word-break:break-all}.msa-bad td:first-child{color:#b32d2e}.msa-warn td:first-child{color:#996800}.msa-note{background:#f0f6fc;border:1px solid #c5d9ed;border-radius:13px;padding:15px 18px;color:#174a73;font-size:12px;line-height:1.55}@media(max-width:1050px){.msa-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:650px){.msa-wrap{margin-right:12px}.msa-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.msa-actions{align-items:stretch;flex-direction:column}.msa-actions .button-hero{width:100%}.msa-table{display:block;overflow:auto;white-space:nowrap}}';
    }
}
new Japur_Master_System_Audit();
}
