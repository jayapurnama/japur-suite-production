<?php
/**
 * Module: JaPur Source Sync
 * Description: Safe source website monitor for the Buat Artikel workflow.
 * Module Version: 1.2.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Japur_Source_Sync')) {
class Japur_Source_Sync {
    const OPT = 'japur_source_sync_settings';
    const QUEUE = 'japur_source_sync_queue';
    const CRON = 'japur_source_sync_tick';

    public static function init() {
        add_action('wp_ajax_jss_save_source', [__CLASS__, 'ajax_save_source']);
        add_action('wp_ajax_jss_delete_source', [__CLASS__, 'ajax_delete_source']);
        add_action('wp_ajax_jss_scan_source', [__CLASS__, 'ajax_scan_source']);
        add_action('wp_ajax_jss_get_queue', [__CLASS__, 'ajax_get_queue']);
        add_action('wp_ajax_jss_clear_queue', [__CLASS__, 'ajax_clear_queue']);
        add_action('wp_ajax_jss_consume_queue', [__CLASS__, 'ajax_consume_queue']);
        add_action('admin_footer', [__CLASS__, 'admin_footer']);
        add_action(self::CRON, [__CLASS__, 'cron_scan']);
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_action('admin_init', [__CLASS__, 'maybe_schedule']);
    }

    public static function cron_schedules($schedules) {
        if (!isset($schedules['jss_30min'])) $schedules['jss_30min'] = ['interval'=>1800,'display'=>'JaPur Source Sync 30 menit'];
        return $schedules;
    }

    private static function defaults() {
        return ['enabled'=>0,'interval'=>'daily','sources'=>[]];
    }

    private static function settings() {
        $s = get_option(self::OPT, []);
        return wp_parse_args(is_array($s)?$s:[], self::defaults());
    }

    private static function save($s) {
        update_option(self::OPT, $s, false);
    }

    private static function auth() {
        if (!current_user_can('edit_posts')) { wp_send_json_error(['message'=>'Akses ditolak.'],403); return false; }
        if (!check_ajax_referer('jaf_nonce','nonce',false)) { wp_send_json_error(['message'=>'Nonce tidak valid.'],403); return false; }
        return true;
    }

    private static function normalize_url($url) {
        $url=esc_url_raw(trim((string)$url));
        if (!$url || !wp_http_validate_url($url)) return '';
        return untrailingslashit($url);
    }

    private static function source_key($url) {
        return md5(strtolower(self::normalize_url($url)));
    }

    public static function ajax_save_source() {
        if (!self::auth()) return;
        $url=self::normalize_url(wp_unslash($_POST['url']??''));
        $name=sanitize_text_field(wp_unslash($_POST['name']??''));
        $enabled=!empty($_POST['enabled'])?1:0;
        if(!$url) wp_send_json_error(['message'=>'URL website sumber tidak valid.']);
        $s=self::settings(); $key=self::source_key($url);
        if($name==='') $name=parse_url($url,PHP_URL_HOST) ?: $url;
        $s['sources'][$key]=[
            'id'=>$key,'name'=>$name,'url'=>$url,'enabled'=>$enabled,
            'last_scan'=>(int)($s['sources'][$key]['last_scan']??0),
            'seen'=>(array)($s['sources'][$key]['seen']??[]),
        ];
        self::save($s); self::maybe_schedule();
        wp_send_json_success(['source'=>$s['sources'][$key]]);
    }

    public static function ajax_delete_source() {
        if (!self::auth()) return;
        $id=sanitize_key($_POST['id']??''); $s=self::settings();
        if($id!=='' && isset($s['sources'][$id])) unset($s['sources'][$id]);
        self::save($s); wp_send_json_success(['sources'=>array_values($s['sources'])]);
    }

    private static function fetch_xml($url) {
        $r=wp_safe_remote_get($url,['timeout'=>20,'redirection'=>4,'user-agent'=>'JaPurSourceSync/1.1']);
        if(is_wp_error($r)) return $r;
        $code=(int)wp_remote_retrieve_response_code($r);
        if($code<200 || $code>=300) return new WP_Error('http','Sitemap tidak dapat diakses (HTTP '.$code.').');
        $body=wp_remote_retrieve_body($r);
        if(!$body) return new WP_Error('empty','Sitemap kosong.');
        libxml_use_internal_errors(true);
        $xml=simplexml_load_string($body);
        libxml_clear_errors();
        if(!$xml) return new WP_Error('xml','Format sitemap tidak valid.');
        return $xml;
    }

    private static function sitemap_is_post($url) {
        $path=strtolower((string)wp_parse_url($url,PHP_URL_PATH));
        $base=strtolower(pathinfo($path,PATHINFO_BASENAME));
        if(preg_match('~(^|[-_])(page|category|categories|tag|tags|author|attachment|media|product|products|taxonomy|taxonomies|post-format|users?)([-_]|\.|$)~i',$base)) return false;
        if(preg_match('~(^|[-_])(post|posts)([-_]|\.|$)~i',$base)) return true;
        return false;
    }

    private static function collect_post_sitemaps($index_url, $depth=0, &$visited=[]) {
        if($depth>3) return [];
        $key=md5(strtolower($index_url));
        if(isset($visited[$key])) return [];
        $visited[$key]=1;
        $xml=self::fetch_xml($index_url);
        if(is_wp_error($xml)) return [];
        $posts=[];
        if(isset($xml->sitemap)) {
            foreach($xml->sitemap as $sm) {
                $loc=esc_url_raw(trim((string)$sm->loc));
                if(!$loc) continue;
                $index_host=strtolower((string)wp_parse_url($index_url,PHP_URL_HOST));
                $child_host=strtolower((string)wp_parse_url($loc,PHP_URL_HOST));
                if(!$child_host || $child_host!==$index_host) continue;
                if(self::sitemap_is_post($loc)) {
                    $lastmod=trim((string)($sm->lastmod ?? ''));
                    $posts[]=['url'=>$loc,'lastmod'=>$lastmod];
                } else {
                    $child_path=strtolower((string)wp_parse_url($loc,PHP_URL_PATH));
                    if(preg_match('~(sitemap[_-]?index|wp-sitemap)~i',$child_path)) {
                        $posts=array_merge($posts,self::collect_post_sitemaps($loc,$depth+1,$visited));
                    }
                }
            }
            return $posts;
        }
        return [];
    }

    private static function read_post_sitemap($url) {
        $xml=self::fetch_xml($url);
        if(is_wp_error($xml)) return [];
        $items=[];
        if(isset($xml->url)) {
            foreach($xml->url as $u) {
                $loc=esc_url_raw(trim((string)$u->loc));
                if(!$loc || !preg_match('~^https?://~i',$loc)) continue;
                $lastmod=trim((string)($u->lastmod ?? ''));
                $items[$loc]=['url'=>$loc,'title'=>'','date'=>$lastmod];
            }
        }
        return array_values($items);
    }

    private static function discover($source) {
        $raw=self::normalize_url($source['url']);
        $parts=wp_parse_url($raw);
        $host=isset($parts['host'])?(string)$parts['host']:'';
        $scheme=isset($parts['scheme'])?(string)$parts['scheme']:'https';
        if(!$host) return new WP_Error('source','Domain sumber tidak valid.');
        $base=$scheme.'://'.$host.'/';
        $candidates=[$base.'wp-sitemap.xml',$base.'sitemap_index.xml',$base.'sitemap-index.xml',$base.'sitemap.xml'];
        $post_sitemaps=[]; $visited=[];
        foreach($candidates as $sm) {
            $xml=self::fetch_xml($sm);
            if(is_wp_error($xml)) continue;
            if(isset($xml->sitemap)) {
                foreach($xml->sitemap as $child) {
                    $loc=esc_url_raw(trim((string)$child->loc));
                    if(!$loc) continue;
                    $path=strtolower((string)wp_parse_url($loc,PHP_URL_PATH));
                    if(self::sitemap_is_post($loc)) {
                        $post_sitemaps[]=['url'=>$loc,'lastmod'=>trim((string)($child->lastmod ?? ''))];
                    } elseif(preg_match('~(sitemap[_-]?index|wp-sitemap)~i',$path)) {
                        $post_sitemaps=array_merge($post_sitemaps,self::collect_post_sitemaps($loc,1,$visited));
                    }
                }
            } elseif(self::sitemap_is_post($sm)) {
                $post_sitemaps[]=['url'=>$sm,'lastmod'=>''];
            }
            if($post_sitemaps) break;
        }
        $unique=[]; foreach($post_sitemaps as $sm) $unique[$sm['url']]=$sm; $post_sitemaps=array_values($unique);
        if(!$post_sitemaps) return new WP_Error('post_sitemap','Post sitemap tidak ditemukan. Pastikan website memiliki sitemap artikel/post yang dapat diakses publik.');
        $urls=[];
        foreach($post_sitemaps as $sm) foreach(self::read_post_sitemap($sm['url']) as $item) {
            $item_host=parse_url($item['url'],PHP_URL_HOST);
            if($item_host && strtolower(preg_replace('/^www\./i','',$item_host))===strtolower(preg_replace('/^www\./i','',$host))) $urls[$item['url']]=$item;
        }
        $urls=array_values($urls);
        usort($urls,function($a,$b){$ta=strtotime((string)($a['date']??''))?:0;$tb=strtotime((string)($b['date']??''))?:0;return $tb<=>$ta;});
        return array_slice($urls,0,500);
    }

    private static function scan($id) {
        $s=self::settings(); if(empty($s['sources'][$id])) return new WP_Error('source','Sumber tidak ditemukan.');
        $src=$s['sources'][$id]; $found=self::discover($src); if(is_wp_error($found)) return $found;
        $seen=(array)($src['seen']??[]); $queue=get_option(self::QUEUE,[]); if(!is_array($queue))$queue=[]; $new=[];
        foreach($found as $item){$url=self::normalize_url($item['url']);if(!$url)continue;$key=md5(strtolower($url));if(isset($seen[$key]))continue;$seen[$key]=time();$entry=['id'=>$key,'source_id'=>$id,'source_name'=>$src['name'],'url'=>$url,'title'=>sanitize_text_field($item['title']??''),'date'=>sanitize_text_field($item['date']??''),'status'=>'new','created'=>time(),'material'=>''];$queue[$key]=$entry;$new[]=$entry;}
        uasort($queue,function($a,$b){$ad=strtotime((string)($a['date']??''));$bd=strtotime((string)($b['date']??''));if($ad!==$bd)return $bd<=>$ad;return(int)($b['created']??0)<=>(int)($a['created']??0);});
        $queue=array_slice($queue,0,200,true); usort($new,function($a,$b){return strtotime((string)($b['date']??''))<=>strtotime((string)($a['date']??''));});
        $src['seen']=$seen;$src['last_scan']=time();$s['sources'][$id]=$src;self::save($s);update_option(self::QUEUE,$queue,false);
        return['found'=>count($found),'new'=>$new,'queue'=>array_values($queue)];
    }

    public static function ajax_scan_source(){if(!self::auth())return;$id=sanitize_key($_POST['id']??'');$r=self::scan($id);if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()]);wp_send_json_success($r);}
    public static function ajax_get_queue(){if(!self::auth())return;$q=get_option(self::QUEUE,[]);if(!is_array($q))$q=[];wp_send_json_success(['queue'=>array_values($q),'sources'=>array_values(self::settings()['sources'])]);}
    public static function ajax_clear_queue(){if(!self::auth())return;update_option(self::QUEUE,[],false);wp_send_json_success();}
    public static function ajax_consume_queue(){if(!self::auth())return;$id=sanitize_key($_POST['id']??'');if($id==='')wp_send_json_error(['message'=>'Artikel sumber tidak valid.']);$q=get_option(self::QUEUE,[]);if(!is_array($q))$q=[];if(isset($q[$id]))unset($q[$id]);update_option(self::QUEUE,$q,false);wp_send_json_success(['queue'=>array_values($q)]);}
    public static function cron_scan(){ $s=self::settings();if(empty($s['enabled']))return;foreach((array)$s['sources'] as $id=>$src)if(!empty($src['enabled']))self::scan($id); }
    public static function maybe_schedule(){ $s=self::settings();if(!empty($s['enabled'])){if(!wp_next_scheduled(self::CRON))wp_schedule_event(time()+300,$s['interval']==='30min'?'jss_30min':'daily',self::CRON);}else wp_clear_scheduled_hook(self::CRON); }

    public static function admin_footer(){
        if(!is_admin())return;$page=sanitize_key($_GET['page']??'');if(!in_array($page,['jaf-extractor','jaf-extractor-workflow'],true))return;$q=get_option(self::QUEUE,[]);if(!is_array($q))$q=[];?>
<style>#jss-source-sync{display:none}.jss-source-panel{margin-top:14px}.jss-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.jss-source-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:0 0 10px}.jss-filter{min-width:120px}.jss-source-actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.jss-source-list{border-top:1px solid #eee;margin-top:10px}.jss-source{display:flex;align-items:center;gap:8px;padding:9px 0;border-bottom:1px solid #eee}.jss-source small{color:#646970;word-break:break-all}.jss-source button{margin-left:auto}.jss-qhead{display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding:12px 0;border-top:1px solid #eee}.jss-item{display:grid;grid-template-columns:1fr auto;gap:8px;padding:10px;border:1px solid #eee;border-radius:9px;margin:7px 0}.jss-item-title{font-weight:600}.jss-item-url{font-size:12px;color:#646970;word-break:break-all}.jss-use{white-space:nowrap}.jss-empty{color:#646970;padding:10px 0}.jss-count{font-size:12px;color:#646970}.jss-source-note{margin-top:8px;color:#646970;font-size:12px}@media(max-width:700px){.jss-grid{grid-template-columns:1fr}.jss-item{grid-template-columns:1fr}.jss-use{width:100%}.jss-source-toolbar{align-items:flex-start;flex-direction:column}}</style>
<div id="jss-source-sync"><div class="jss-source-panel"><div class="jss-grid"><div><label>Nama Website</label><input id="jss-name" type="text" placeholder="Contoh: Sumber Berita"></div><div><label>Domain Website</label><input id="jss-url" type="url" placeholder="https://contoh.com"></div></div><div class="jss-source-actions"><button type="button" class="button button-primary" id="jss-add">Tambah Sumber</button><button type="button" class="button" id="jss-scan-all">Cek Artikel Terbaru</button></div><div id="jss-sources" class="jss-source-list"></div><div class="jss-qhead"><strong>Artikel Terbaru Terdeteksi</strong><span><span id="jss-count">0</span> artikel</span></div><div class="jss-source-toolbar"><span class="jss-count" id="jss-range-label">Menampilkan 10 terbaru</span><select id="jss-limit" class="jss-filter"><option value="10">10 terbaru</option><option value="25">25 terbaru</option><option value="50">50 terbaru</option><option value="all">Semua</option></select></div><div id="jss-list"></div><div class="jss-source-note">Hanya URL dari sitemap post yang diproses. Setelah Extract berhasil, artikel otomatis dikeluarkan dari daftar tetapi tetap ditandai sebagai sudah digunakan.</div></div></div>
<script>jQuery(function($){var box=$('#jss-source-sync');if(!box.length)return;var sourceCard=$('#jaf-source-card');if(!sourceCard.length)return;var tabs=sourceCard.find('.jwp-source-tabs').first();var urlPanel=$('#jaf-source-url');if(!tabs.length||!urlPanel.length)return;var tab=$('<button type="button" class="button jwp-source-tab" data-source="web">Web Sumber</button>');tabs.append(tab);var panel=$('<div id="jaf-source-web" class="jwp-source-panel" style="display:none"></div>');panel.append(box.children('.jss-source-panel'));urlPanel.after(panel);box.remove();var allQueue=<?php echo wp_json_encode(array_values($q));?>;function req(action,data,done){data=data||{};data.action=action;data.nonce=(window.JAF&&JAF.nonce)||'';$.post((window.JAF&&JAF.ajax)||ajaxurl,data,done)}function esc(t){return $('<div>').text(t||'').html()}function setTab(mode){sourceCard.find('.jwp-source-tab').removeClass('active');sourceCard.find('.jwp-source-panel').hide();sourceCard.find('.jwp-source-tab[data-source="'+mode+'"]').addClass('active');if(mode==='web')$('#jaf-source-web').show();else $('#jaf-source-'+mode).show()}tabs.on('click','.jwp-source-tab',function(){setTab($(this).data('source'))});function renderSources(items){var h='';$.each(items,function(_,s){h+='<div class="jss-source"><strong>'+esc(s.name)+'</strong><small>'+esc(s.url)+'</small><button class="button jss-scan" data-id="'+esc(s.id)+'">Cek</button><button class="button-link-delete jss-del" data-id="'+esc(s.id)+'">Hapus</button></div>'});$('#jss-sources').html(h||'<div class="jss-empty">Belum ada website sumber.</div>')}function renderQueue(items){allQueue=Array.isArray(items)?items:[];$('#jss-count').text(allQueue.length);var limit=$('#jss-limit').val()||'10',shown=limit==='all'?allQueue:allQueue.slice(0,Number(limit));$('#jss-range-label').text('Menampilkan '+shown.length+' '+(limit==='all'?'artikel':'terbaru'));var h='';$.each(shown,function(_,x){h+='<div class="jss-item" data-id="'+esc(x.id)+'"><div><div class="jss-item-title">'+esc(x.title||'Artikel sumber')+'</div><div class="jss-item-url">'+esc(x.url)+(x.date?' • '+esc(x.date):'')+'</div></div><button type="button" class="button button-primary jss-use" data-id="'+esc(x.id)+'" data-url="'+esc(x.url)+'">Gunakan</button></div>'});$('#jss-list').html(h||'<div class="jss-empty">Belum ada artikel baru.</div>')}function load(){req('jss_get_queue',{},function(r){if(!r.success)return;renderSources(r.data.sources||[]);renderQueue(r.data.queue||[])})}$('#jss-limit').on('change',function(){renderQueue(allQueue)});$('#jss-add').on('click',function(){var b=$(this);b.prop('disabled',true);req('jss_save_source',{name:$('#jss-name').val(),url:$('#jss-url').val(),enabled:1},function(r){b.prop('disabled',false);if(!r.success){alert(r.data.message);return}$('#jss-name,#jss-url').val('');load()})});$('#jss-scan-all').on('click',function(){var b=$(this);b.prop('disabled',true);req('jss_get_queue',{},function(r){var sources=(r.success&&r.data.sources)||[],i=0;function next(){if(i>=sources.length){b.prop('disabled',false);load();return}var id=sources[i++].id;req('jss_scan_source',{id:id},function(){next()})}next()})});$(document).on('click','.jss-scan',function(){var id=$(this).data('id'),b=$(this);b.prop('disabled',true);req('jss_scan_source',{id:id},function(r){b.prop('disabled',false);if(!r.success)alert(r.data.message);load()})});$(document).on('click','.jss-del',function(){if(!confirm('Hapus website sumber ini?'))return;req('jss_delete_source',{id:$(this).data('id')},function(){load()})});$(document).on('click','.jss-use',function(){var id=$(this).data('id'),url=$(this).data('url'),b=$(this);b.prop('disabled',true).text('Mengambil...');req('jaf_extract_url',{url:url},function(r){if(!r.success){b.prop('disabled',false).text('Gunakan');alert(r.data&&r.data.message?r.data.message:'Gagal mengambil artikel.');return}var m=r.data.material||r.data.content||'';if($('#jaf-material').length)$('#jaf-material').val(m).trigger('input').trigger('change');req('jss_consume_queue',{id:id},function(){load()});$('html,body').animate({scrollTop:$('#jaf-material').offset().top-100},300)})});load()});</script>
<?php }
}
Japur_Source_Sync::init();
}
