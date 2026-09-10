<?php
/**
 * Module: JaPur Unified Web Source Bridge
 * Description: Safe AJAX bridge for Unified Discovery without changing Source Sync.
 * Module Version: 0.2.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Web_Source_Bridge')) {
final class JaPur_Web_Source_Bridge {
    const ACTION = 'japur_unified_discover';
    const NONCE  = 'japur_unified_discover_nonce';

    public static function init() {
        add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'ajax_discover']);
        add_action('admin_footer', [__CLASS__, 'admin_footer'], 20);
    }

    private static function allowed_page() {
        if (!is_admin()) return false;
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return in_array($page, ['jaf-extractor','jaf-extractor-workflow'], true);
    }

    public static function ajax_discover() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Akses ditolak.'], 403);
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce tidak valid.'], 403);
        }

        $site = isset($_POST['site']) ? esc_url_raw(wp_unslash($_POST['site'])) : '';
        $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        $sitemap = isset($_POST['sitemap']) ? sanitize_text_field(wp_unslash($_POST['sitemap'])) : 'sitemap.xml';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;
        $limit = max(1, min(50, $limit));

        if (!$site || !wp_http_validate_url($site)) {
            wp_send_json_error(['message' => 'URL website sumber tidak valid.'], 400);
        }
        $host = wp_parse_url($site, PHP_URL_HOST);
        if (!$host) {
            wp_send_json_error(['message' => 'Domain website sumber tidak valid.'], 400);
        }

        $file = __DIR__ . '/class-unified-engine.php';
        if (!class_exists('JaPur_Unified_Discovery_Engine') && is_readable($file)) {
            require_once $file;
        }
        if (!class_exists('JaPur_Unified_Discovery_Engine')) {
            wp_send_json_error(['message' => 'Unified Discovery Engine tidak tersedia.'], 500);
        }

        $result = JaPur_Unified_Discovery_Engine::discover($site, $category, $sitemap, $limit);
        if (!is_array($result)) {
            wp_send_json_error(['message' => 'Discovery tidak menghasilkan respons yang valid.'], 500);
        }

        wp_send_json_success([
            'items' => array_values((array) ($result['items'] ?? [])),
            'methods' => array_values((array) ($result['methods'] ?? [])),
            'method' => sanitize_text_field($result['method'] ?? ''),
            'version' => sanitize_text_field($result['version'] ?? ''),
        ]);
    }

    public static function admin_footer() {
        if (!self::allowed_page()) return;
        $nonce = wp_create_nonce(self::NONCE);
        $ajax = admin_url('admin-ajax.php');
        ?>
        <style>
        .japur-unified-test{margin:8px 0 10px;padding:9px 10px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7}
        .japur-unified-test-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:7px}
        .japur-unified-test-title{font-size:12px;font-weight:700;color:#1d2327}.japur-unified-test-badge{font-size:10px;color:#646970}
        .japur-unified-test-controls{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.japur-unified-test-controls select{height:32px;min-width:130px}.japur-unified-test-controls .button{min-height:32px}
        .japur-unified-test-status{margin-top:6px;font-size:11px;color:#646970}.japur-unified-test-results{margin-top:7px}.japur-unified-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px;padding:6px 7px;margin:4px 0;border:1px solid #e2e4e7;border-radius:7px;background:#fff}
        .japur-unified-item-title{font-size:12px;font-weight:600;line-height:1.3}.japur-unified-item-url{font-size:10px;color:#646970;word-break:break-all;line-height:1.3;margin-top:2px}.japur-unified-use{white-space:nowrap!important;height:28px!important;min-height:28px!important;line-height:28px!important;padding:0 9px!important;font-size:11px!important}
        @media(max-width:700px){.japur-unified-test-controls{display:grid;grid-template-columns:1fr}.japur-unified-test-controls select,.japur-unified-test-controls .button{width:100%}}
        </style>
        <script>
        jQuery(function($){
            var box=$('#jss-source-sync'), panel=$('.jss-source-panel').first();
            if(!box.length || !panel.length || $('#japur-unified-test').length) return;
            var html='<div id="japur-unified-test" class="japur-unified-test">'
                +'<div class="japur-unified-test-head"><span class="japur-unified-test-title">Mesin Sumber Pintar</span><span class="japur-unified-test-badge">Sitemap + Category/REST + RSS/Atom</span></div>'
                +'<div class="japur-unified-test-controls"><select id="japur-unified-sitemap" aria-label="Sitemap"><option value="sitemap.xml">sitemap.xml</option><option value="wp-sitemap.xml">wp-sitemap.xml</option><option value="sitemap_index.xml">sitemap_index.xml</option><option value="sitemap-posts.xml">sitemap-posts.xml</option></select><button type="button" class="button button-primary" id="japur-unified-run">Cari dengan Semua Mesin</button></div>'
                +'<div id="japur-unified-status" class="japur-unified-test-status"></div><div id="japur-unified-results" class="japur-unified-test-results"></div></div>';
            panel.prepend(html);
            function esc(t){return $('<div>').text(t||'').html()}
            function run(){
                var site=$.trim($('#jss-url').val());
                if(!site){alert('Isi Domain Website terlebih dahulu.');$('#jss-url').focus();return}
                var b=$('#japur-unified-run'),status=$('#japur-unified-status'),results=$('#japur-unified-results');
                b.prop('disabled',true).text('Mencari...');status.text('Menjalankan mesin sumber...');results.empty();
                $.post(<?php echo wp_json_encode($ajax); ?>,{action:<?php echo wp_json_encode(self::ACTION); ?>,nonce:<?php echo wp_json_encode($nonce); ?>,site:site,sitemap:$('#japur-unified-sitemap').val(),limit:10},function(r){
                    if(!r.success){status.text((r.data&&r.data.message)?r.data.message:'Discovery gagal.');return}
                    var d=r.data||{},items=Array.isArray(d.items)?d.items:[];
                    status.text((d.method||'Tidak ada mesin yang menghasilkan hasil.')+(d.version?' • Engine '+d.version:''));
                    var h='';$.each(items,function(_,x){var url=x.url||'';h+='<div class="japur-unified-item"><div><div class="japur-unified-item-title">'+esc(x.title||'Artikel sumber')+'</div><div class="japur-unified-item-url">'+esc(url)+(x.date?' • '+esc(x.date):'')+'</div></div><button type="button" class="button button-primary japur-unified-use" data-url="'+esc(url)+'">Gunakan</button></div>'});
                    results.html(h||'<div class="jss-empty">Tidak ada artikel yang ditemukan.</div>');
                }).fail(function(){status.text('Koneksi discovery gagal. Silakan coba lagi.');}).always(function(){b.prop('disabled',false).text('Cari dengan Semua Mesin')});
            }
            $('#japur-unified-run').on('click',run);
            $(document).on('click','.japur-unified-use',function(){
                var b=$(this),url=b.data('url');if(!url)return;b.prop('disabled',true).text('Mengambil...');
                $.post((window.JAF&&JAF.ajax)||ajaxurl,{action:'jaf_extract_url',url:url,nonce:(window.JAF&&JAF.nonce)||''},function(r){
                    if(!r.success){b.prop('disabled',false).text('Gunakan');alert(r.data&&r.data.message?r.data.message:'Gagal mengambil artikel.');return}
                    var m=r.data.material||r.data.content||'';if($('#jaf-material').length)$('#jaf-material').val(m).trigger('input').trigger('change');
                    b.closest('.japur-unified-item').remove();$('html,body').animate({scrollTop:$('#jaf-material').offset().top-100},300);
                });
            });
        });
        </script>
        <?php
    }
}

add_action('init', ['JaPur_Web_Source_Bridge', 'init'], 20);
}
