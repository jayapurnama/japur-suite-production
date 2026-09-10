<?php
/**
 * Plugin Name: JaPur Category Source Test
 * Description: Isolated test harness for the JaPur Category Source Engine. No API key required.
 * Version: 0.1.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/modules/source-category-engine.php';
add_action('admin_menu', function () {
    add_management_page('JaPur Category Source Test','JaPur Category Test','edit_posts','japur-category-source-test',function(){
        $nonce = wp_create_nonce('jaf_nonce');
        ?>
        <div class="wrap"><h1>JaPur Category Source Test</h1>
        <p>Uji REST API publik atau RSS/Atom tanpa API key. Plugin ini terpisah dan tidak mengubah JaPur Suite.</p>
        <p><input id="jcse-url" type="url" class="regular-text" placeholder="https://contoh.com"> <input id="jcse-category" type="text" class="regular-text" placeholder="Kategori, opsional"> <button class="button button-primary" id="jcse-run">Cari Artikel Terbaru</button></p>
        <div id="jcse-result"></div></div>
        <script>jQuery(function($){$('#jcse-run').on('click',function(){var b=$(this),r=$('#jcse-result');b.prop('disabled',true).text('Memproses...');$.post(ajaxurl,{action:'jcse_discover',nonce:'<?php echo esc_js($nonce); ?>',url:$('#jcse-url').val(),category:$('#jcse-category').val()},function(x){b.prop('disabled',false).text('Cari Artikel Terbaru');if(!x.success){r.html('<p style="color:#b32d2e">'+$('<div>').text(x.data&&x.data.message?x.data.message:'Gagal').html()+'</p>');return}var h='<p><strong>Metode: '+$('<div>').text(x.data.method||'-').html()+'</strong></p><ol>';$.each(x.data.items||[],function(_,i){h+='<li><strong>'+ $('<div>').text(i.title||'Tanpa judul').html()+'</strong><br><small>'+ $('<div>').text(i.url).html()+'</small></li>'});r.html(h+'</ol>')});});});</script>
        <?php
    });
});
