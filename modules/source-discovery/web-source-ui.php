<?php
/**
 * Module: JaPur Unified Web Source UI
 * Description: Additive Smart Discovery controls for the existing Web Sumber panel.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Web_Source_UI')) {
final class JaPur_Web_Source_UI {
    public static function init() {
        add_action('admin_footer', [__CLASS__, 'admin_footer'], 30);
    }

    public static function admin_footer() {
        if (!is_admin()) return;
        $page = sanitize_key($_GET['page'] ?? '');
        if (!in_array($page, ['jaf-extractor', 'jaf-extractor-workflow'], true)) return;
        if (!current_user_can('edit_posts')) return;
        $nonce = wp_create_nonce('japur_unified_discover_nonce');
        ?>
<style>
.jss-smart-bar{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:7px 0 4px}
.jss-smart-status{font-size:10px;color:#646970;line-height:1.35}
.jss-smart-method{font-size:10px;color:#2271b1;margin:4px 0}
.jss-smart-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px;align-items:center;padding:6px 7px;border:1px solid #c8d9e8;border-radius:7px;margin:4px 0;background:#f6fbff}
.jss-smart-item-title{font-weight:600;font-size:12px;line-height:1.25;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.jss-smart-item-url{font-size:10px;color:#646970;word-break:break-all;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-top:2px}
@media(max-width:700px){.jss-smart-item{grid-template-columns:minmax(0,1fr) auto;padding:5px 6px}.jss-smart-item-url{-webkit-line-clamp:1;font-size:9px}}
</style>
<script>
jQuery(function($){
    var card=$('#jaf-source-card'); if(!card.length)return;
    var web=$('#jaf-source-web'); if(!web.length)return;
    var panel=web.find('.jss-source-panel').first(); if(!panel.length)return;
    if($('#jss-smart-bar').length)return;
    var bar=$('<div id="jss-smart-bar" class="jss-smart-bar"><button type="button" class="button button-primary" id="jss-smart-discover">Discovery Cerdas</button><span class="jss-smart-status" id="jss-smart-status">Sitemap + Category/REST + RSS/Atom, dengan fallback Source Sync.</span></div>');
    panel.children('.jss-source-actions').after(bar);
    var resultBox=$('<div id="jss-smart-results"></div>');
    panel.children('#jss-sources').after(resultBox);
    var nonce=<?php echo wp_json_encode($nonce); ?>;
    function esc(t){return $('<div>').text(t||'').html()}
    function ajaxUrl(){return (window.JAF&&JAF.ajax)||ajaxurl}
    function show(items,method){
        items=Array.isArray(items)?items:[];
        var h=method?'<div class="jss-smart-method">Mesin: '+esc(method)+' • '+items.length+' kandidat</div>':'';
        $.each(items,function(_,x){
            var id='smart-'+Math.random().toString(36).slice(2);
            h+='<div class="jss-smart-item" data-id="'+id+'"><div><div class="jss-smart-item-title">'+esc(x.title||'Artikel sumber')+'</div><div class="jss-smart-item-url">'+esc(x.url)+(x.date?' • '+esc(x.date):'')+'</div></div><button type="button" class="button button-primary jss-smart-use" data-url="'+esc(x.url)+'">Gunakan</button></div>';
        });
        resultBox.html(h);
    }
    $('#jss-smart-discover').on('click',function(){
        var b=$(this),site=$('#jss-url').val().trim();
        if(!site){alert('Masukkan domain website terlebih dahulu.');return}
        b.prop('disabled',true).text('Mencari...');$('#jss-smart-status').text('Mencari kandidat dari mesin sumber...');resultBox.empty();
        $.post(ajaxUrl(),{action:'japur_unified_discover',nonce:nonce,site:site,sitemap:'sitemap.xml',limit:50},function(r){
            if(r&&r.success&&r.data&&Array.isArray(r.data.items)&&r.data.items.length){
                show(r.data.items,r.data.method||'Unified Discovery');
                $('#jss-smart-status').text('Selesai. Kandidat siap digunakan.');
                return;
            }
            $('#jss-smart-status').text('Unified tidak menemukan kandidat. Menjalankan Source Sync sebagai fallback...');
            var id=$('.jss-scan').first().data('id');
            if(id){
                $.post(ajaxUrl(),{action:'jss_scan_source',nonce:(window.JAF&&JAF.nonce)||'',id:id},function(){ $('#jss-smart-status').text('Fallback selesai. Daftar Source Sync diperbarui.'); $(document).trigger('jss-smart-fallback-done'); });
            } else {
                $('#jss-smart-status').text('Tidak ada sumber tersimpan untuk fallback.');
            }
        }).fail(function(){
            $('#jss-smart-status').text('Unified gagal diakses. Menjalankan Source Sync sebagai fallback...');
            var id=$('.jss-scan').first().data('id');
            if(id)$.post(ajaxUrl(),{action:'jss_scan_source',nonce:(window.JAF&&JAF.nonce)||'',id:id},function(){$('#jss-smart-status').text('Fallback selesai. Daftar Source Sync diperbarui.');$(document).trigger('jss-smart-fallback-done');});
        }).always(function(){b.prop('disabled',false).text('Discovery Cerdas');});
    });
    $(document).on('jss-smart-fallback-done',function(){var b=$('#jss-scan-all');if(b.length)b.trigger('click');});
    $(document).on('click','.jss-smart-use',function(){
        var b=$(this),url=b.data('url');b.prop('disabled',true).text('Mengambil...');
        $.post(ajaxUrl(),{action:'jaf_extract_url',url:url},function(r){
            if(!r.success){b.prop('disabled',false).text('Gunakan');alert(r.data&&r.data.message?r.data.message:'Gagal mengambil artikel.');return}
            var m=r.data.material||r.data.content||'';if($('#jaf-material').length)$('#jaf-material').val(m).trigger('input').trigger('change');
            b.closest('.jss-smart-item').remove();$('#jss-smart-status').text('Artikel berhasil diambil ke Materi Artikel.');$('html,body').animate({scrollTop:$('#jaf-material').offset().top-100},300);
        });
    });
});
</script>
<?php
    }
}
JaPur_Web_Source_UI::init();
}
