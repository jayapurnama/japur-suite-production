<?php
if (!defined('ABSPATH')) exit;
class JAI_Pro_Plugin {
    private static $instance;
    public static function instance(){ return self::$instance ?: (self::$instance=new self); }
    private function __construct(){
        // Admin tetap dimuat agar menu dan pengaturan Japur Auto Index PRO
        // tetap dapat dibuka walaupun modul Suite sedang OFF.
        new JAI_Pro_Admin();

        // Hook operasional hanya aktif ketika modul Suite ON.
        if (class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return;

        add_filter('cron_schedules', function($s){$s['jai_pro_5min']=['interval'=>300,'display'=>'Japur Auto Index - 5 Menit'];return $s;});
        add_action('init', [$this,'key_endpoint']);
        add_action('template_redirect', [$this,'key_fallback']);
        add_action('update_option_jai_pro_indexnow_key', [$this,'key_changed'], 10, 3);
        add_action('transition_post_status', [$this,'post_published'],10,3);
        add_action('post_updated', [$this,'post_updated'],10,3);
        add_action('jai_pro_gsc_auto_submit_sitemap', [$this,'gsc_auto_submit_sitemap']);
        add_action('jai_pro_gsc_auto_inspect_url', [$this,'gsc_auto_inspect_url'], 10, 3);
        JAI_Pro_Queue::init(); JAI_Pro_Queue::schedule();
    }
    public static function activate(){ JAI_Pro_DB::install(); if(!get_option('jai_pro_indexnow_key')) update_option('jai_pro_indexnow_key','0ac8ea0a346b48afaa9c2afc5e59fc89',false); JAI_Pro_IndexNow::sync_key_file(); add_option('jai_pro_enabled',1); add_option('jai_pro_post_types',['post']); add_option('jai_pro_batch',5); add_option('jai_pro_max_attempts',5); add_option('jai_pro_gsc_auto_inspect',1); JAI_Pro_Queue::schedule(); flush_rewrite_rules(); }
    public static function deactivate(){ JAI_Pro_Queue::unschedule(); flush_rewrite_rules(); }
    public function key_endpoint(){ add_rewrite_tag('%jai_pro_key%','([0-9]+)'); }
    public function key_fallback(){
        $path = wp_parse_url(home_url('/japurai-indexnow-key.txt'), PHP_URL_PATH);
        $request = wp_parse_url(home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? '/')), PHP_URL_PATH);
        if ($request !== $path) return;
        if (is_file(trailingslashit(ABSPATH).'japurai-indexnow-key.txt') && is_readable(trailingslashit(ABSPATH).'japurai-indexnow-key.txt')) return;
        nocache_headers(); header('Content-Type: text/plain; charset=utf-8'); header('X-Robots-Tag: noindex'); echo esc_html(JAI_Pro_IndexNow::key()); exit;
    }
    public function key_changed($old_value,$value,$option){ JAI_Pro_IndexNow::sync_key_file(); }
    public function post_published($new,$old,$post){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return; if($new!=='publish'||$old==='publish') return; $types=(array)get_option('jai_pro_post_types',['post']); if(!in_array($post->post_type,$types,true)||wp_is_post_revision($post->ID)) return; $url=get_permalink($post); if($url) { JAI_Pro_DB::enqueue($url,$post->ID); } if(get_option('jai_pro_gsc_auto_submit',1)) $this->schedule_gsc_auto_submit(); if(get_option('jai_pro_gsc_auto_inspect',1)) $this->schedule_gsc_auto_inspect($url,$post->ID); }
    public function post_updated($post_id,$post_after,$post_before){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return; if(wp_is_post_revision($post_id)||$post_after->post_status!=='publish'||$post_before->post_status!=='publish') return; $types=(array)get_option('jai_pro_post_types',['post']); if(!in_array($post_after->post_type,$types,true)) return; $url=get_permalink($post_id); if($url) JAI_Pro_DB::enqueue($url,$post_id); if(get_option('jai_pro_gsc_auto_submit',1)) $this->schedule_gsc_auto_submit(); if($url && get_option('jai_pro_gsc_auto_inspect',1)) $this->schedule_gsc_auto_inspect($url,$post_id); }
    private function schedule_gsc_auto_submit(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return; if(!JAI_Pro_GSC::connected() || !JAI_Pro_GSC::property()) return; if(!JAI_Pro_GSC::sitemap()) return; if(!wp_next_scheduled('jai_pro_gsc_auto_submit_sitemap')) wp_schedule_single_event(time()+600,'jai_pro_gsc_auto_submit_sitemap'); }
    private function schedule_gsc_auto_inspect($url,$post_id=0){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return; if(!JAI_Pro_GSC::connected() || !JAI_Pro_GSC::property() || !$url) return; if(wp_next_scheduled('jai_pro_gsc_auto_inspect_url',[$url,(int)$post_id,0])) return; wp_schedule_single_event(time()+660,'jai_pro_gsc_auto_inspect_url',[$url,(int)$post_id,0]); }
    public function gsc_auto_inspect_url($url,$post_id=0,$attempt=0){
        if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return;
        if(!get_option('jai_pro_gsc_auto_inspect',1) || !JAI_Pro_GSC::connected()) return;
        $site=JAI_Pro_GSC::property(); if(!$site || !$url) return;
        $attempt=(int)$attempt;
        $r=JAI_Pro_GSC::inspect($site,$url);
        if(is_wp_error($r)){
            $ed=$r->get_error_data(); $code=(is_array($ed)&&isset($ed['status']))?(int)$ed['status']:0;
            JAI_Pro_DB::update_latest_gsc_inspection_log($url,(int)$post_id,'failed',$code,'Auto URL Inspection gagal: '.$r->get_error_message());
            return;
        }
        $data=$r['data']['inspectionResult']??[]; $index=$data['indexStatusResult']??[];
        $verdict=$index['verdict']??'UNKNOWN'; $coverage=$index['coverageState']??'-'; $robots=$index['robotsTxtState']??'-';
        $crawl=$index['lastCrawlTime']??'-'; $canonical=$index['googleCanonical']??'-';
        $is_pass = ($verdict==='PASS');
        $is_neutral = (!$is_pass && $verdict==='NEUTRAL');
        $status = $is_pass ? 'success' : ($is_neutral ? 'neutral' : 'failed');
        $message="Auto URL Inspection: Verdict: $verdict | Coverage: $coverage | Robots.txt: $robots | Last Crawl: $crawl | Google Canonical: $canonical";
        JAI_Pro_DB::update_latest_gsc_inspection_log($url,(int)$post_id,$status,200,$message);
        if($is_neutral && $attempt < 3 && get_option('jai_pro_gsc_auto_inspect',1)){
            $delays=[1=>HOUR_IN_SECONDS,2=>6*HOUR_IN_SECONDS,3=>24*HOUR_IN_SECONDS];
            $next_attempt=$attempt+1; $delay=$delays[$next_attempt]??0;
            if($delay && !wp_next_scheduled('jai_pro_gsc_auto_inspect_url',[$url,(int)$post_id,$next_attempt])){
                wp_schedule_single_event(time()+$delay,'jai_pro_gsc_auto_inspect_url',[$url,(int)$post_id,$next_attempt]);
            }
        }
    }
    public function gsc_auto_submit_sitemap(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return; if(!get_option('jai_pro_gsc_auto_submit',1) || !JAI_Pro_GSC::connected()) return; $site=JAI_Pro_GSC::property(); $sitemap=JAI_Pro_GSC::sitemap(); if(!$site || !$sitemap) return; $r=JAI_Pro_GSC::submit_sitemap($site,$sitemap); $ok=!is_wp_error($r); $http_code=0; $message='Sitemap berhasil dikirim ke Google Search Console secara otomatis.'; if(!$ok){ $ed=$r->get_error_data(); if(is_array($ed) && isset($ed['status'])) $http_code=(int)$ed['status']; $message='Auto submit sitemap GSC gagal: '.$r->get_error_message(); } JAI_Pro_DB::log($sitemap,0,'Google Search Console',$ok?'success':'failed',$ok?200:$http_code,$message); }
}
