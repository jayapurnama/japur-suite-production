<?php
if (!defined('ABSPATH')) exit;

class JAF_Admin {
    private $o;

    function __construct() {
        $this->o = $this->get_secure_options();
        $this->migrate_local_adsense_flag();
        $this->migrate_blogger_profiles();
        $this->o = $this->get_secure_options();
        add_action('admin_menu', [$this,'menu'], 15);
        add_action('admin_init', [$this,'settings']);
        add_action('admin_enqueue_scripts', [$this,'assets']);
        add_action('wp_ajax_jaf_extract_url', [$this,'extract_url']);
        add_action('wp_ajax_jaf_extract_material', [$this,'extract_material']);
        add_action('wp_ajax_jaf_generate_article', [$this,'generate_article']);
        add_action('wp_ajax_jaf_generate_thumbnail', [$this,'generate_thumbnail']);
        add_action('wp_ajax_jaf_apply_publish', [$this,'apply_publish']);
        add_action('wp_ajax_jaf_test_api', [$this,'test_api']);
        add_action('wp_ajax_jaf_test_wp_profile', [$this,'test_wp_profile']);
        add_action('wp_ajax_jaf_preflight_check', [$this,'preflight_check']);
        add_action('wp_ajax_jaf_start_background', [$this,'start_background']);
        add_action('wp_ajax_jaf_start_background_publish', [$this,'start_background_publish']);
        add_action('wp_ajax_jaf_background_status', [$this,'background_status']);
        add_action('wp_ajax_jaf_cancel_background', [$this,'cancel_background']);
        add_action('wp_ajax_jaf_retry_multi_item', [$this,'retry_multi_item']);
        add_action('wp_ajax_nopriv_jaf_background_execute', [$this,'background_execute']);
        add_action('jaf_background_worker', [$this,'background_worker']);
        add_action('wp_ajax_jaf_load_wp_taxonomy', [$this,'load_wp_taxonomy']);
        add_action('wp_ajax_jaf_load_wp_taxonomies', [$this,'load_wp_taxonomies']);
        add_action('wp_ajax_jaf_refresh_local_taxonomy', [$this,'refresh_local_taxonomy']);
        add_action('wp_ajax_jaf_delete_wp_terms', [$this,'delete_wp_terms']);
        add_action('wp_ajax_jaf_save_wp_term_selection', [$this,'save_wp_term_selection']);
        add_action('wp_ajax_jaf_blogger_publish', [$this,'blogger_publish']);
        add_action('admin_post_jaf_blogger_oauth', [$this,'blogger_oauth_callback']);
        add_action('admin_post_jaf_blogger_refresh_blogs', [$this,'refresh_blogger_blogs']);
        add_action('wp_ajax_jaf_blogger_refresh_blogs', [$this,'ajax_refresh_blogger_blogs']);
        add_action('admin_post_jaf_blogger_add_profile', [$this,'add_blogger_profile']);
        add_action('admin_post_jaf_save_wp_profile', [$this,'save_wp_profile']);
        add_action('admin_post_jaf_save_blogger_profile', [$this,'save_blogger_profile']);
        add_action('admin_post_jaf_save_local_taxonomy', [$this,'save_local_taxonomy']);
        add_action('admin_post_jaf_save_local_adsense', [$this,'save_local_adsense']);
        add_action('admin_post_jaf_delete_wp_profile', [$this,'delete_wp_profile']);
    }

    function menu() {
        // Workspace Buat Artikel berdiri sebagai menu utama WordPress, tetapi
        // tetap sepenuhnya dikendalikan oleh status Modul Extractor AI di Japur Suite.
        if (JapurSuite_Core::module_enabled('extractor')) {
            add_menu_page(
                'Buat Artikel',
                'Buat Artikel',
                'edit_posts',
                'jaf-extractor-workflow',
                [$this,'workflow_page'],
                'dashicons-edit-page',
                3
            );
        }

        // Halaman pengaturan tetap berada di bawah Japur Suite.
        add_submenu_page('japur-suite','Japur Extractor AI','Extractor AI','manage_options','jaf-extractor',[$this,'settings_page']);
    }

    function workflow_page() {
        $_GET['tab'] = 'workflow';
        $this->page();
    }

    function settings_page() {
        if (!current_user_can('manage_options')) wp_die('Akses ditolak.');
        if (!isset($_GET['tab']) || sanitize_key($_GET['tab']) === 'workflow') {
            $_GET['tab'] = 'wordpress';
        }
        $this->page();
    }

    function settings() {
        register_setting('jaf_group','jaf_options',function($in){
            $o=$this->get_secure_options();
            $save_context=sanitize_key($_POST['jaf_save_context']??'');
            $save_messages=[
                'openai'=>'Pengaturan OpenAI berhasil disimpan.',
                'google'=>'Koneksi Google berhasil disimpan.',
                'prompt'=>'Master Prompt berhasil disimpan.'
            ];
            $api_key=trim((string)($in['api_key']??''));
            $central_openai = (string) get_option('japur_api_openai_key', '');
            if (strpos($central_openai, 'jafenc:v1:') === 0) $central_openai = $this->decrypt_secret_deep($central_openai);
            if($central_openai!=='') $api_key=$central_openai;
            elseif($api_key==='') $api_key=(string)($o['api_key']??'');
            $client_secret=trim((string)($in['blogger_client_secret']??''));
            if($client_secret==='') $client_secret=(string)($o['blogger_client_secret']??'');
            $out=[
                'api_key'=>sanitize_text_field($api_key),
                'text_model'=>sanitize_text_field($in['text_model']??$o['text_model']??'gpt-5.6-luna'),
                'image_model'=>sanitize_text_field($in['image_model']??$o['image_model']??'gpt-image-2'),
                'master_prompt'=>wp_kses_post($in['master_prompt']??$o['master_prompt']??JAF_Prompt::default_prompt()),
                'blogger_prompt'=>wp_kses_post($in['blogger_prompt']??$o['blogger_prompt']??JAF_Prompt::default_blogger_prompt()),
                'blogger_master_prompt'=>wp_kses_post($in['blogger_master_prompt']??$o['blogger_master_prompt']??JAF_Prompt::default_blogger_prompt()),
                'image_prompt'=>sanitize_textarea_field($in['image_prompt']??$o['image_prompt']??''),
                'blogger_client_id'=>sanitize_text_field($in['blogger_client_id']??$o['blogger_client_id']??''),
                'blogger_client_secret'=>sanitize_text_field($client_secret),
                'blogger_blog_id'=>sanitize_text_field($in['blogger_blog_id']??$o['blogger_blog_id']??''),
                'blogger_blog_url'=>esc_url_raw($in['blogger_blog_url']??$o['blogger_blog_url']??''),
                'blogger_labels'=>sanitize_textarea_field($in['blogger_labels']??$o['blogger_labels']??''),
                // Token OAuth tidak dikirim oleh form settings. Wajib dipertahankan agar
                // klik "Simpan Pengaturan Blogger" tidak memutus koneksi yang sudah berhasil.
                // Token OAuth disimpan terpisah dari form settings agar tidak pernah
                // tertimpa oleh options.php. Nilai lama tetap dipertahankan untuk migrasi.
                'blogger_refresh_token'=>sanitize_text_field($o['blogger_refresh_token']??''),
                'blogger_access_token'=>sanitize_text_field($o['blogger_access_token']??''),
                'blogger_token_expires'=>absint($o['blogger_token_expires']??0),
                'blogger_connected'=>!empty($o['blogger_connected']) ? 1 : 0,
                'blogger_available_blogs'=>is_array($o['blogger_available_blogs']??null)?$o['blogger_available_blogs']:[],
                // Website Ini Web Adsense disimpan pada option khusus dan tidak ikut form Settings API.
                'local_taxonomy_cache'=>is_array($o['local_taxonomy_cache']??null)?$o['local_taxonomy_cache']:[],
                'local_taxonomy_selected'=>is_array($o['local_taxonomy_selected']??null)?$o['local_taxonomy_selected']:[],
                'blogger_profiles'=> $this->sanitize_profiles($in['blogger_profiles']??[], $o['blogger_profiles']??[]),
                'wordpress_profiles'=> $this->sanitize_wordpress_profiles($in['wordpress_profiles']??[], $o['wordpress_profiles']??[])
            ];
            if(isset($save_messages[$save_context])){
                // Settings API tidak selalu merender settings_errors() pada halaman modular ini.
                // Simpan konteks sekali pakai agar dispatcher global dapat menampilkan Toast setelah redirect.
                set_transient('jaf_save_feedback_' . get_current_user_id(), [
                    'message' => $save_messages[$save_context],
                    'type' => 'success',
                ], 60);
            }
            return $this->secure_options($out);
        });
        add_filter('option_page_capability_jaf_group', function($cap){ return 'manage_options'; });
    }

    function assets($hook) {
        $page = sanitize_key($_GET['page']??'');
        if ($hook!=='japur-suite_page_jaf-extractor' && $hook!=='toplevel_page_jaf-extractor-workflow' && !in_array($page,['jaf-extractor','jaf-extractor-workflow'],true)) return;
        wp_enqueue_style('jaf-admin',JAF_URL.'assets/css/admin.css',[],JAF_VER.'-'.@filemtime(JAF_DIR.'assets/css/admin.css'));
        wp_enqueue_script('jaf-admin',JAF_URL.'assets/js/admin.js',['jquery'],JAF_VER.'-'.@filemtime(JAF_DIR.'assets/js/admin.js'),true);
        wp_localize_script('jaf-admin','JAF',[
            'ajax'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('jaf_nonce'),
            'master_settings'=>class_exists('Japur_Master_Workflow_Settings') ? Japur_Master_Workflow_Settings::get_all() : []
        ]);
    }

    private function auth($publish=false) {
        if($this->bg_context()) return current_user_can('edit_posts');
        if ($publish ? !current_user_can('edit_posts') : !current_user_can('edit_posts')) return false;
        if (!check_ajax_referer('jaf_nonce','nonce',false)) {
            wp_send_json_error(['message'=>'Nonce tidak valid.'],403);
            return false;
        }
        return true;
    }

    private function auth_settings() {
        if(!current_user_can('manage_options')) { wp_send_json_error(['message'=>'Akses ditolak.'],403); return false; }
        if(!check_ajax_referer('jaf_nonce','nonce',false)) { wp_send_json_error(['message'=>'Nonce tidak valid.'],403); return false; }
        return true;
    }

    function page() {
        $this->render_save_notice();
        $o=$this->o;
        $tab=sanitize_key($_GET['tab']??'workflow');
        if(!in_array($tab,['workflow','prompt','wordpress','blogger'],true)) $tab='workflow';
        $active=JapurSuite_Core::module_enabled('extractor');
        $is_workflow = ($tab==='workflow');
        echo '<div class="wrap jwp">';
        echo '<div class="jwp-head"><div><div class="jwp-eyebrow">JAPUR SUITE • MODUL</div><h1>'.($is_workflow?'Buat Artikel':'Japur Extractor AI').' <small>v'.esc_html(JAF_VER).'</small></h1></div></div>';
        echo '<div class="japi-status '.($active?'is-on':'is-off').'"><span class="japi-status-dot"></span><div class="japi-status-label"><strong>Modul '.($active?'aktif':'nonaktif').'</strong></div><a class="japi-dashboard-link" href="'.esc_url(admin_url('admin.php?page=jaf-extractor&tab=wordpress')).'">Pengaturan</a></div>';
        if(!$is_workflow){ echo '<nav class="jwp-tabs"><a class="'.($tab==='wordpress'?'active':'').'" href="'.esc_url(admin_url('admin.php?page=jaf-extractor&tab=wordpress')).'">WordPress</a><a class="'.($tab==='prompt'?'active':'').'" href="'.esc_url(admin_url('admin.php?page=jaf-extractor&tab=prompt')).'">Master Prompt</a><a class="'.($tab==='blogger'?'active':'').'" href="'.esc_url(admin_url('admin.php?page=jaf-extractor&tab=blogger')).'">Blogger</a></nav>'; }
        if($tab==='prompt'){ $this->prompt_page($o); return; }
        if($tab==='wordpress'){ $this->wordpress_page($o); return; }
        if($tab==='blogger'){ $this->blogger_page($o); return; }

        $wp_tax_cache=$this->workflow_taxonomy_cache();
        $local_categories=$wp_tax_cache['local']['categories']??[];
        $local_tags=$wp_tax_cache['local']['tags']??[];
        $profiles=$this->blogger_profiles();
        echo '<div class="jwp-card" id="jaf-target-card">';
        echo '<div class="jwp-card-head"><div><h2>Tujuan Publikasi</h2><p>Pilih platform dan pengaturan dasar sebelum membuat artikel.</p></div><span class="jwp-badge">Workflow</span></div>';
        echo '<div class="jwp-fields">';
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Tujuan Publikasi</div><div class="jwp-desc">Pilih tujuan terlebih dahulu. Opsi berikutnya akan menyesuaikan platform.</div></div><div class="jwp-input"><select id="jaf-publish-target"><option value="" selected disabled>Pilih tujuan Publish</option><option value="wordpress">WordPress</option><option value="blogger">Blogger</option><option value="multi">Wordpress + Blogger</option></select></div></div>';
        $wp_profiles=$this->wordpress_profiles();
        echo '<script>window.JAF_WP_TAXONOMY_CACHE='.wp_json_encode($wp_tax_cache).';</script>';
        echo '<div class="jwp-row" id="jaf-wp-profile-wrap"><div class="jwp-copy"><div class="jwp-title">Website WordPress</div><div class="jwp-desc">Pilih website tujuan. Jika tidak memilih profil, artikel tetap diterbitkan ke website ini.</div></div><div class="jwp-input"><select id="jaf-wp-profile"><option value="">'.esc_html(get_bloginfo('name')).'</option>';
        foreach($wp_profiles as $wid=>$wp){ $wn=trim((string)($wp['name']??'')); if($wn==='') $wn='Website WordPress'; echo '<option value="'.esc_attr($wid).'">'.esc_html($wn).'</option>'; }
        echo '</select></div></div>';
        echo '<div class="jwp-row" id="jaf-blogger-profile-wrap" style="display:none"><div class="jwp-copy"><div class="jwp-title">Profil Blogger</div><div class="jwp-desc">Label dan Prompt Blogger akan mengikuti profil yang dipilih.</div></div><div class="jwp-input"><select id="jaf-blogger-profile">';
        foreach($profiles as $pid=>$pr){ $profile_name=trim((string)($pr['name']??'')); if($profile_name==='') $profile_name='Profil Blogger'; echo '<option value="'.esc_attr($pid).'">'.esc_html($profile_name).'</option>'; }
        echo '</select></div></div>';
        // Multi Website memakai endpoint generate/thumbnail/publish yang sama dengan mode tunggal.
        // Data tujuan hanya dikirim ke browser untuk membangun antrean; kredensial tidak ikut.
        $multi_targets=[];
        $local_host=(string)wp_parse_url(home_url('/'),PHP_URL_HOST);
        $local_host=preg_replace('/^www\./i','',$local_host);
        $local_name=$local_host!==''?$local_host:get_bloginfo('name');
        $local_cats=$wp_tax_cache['local']['categories']??[];
        $local_tags=$wp_tax_cache['local']['tags']??[];
        $multi_targets[]=[
            'key'=>'wordpress:local','target'=>'wordpress','wordpress_profile'=>'','blogger_profile'=>'',
            'name'=>$local_name,'platform'=>'WordPress','adsense'=>$this->is_local_adsense_enabled()?1:0,'language'=>'id',
            'categories'=>array_values(array_filter(array_map(function($x){return is_array($x)?sanitize_text_field($x['name']??''):sanitize_text_field((string)$x);},$local_cats))),
            'tags'=>array_values(array_filter(array_map(function($x){return is_array($x)?sanitize_text_field($x['name']??''):sanitize_text_field((string)$x);},$local_tags))),
            'labels'=>[]
        ];
        foreach($wp_profiles as $wid=>$wp){
            $wid=sanitize_key($wid); $wn=trim((string)($wp['name']??'')); if($wn==='') $wn='Website WordPress';
            $cache=$wp_tax_cache[$wid]??[];
            $cats=is_array($cache['categories']??null)?$cache['categories']:[];
            $tags=is_array($cache['tags']??null)?$cache['tags']:[];
            $multi_targets[]=[
                'key'=>'wordpress:'.$wid,'target'=>'wordpress','wordpress_profile'=>$wid,'blogger_profile'=>'',
                'name'=>$wn,'platform'=>'WordPress','adsense'=>!empty($wp['adsense'])?1:0,'language'=>in_array(($wp['language']??'id'),['id','en'],true)?$wp['language']:'id',
                'categories'=>array_values(array_filter(array_map(function($x){return is_array($x)?sanitize_text_field($x['name']??''):sanitize_text_field((string)$x);},$cats))),
                'tags'=>array_values(array_filter(array_map(function($x){return is_array($x)?sanitize_text_field($x['name']??''):sanitize_text_field((string)$x);},$tags))),
                'labels'=>[]
            ];
        }
        foreach($profiles as $pid=>$pr){
            $pid=sanitize_key($pid); $pn=trim((string)($pr['name']??'')); if($pn==='') $pn='Profil Blogger';
            $multi_targets[]=[
                'key'=>'blogger:'.$pid,'target'=>'blogger','wordpress_profile'=>'','blogger_profile'=>$pid,
                'name'=>$pn,'platform'=>'Blogger','adsense'=>!empty($pr['adsense'])?1:0,'language'=>in_array(($pr['language']??'id'),['id','en'],true)?$pr['language']:'id',
                'categories'=>[],'tags'=>[],'labels'=>$this->profile_labels($pr)
            ];
        }
        echo '<script>window.JAF_MULTI_TARGETS='.wp_json_encode($multi_targets).';</script>';
        echo '<div class="jwp-card jaf-multi-card" id="jaf-multi-target-card" style="display:none"><div class="jwp-card-head"><div><h2>Website Tujuan</h2><p>Pilih beberapa website atau blog. Setiap tujuan diproses satu per satu dengan profil dan prompt masing-masing.</p></div><span class="jwp-badge">Multi</span></div><div class="jwp-fields"><div class="jwp-row" id="jaf-multi-primary-taxonomy-row"><div class="jwp-copy"><div class="jwp-title">Kategori Utama Multi Website</div><div class="jwp-desc">Pilih satu atau beberapa dari 3 kategori utama. Pilihan ini diterapkan ke semua tujuan yang dicentang; pada Blogger dipetakan ke Label dengan nama yang sama.</div></div><div class="jwp-input"><div id="jaf-multi-primary-taxonomy" class="jwp-checks" role="group" aria-label="Kategori Utama Multi Website"><label><input type="checkbox" class="jaf-multi-primary-category" value="Otomotif"> Otomotif</label><label><input type="checkbox" class="jaf-multi-primary-category" value="Investasi"> Investasi</label><label><input type="checkbox" class="jaf-multi-primary-category" value="Gadget"> Gadget</label></div><div id="jaf-multi-primary-taxonomy-status" class="jwp-status" aria-live="polite">Pilih kategori utama bila diperlukan. Default setiap tujuan adalah Berita.</div></div></div><div class="jwp-row" id="jaf-multi-primary-tag-row"><div class="jwp-copy"><div class="jwp-title">Tag Utama Multi Website</div><div class="jwp-desc">Pilih satu atau beberapa tag. Pilihan ini diterapkan ke semua tujuan WordPress yang dicentang; Blogger menggunakan Label.</div></div><div class="jwp-input"><div id="jaf-multi-primary-tags" class="jwp-checks" role="group" aria-label="Tag Utama Multi Website"><label><input type="checkbox" class="jaf-multi-primary-tag" value="Discover" checked="checked"> Discover</label><label><input type="checkbox" class="jaf-multi-primary-tag" value="Trending"> Trending</label></div><div id="jaf-multi-primary-tags-status" class="jwp-status" aria-live="polite">Discover aktif sebagai default untuk tujuan WordPress.</div></div></div><div class="jwp-row" id="jaf-multi-filter-row"><div class="jwp-copy"><div class="jwp-title">Filter Tujuan</div><div class="jwp-desc">Saring daftar tujuan berdasarkan penanda Web Adsense tanpa membuat menu tujuan terpisah.</div></div><div class="jwp-input"><div id="jaf-multi-filter" class="jaf-multi-filter" role="group" aria-label="Filter Tujuan"><button type="button" class="button jaf-multi-filter-btn" data-filter="adsense">Web Adsense</button><button type="button" class="button jaf-multi-filter-btn" data-filter="non-adsense">Web Bukan Adsense</button><button type="button" class="button button-primary jaf-multi-filter-btn is-active" data-filter="all">Semua Website</button></div><div id="jaf-multi-filter-status" class="jwp-status" aria-live="polite">Menampilkan semua website dan blog.</div></div></div><div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Pilih Website & Taxonomy</div><div class="jwp-desc">Setiap website memiliki pilihan Kategori + Tag sendiri untuk WordPress atau Label sendiri untuk Blogger. Pilihan hanya berlaku untuk website tersebut.</div></div><div class="jwp-input"><div id="jaf-multi-targets" class="jaf-multi-targets" role="group" aria-label="Pilih Website Tujuan"></div><div class="jaf-multi-actions"><button type="button" class="button" id="jaf-multi-select-all">Pilih Semua</button><button type="button" class="button" id="jaf-multi-clear-all">Kosongkan</button></div><div id="jaf-multi-selection-status" class="jwp-status" aria-live="polite"></div></div></div></div></div>';
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Jenis Artikel</div><div class="jwp-desc">Tentukan format penulisan yang akan dibuat.</div></div><div class="jwp-input"><select id="jaf-type"><option value="" selected disabled>Pilih jenis Artikel</option><option value="berita">Berita</option><option value="panduan">Panduan</option><option value="pengalaman">Pengalaman Pribadi</option><option value="opini">Opini</option><option value="review">Review</option></select></div></div>';
        echo '</div></div>';

        echo '<div class="jwp-card" id="jaf-wp-options-card"><div class="jwp-card-head"><div><h2 id="jaf-taxonomy-title">Kategori & Tag</h2><p id="jaf-taxonomy-desc">Kategori dan Tag mengikuti Website WordPress yang dipilih.</p></div><span class="jwp-badge" id="jaf-taxonomy-badge">WordPress</span></div><div class="jwp-fields">';
        echo '<div class="jwp-row" id="jaf-wp-taxonomy"><div class="jwp-copy"><div class="jwp-title">Kategori WordPress</div><div class="jwp-desc" id="jaf-category-desc">Kategori dari daftar yang tersimpan di Japur Suite.</div><div id="jaf-category" class="jwp-checks" role="group" aria-label="Kategori WordPress">';
        foreach($local_categories as $term){ $name=is_array($term)?($term['name']??''):(is_object($term)?$term->name:''); if($name==='')continue; $checked=(strcasecmp(trim((string)$name),'Berita')===0)?' checked="checked"':''; echo '<label><input type="checkbox" name="jaf_category[]" value="'.esc_attr($name).'"'.$checked.'> '.esc_html($name).'</label>'; }
        if(!$local_categories) echo '<em>Belum ada kategori tersimpan. Atur daftar di Pengaturan → Japur Extractor AI → WordPress.</em>';
        echo '</div></div></div>';
        echo '<div class="jwp-row" id="jaf-wp-tags"><div class="jwp-copy"><div class="jwp-title">Tag WordPress <span class="jwp-optional">opsional</span></div><div class="jwp-desc" id="jaf-tag-desc">Tag dari daftar yang tersimpan di Japur Suite.</div><div id="jaf-tags" class="jwp-checks">';
        foreach($local_tags as $term){ $name=is_array($term)?($term['name']??''):(is_object($term)?$term->name:''); if($name==='')continue; echo '<label><input type="checkbox" name="jaf_tag[]" value="'.esc_attr($name).'"> '.esc_html($name).'</label>'; }
        if(!$local_tags) echo '<em>Belum ada tag tersimpan. Atur daftar di Pengaturan → Japur Extractor AI → WordPress.</em>';
        echo '</div></div></div>';
        echo '<div class="jwp-row" id="jaf-blogger-labels" style="display:none"><div class="jwp-copy"><div class="jwp-title">Label Blogger</div><div class="jwp-desc">Label mengikuti Profil Blogger yang dipilih.</div><div id="jaf-label" class="jwp-checks" role="group" aria-label="Label Blogger"></div></div></div>';
        echo '</div></div>';

        echo '<div class="jwp-card" id="jaf-settings-card"><div class="jwp-card-head"><div><h2>Pengaturan Artikel</h2><p>Lokasi lead dan bahasa berlaku untuk WordPress maupun Blogger.</p></div><span class="jwp-badge">2 Pengaturan</span></div><div class="jwp-fields">';
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Lokasi Lead</div><div class="jwp-desc">Bisa diganti manual. Default: Jakarta.</div></div><div class="jwp-input"><input id="jaf-location" value="Jakarta" placeholder="Contoh: Jakarta"></div></div>';
        echo '<div class="jwp-row" id="jaf-language-setting-row"><div class="jwp-copy"><div class="jwp-title">Bahasa Artikel</div><div class="jwp-desc">Universal untuk WordPress dan Blogger.</div></div><div class="jwp-input"><select id="jaf-language" class="jaf-language-native" aria-hidden="true" tabindex="-1"><option value="id">Bahasa Indonesia</option><option value="en">English</option></select><div id="jaf-language-picker" class="jaf-language-picker" role="group" aria-label="Bahasa Artikel"><button type="button" class="jaf-language-option active" data-language="id"><span class="jaf-language-flag" aria-hidden="true">🇮🇩</span><span>Bahasa Indonesia</span></button><button type="button" class="jaf-language-option" data-language="en"><span class="jaf-language-flag" aria-hidden="true">🇬🇧</span><span>English</span></button></div></div></div>';
        echo '</div></div>';

        echo '<div class="jwp-card" id="jaf-source-card"><div class="jwp-card-head"><div><h2>Sumber Materi</h2><p>Masukkan materi langsung atau ekstrak isi utama artikel dari tautan tanpa AI.</p></div><span class="jwp-badge">Input</span></div><div class="jwp-fields">';
        echo '<div class="jwp-row jwp-source-row"><div class="jwp-copy"><div class="jwp-title">Metode Input</div><div class="jwp-desc">Pilih sumber materi yang akan diproses.</div></div><div class="jwp-source-tabs"><button type="button" class="button button-primary jwp-source-tab active" data-source="manual">Materi Langsung</button><button type="button" class="button jwp-source-tab" data-source="url">Dari Tautan</button></div></div>';
        echo '<div id="jaf-source-manual" class="jwp-source-panel"><div class="jwp-field-block"><div class="jwp-title">Materi Artikel</div><div class="jwp-desc">Tempel bahan artikel yang ingin diolah. Jika bahan masih berupa HTML atau teks mentah, klik Ekstrak Materi untuk membersihkannya.</div><textarea id="jaf-material" rows="17" placeholder="Tempel materi artikel di sini..."></textarea><div class="jwp-url-action jaf-manual-extract-action"><button type="button" id="jaf-extract-material" class="button">Ekstrak Materi</button></div><div id="jaf-manual-extract-status" class="jwp-status"></div></div></div>';
        echo '<div id="jaf-source-url" class="jwp-source-panel" style="display:none"><div class="jwp-field-block"><div class="jwp-title">URL Artikel</div><div class="jwp-desc">Extractor hanya mengambil isi utama yang layak menjadi materi.</div><div class="jwp-url-action"><input id="jaf-url" type="url" placeholder="https://contoh.com/artikel"><button type="button" id="jaf-extract-url" class="button">Ekstrak Materi</button></div><div id="jaf-extract-status" class="jwp-status"></div></div></div>';
        echo '<div class="jwp-card jaf-preflight-card" id="jaf-preflight-card" style="display:none"><div class="jwp-card-head"><div><h2>Pemeriksaan Sistem</h2><p>Periksa kebutuhan AI dan website tujuan sebelum proses artikel, thumbnail, dan penerbitan dimulai.</p></div><span class="jwp-badge">Preflight</span></div><div class="jwp-fields"><div id="jaf-preflight-status" class="jwp-preflight-status is-idle"><strong>Belum diperiksa.</strong><span>Setelah materi siap, jalankan pemeriksaan sistem.</span></div><div id="jaf-preflight-checks" class="jaf-preflight-checks"></div><div class="jwp-actions"><button type="button" id="jaf-preflight-run" class="button">Periksa Sistem</button></div></div></div>';
        echo '</div></div>';

        $profile_js=[]; foreach($profiles as $pid=>$pr){
            $profile_name=trim((string)($pr['name']??''));
            if($profile_name==='') $profile_name='Profil Blogger';
            $profile_js[$pid]=['name'=>$profile_name,'blog_id'=>(string)($pr['blog_id']??''),'blog_url'=>(string)($pr['blog_url']??''),'labels'=>$this->profile_labels($pr)];
        }
        // JAF_WP_TAXONOMY_CACHE sudah dibangun dari workflow_taxonomy_cache() di atas.
        // Jangan timpa lagi dengan taxonomy_cache mentah, karena untuk profil tambahan
        // sumber workflow harus Kategori/Tag yang sudah disimpan.
        echo '<script>window.JAF_BLOGGER_PROFILES='.wp_json_encode($profile_js).';</script>';
        $jmw = class_exists('Japur_Master_Workflow_Settings') ? Japur_Master_Workflow_Settings::get_all() : [];
        $jmw_attrs = '';
        foreach (['single_wp_background_hide_workflow_after_success','single_wp_foreground_hide_workflow_after_success','single_blogger_background_hide_workflow_after_success','single_blogger_foreground_hide_workflow_after_success'] as $jmw_key) {
            $jmw_attrs .= ' data-' . esc_attr($jmw_key) . '=\"' . (!empty($jmw[$jmw_key]) ? '1' : '0') . '\"';
        }
        echo '<div id="jaf-workflow" class="jaf-workflow" aria-live="polite"></div><div class="jwp-footer-card" id="jaf-next-action-card"' . $jmw_attrs . '><div class="jwp-footer-note"><h3>Alur kerja</h3><p>Materi diproses sesuai tujuan publikasi. Pengaturan API, Prompt, dan Blogger dikelola dari tab di atas.</p><div class="jaf-run-options"><label><input type="checkbox" id="jaf-background-mode" value="1"> Jalankan di belakang layar</label><label><input type="checkbox" id="jaf-wake-lock" value="1" checked> Pertahankan layar tetap hidup saat proses di layar</label></div></div><div class="jwp-actions"><button type="button" id="jaf-generate" class="button button-primary button-large"><span class="dashicons dashicons-edit" aria-hidden="true"></span> Buat Artikel dan Gambar</button></div></div>';
        echo '<div id="jaf-status" class="jwp-status"></div><div id="jaf-result"></div></div>';
    }

    function wordpress_page($o) {
        $profiles=$this->wordpress_profiles();
        $selected=isset($_GET['profile'])?sanitize_key($_GET['profile']):'';
        $base=admin_url('admin.php?page=jaf-extractor&tab=wordpress');

        if($selected==='new'){
            $new_nonce=sanitize_text_field(wp_unslash($_GET['_wpnonce']??''));
            if(!wp_verify_nonce($new_nonce,'jaf_wp_profile_new')) wp_die('Nonce tidak valid.');
            $selected='wp_'.substr(wp_generate_uuid4(),0,8);
            $profiles[$selected]=['name'=>'Website WordPress Baru','site_url'=>'','username'=>'','app_password'=>'','taxonomy_cache'=>[],'taxonomy_selected'=>[],'adsense'=>0,'language'=>'id'];
            $o['wordpress_profiles']=$profiles; $this->save_secure_options($o);
            echo '<script>location.href='.wp_json_encode($base.'&profile='.$selected).';</script>'; return;
        }


        echo '<div class="jwp-card"><div class="jwp-card-head"><div><h2>Pengaturan WordPress</h2><p>Kelola setiap website WordPress secara terpisah. Buka <strong>Kelola</strong> pada domain untuk mengatur koneksi serta memasukkan Kategori dan Tag secara manual.</p></div><span class="jwp-badge">Per Domain</span></div>';
        echo '<div class="jwp-profiles">';
        $local_base=$base.'&profile=local';
        echo '<div class="jwp-profile-item jaf-wp-profile-featured"><div><strong>'.esc_html(get_bloginfo('name')).'</strong><small>'.esc_html(home_url('/')).' · Website ini</small></div><div class="jwp-profile-actions"><a class="button button-primary" href="'.esc_url($local_base).'#jaf-taxonomy-manager">Kelola</a></div></div>';
        if($profiles){
            foreach($profiles as $id=>$pr){
                $n=trim((string)($pr['name']??'')); if($n==='')$n='Website WordPress';
                $u=trim((string)($pr['site_url']??''));
                $ready=(!empty($pr['username'])&&!empty($pr['app_password'])&&!empty($u));
                $delete_action=admin_url('admin-post.php');
                echo '<div class="jwp-profile-item"><div><strong>'.esc_html($n).'</strong><small>'.esc_html($u).' · '.($ready?'Koneksi tersimpan':'Belum lengkap').'</small></div><div class="jwp-profile-actions"><a class="button button-primary" href="'.esc_url($base.'&profile='.rawurlencode($id).'#jaf-profile-editor').'">Kelola</a><form method="post" action="'.esc_url($delete_action).'" class="jaf-delete-wp-profile-form" style="display:inline;margin:0">'.wp_nonce_field('jaf_delete_wp_profile_'.$id,'_wpnonce',true,false).'<input type="hidden" name="action" value="jaf_delete_wp_profile"><input type="hidden" name="profile" value="'.esc_attr($id).'"><button type="submit" class="button button-link-delete jaf-delete-wp-profile">Hapus</button></form></div></div>';
            }
        } else {
            echo '<div class="jwp-info"><strong>Belum ada profil website tambahan.</strong><p>Tambahkan website WordPress untuk menjadikannya tujuan publikasi terpisah.</p></div>';
        }
        echo '</div><div class="jwp-actions"><a class="button button-primary button-large" href="'.esc_url(wp_nonce_url($base.'&profile=new','jaf_wp_profile_new')).'"><span class="dashicons dashicons-plus-alt" aria-hidden="true"></span> Tambah Website WordPress</a></div></div>';

        if($selected===''){
            echo '<div class="jwp-info jaf-wp-manager-intro"><strong>Kelola per domain</strong><p>Pilih <strong>Kelola</strong> pada website di atas. Masukkan Kategori dan Tag secara manual, lalu simpan. Daftar tersebut akan otomatis tampil saat domain dipilih di Buat Artikel.</p></div>';
            return;
        }

        if($selected==='local'){
            $name=get_bloginfo('name');
            echo '<div class="jwp-profile-sticky"><div class="jwp-profile-sticky-copy"><span class="jwp-profile-sticky-label">Sedang mengelola</span><strong>'.esc_html($name).' — Website Ini</strong></div><a class="button" href="'.esc_url($base).'">Kembali ke Daftar</a></div>';
            // Website utama (jayapurnama.com / Website Ini) memakai taxonomy live dari
            // WordPress saat halaman dibuka. Tidak perlu input manual dan tidak memakai
            // cache pilihan domain tambahan.
            $local_live=[];
            foreach(['categories'=>'Kategori','tags'=>'Tag'] as $tax=>$label){
                $terms=$tax==='categories'
                    ? get_categories(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC'])
                    : get_tags(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
                $local_live[$tax]=[];
                if(is_array($terms)) foreach($terms as $term){
                    if(is_object($term) && !empty($term->term_id) && isset($term->name)){
                        $local_live[$tax][]=['id'=>absint($term->term_id),'name'=>sanitize_text_field($term->name),'slug'=>sanitize_title($term->slug??$term->name)];
                    }
                }
            }
            echo '<div id="jaf-taxonomy-manager" class="jwp-card jaf-local-taxonomy-card" style="margin-top:16px">';
            echo '<div class="jwp-card-head"><div><h2>Kategori &amp; Tag</h2><p>Data live dari WordPress website ini. Tidak perlu input manual.</p></div><span class="jwp-badge">Otomatis</span></div>';
            $local_adsense=$this->is_local_adsense_enabled();
            echo '<div class="jwp-row jaf-adsense-mark-row"><div class="jwp-copy"><div class="jwp-title">Web Adsense</div><div class="jwp-desc">Tandai Website Ini agar masuk filter Web Adsense di Buat Artikel.</div></div><div class="jwp-input"><form method="post" action="'.esc_url(admin_url('admin-post.php?action=jaf_save_local_adsense')).'" style="margin:0">'.wp_nonce_field('jaf_save_local_adsense','_wpnonce',true,false).'<input type="hidden" name="local_adsense" value="0"><label><input type="checkbox" name="local_adsense" value="1" '.checked($local_adsense,true,false).' onchange="this.form.submit()"> Tandai sebagai Web Adsense</label><span class="jaf-adsense-save-note" aria-live="polite">Perubahan disimpan otomatis.</span></form></div></div>';
            echo '<div class="jaf-local-taxonomy-grid">';
            foreach(['categories'=>'Kategori','tags'=>'Tag'] as $tax=>$label){
                $items=$local_live[$tax];
                echo '<section class="jaf-local-tax-box" data-taxonomy="'.esc_attr($tax).'">';
                echo '<div class="jaf-local-tax-head"><div class="jaf-local-tax-icon"><span class="dashicons '.($tax==='categories'?'dashicons-category':'dashicons-tag').'" aria-hidden="true"></span></div><div class="jaf-local-tax-title"><h3>'.esc_html($label).'</h3><span class="jaf-local-tax-count">'.count($items).' item</span></div></div>';
                echo '<div class="jaf-local-tax-desc">Mengikuti daftar terbaru di WordPress secara otomatis.</div>';
                echo '<div class="jaf-local-tax-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" class="jaf-local-term-search" placeholder="Cari '.esc_attr(strtolower($label)).'..." autocomplete="off"></div>';
                echo '<div class="jaf-local-term-list" aria-live="polite">';
                if(!$items){
                    echo '<div class="jaf-local-term-empty">Belum ada '.esc_html(strtolower($label)).'.</div>';
                } else {
                    foreach($items as $it) echo '<span class="jaf-local-term-chip">'.esc_html($it['name']).'</span>';
                }
                echo '</div><div class="jaf-local-tax-footer"><span class="jaf-local-visible-count">'.count($items).' ditampilkan</span></div>';
                echo '</section>';
            }
            echo '</div>';
            echo '<div class="jwp-actions jaf-local-tax-actions"><a class="button" href="'.esc_url($base).'">Kembali</a><a class="button button-primary button-large jaf-refresh-local-taxonomy" href="'.esc_url($local_base).'#jaf-taxonomy-manager"><span class="dashicons dashicons-update" aria-hidden="true"></span> Perbarui Daftar</a></div>';
            echo '<div class="jaf-local-tax-status" aria-live="polite"></div></div>';
            echo '<div class="jwp-info"><strong>Catatan</strong><p>Kategori dan Tag Website Ini selalu mengikuti data WordPress terbaru. Tombol Perbarui Daftar mengambil ulang data saat itu juga tanpa mengubah kategori atau tag di website. Profil WordPress tambahan tetap terpisah per domain.</p></div>';
            return;
        }

        $p=$profiles[$selected]??null;
        if(!$p){ echo '<div class="jwp-info" style="margin-top:16px"><strong>Profil tidak ditemukan.</strong><p>Silakan kembali ke daftar profil WordPress.</p></div>'; return; }
        $name=trim((string)($p['name']??'')); if($name==='')$name='Website WordPress';
        echo '<div class="jwp-profile-sticky"><div class="jwp-profile-sticky-copy"><span class="jwp-profile-sticky-label">Sedang mengelola</span><strong>'.esc_html($name).'</strong></div><a class="button" href="'.esc_url($base).'">Kembali ke Daftar</a></div>';
        $save_action=admin_url('admin-post.php?action=jaf_save_wp_profile');
        echo '<div id="jaf-profile-editor" class="jwp-card jwp-profile-editor" style="margin-top:16px"><div class="jwp-card-head"><div><h2>Kelola Website WordPress</h2><p>Atur koneksi domain ini. Kategori dan Tag di bawahnya juga hanya berlaku untuk domain ini.</p></div><span class="jwp-badge">Profil</span></div><div class="jwp-fields"><form method="post" action="'.esc_url($save_action).'">'.wp_nonce_field('jaf_save_wp_profile_'.$selected,'_wpnonce',true,false).'<input type="hidden" name="profile" value="'.esc_attr($selected).'"><input type="hidden" name="taxonomy_manual_dirty" value="0">';
        $this->profile_row('Nama Website','name',$p,$selected,'text','Contoh: Rafteninfo.com','wp_profile','jaf-wp-name');
        $this->profile_row('URL Website','site_url',$p,$selected,'url','https://contoh.com','wp_profile','jaf-wp-site-url');
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Bahasa Artikel</div><div class="jwp-desc">Bahasa default saat profil ini digunakan dalam Multi Website. Profil lama tetap Indonesia.</div></div><div class="jwp-input"><select name="wp_profile[language]"><option value="id"'.selected(($p['language']??'id'),'id',false).'>🇮🇩 Bahasa Indonesia</option><option value="en"'.selected(($p['language']??'id'),'en',false).'>🇬🇧 English</option></select></div></div>';
        $this->profile_row('Username WordPress','username',$p,$selected,'text','Username akun WordPress','wp_profile','jaf-wp-username');
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Web Adsense</div><div class="jwp-desc">Tandai website ini agar masuk filter Web Adsense di Buat Artikel.</div></div><div class="jwp-input"><label><input type="checkbox" name="wp_profile[adsense]" value="1" '.checked(!empty($p['adsense']),true,false).'> Tandai sebagai Web Adsense</label></div></div>';
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Application Password</div><div class="jwp-desc">Gunakan Application Password WordPress, bukan password login biasa. Password disimpan terenkripsi dan tidak ditampilkan kembali.</div></div><div class="jwp-input"><input id="jaf-wp-app-password" type="password" name="wp_profile[app_password]" value="" placeholder="'.esc_attr(!empty($p['app_password'])?'Tersimpan — isi untuk mengganti':'Masukkan Application Password').'" autocomplete="new-password"></div></div>';
        echo '<div class="jwp-row jaf-taxonomy-action-row"><div class="jwp-copy"><div class="jwp-title">Kategori &amp; Tag Profil</div><div class="jwp-desc">Ambil kategori dan tag dari website tujuan.</div></div><div class="jwp-input"><div class="jaf-term-manager" data-profile="'.esc_attr($selected).'">'.$this->taxonomy_manager_markup($o,$selected,false).'</div></div></div>';
        $manual=is_array($p['taxonomy_manual']??null)?$p['taxonomy_manual']:[];
        $cats=implode(", ",(array)($manual['categories']??[])); $tags=implode(", ",(array)($manual['tags']??[]));
                echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Kategori</div><div class="jwp-desc">Pisahkan dengan koma atau satu item per baris.</div></div><div class="jwp-input"><textarea name="wp_profile[taxonomy_categories]" class="widefat jaf-manual-taxonomy" data-taxonomy="categories" rows="4" placeholder="Contoh: Berita, Teknologi, Otomotif">'.esc_textarea($cats).'</textarea></div></div>';
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Tag <span class="jwp-optional">opsional</span></div><div class="jwp-desc">Pisahkan dengan koma atau satu item per baris.</div></div><div class="jwp-input"><textarea name="wp_profile[taxonomy_tags]" class="widefat jaf-manual-taxonomy" data-taxonomy="tags" rows="4" placeholder="Contoh: teknologi, gadget, berita terbaru">'.esc_textarea($tags).'</textarea></div></div>';
        echo '</div><div class="jwp-actions"><a class="button" href="'.esc_url($base).'">Kembali</a><button type="submit" class="button button-primary button-large"><span class="dashicons dashicons-yes" aria-hidden="true"></span> Simpan Profil</button><button type="button" class="button button-large jaf-test-wp-profile" data-profile="'.esc_attr($selected).'">Test Koneksi</button></div><div class="jwp-profile-note"><strong>Penggunaan</strong><p>Kategori dan Tag yang tersimpan adalah sumber Kategori dan Tag untuk profil ini di halaman Buat Artikel. Edit daftar di atas lalu klik Simpan Profil untuk memperbaruinya.</p></div></form></div></div>';
    }

    private function taxonomy_manager_markup($o,$profile_id='',$local=false) {
        $html='<div class="jaf-taxonomy-profile-fields">';
        $html.='<div class="jaf-term-manager-simple">';
        $html.='<div class="jaf-term-simple-actions">';
        $html.='<button type="button" class="button button-primary jaf-fetch-taxonomies jaf-fetch-taxonomy-simple"><span class="jaf-tax-button-content"><span class="dashicons dashicons-update" aria-hidden="true"></span><span class="jaf-tax-button-label">Ambil Kategori dan Tag</span></span></button>';
        $html.='</div>';
        $html.='<div class="jaf-term-manager-status" aria-live="polite"></div>';
                $html.='</div></div>';
        return $html;
    }

    private function workflow_taxonomy_cache() {
        $o=$this->get_secure_options();
        $out=['local'=>['categories'=>[],'tags'=>[]]];
        $local=is_array($o['local_taxonomy_manual']??null)?$o['local_taxonomy_manual']:[];
        $legacy_local=is_array($o['local_taxonomy_cache']??null)?$o['local_taxonomy_cache']:[];
        // Website utama selalu memakai taxonomy live. Ini sengaja tidak membaca
        // local_taxonomy_manual/cache agar perubahan di WordPress langsung terlihat.
        foreach(['categories'=>'categories','tags'=>'tags'] as $tax=>$unused){
            $terms=$tax==='categories'
                ? get_categories(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC'])
                : get_tags(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
            $items=[];
            if(is_array($terms)) foreach($terms as $term){
                if(is_object($term) && !empty($term->term_id) && isset($term->name)){
                    $items[]=['id'=>absint($term->term_id),'name'=>sanitize_text_field($term->name),'slug'=>sanitize_title($term->slug??$term->name)];
                }
            }
            $out['local'][$tax]=$items;
        }
        $profiles=is_array($o['wordpress_profiles']??null)?$o['wordpress_profiles']:[];
        $local_host=strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST));
        $local_host=preg_replace('/^www\./i','',$local_host);
        foreach($profiles as $id=>$p){
            $out[$id]=['categories'=>[],'tags'=>[]];
            // Jika profil tambahan menunjuk ke domain WordPress yang sedang dipakai
            // sebagai Website Ini, taxonomy-nya harus tetap memakai sumber LIVE lokal.
            // Jangan memaksa workflow memakai cache/manual hanya karena profil tersebut
            // memiliki ID internal sendiri.
            $profile_host=strtolower((string)wp_parse_url($p['site_url']??'',PHP_URL_HOST));
            $profile_host=preg_replace('/^www\./i','',$profile_host);
            if($local_host!=='' && $profile_host!=='' && $profile_host===$local_host){
                $out[$id]=[
                    'categories'=>$out['local']['categories'],
                    'tags'=>$out['local']['tags'],
                    'source'=>'local-live'
                ];
                continue;
            }
            $manual=is_array($p['taxonomy_manual']??null)?$p['taxonomy_manual']:[];
            // Untuk profil tambahan, Kategori/Tag yang tersimpan adalah sumber utama workflow.
            // Tombol Ambil Kategori dan Tag hanya menjadi alat untuk mengisi daftar tersebut.
            foreach(['categories','tags'] as $tax){
                $names=is_array($manual[$tax]??null)?$manual[$tax]:[];
                $names=array_values(array_unique(array_filter(array_map(function($name){return sanitize_text_field($name);},$names))));
                $out[$id][$tax]=array_map(function($name){return ['id'=>0,'name'=>(string)$name,'slug'=>sanitize_title($name)];},$names);
            }
        }
        return $out;
    }

    private function parse_manual_terms($raw) {
        if(!is_string($raw)) $raw='';
        $raw=wp_unslash($raw);
        $parts=preg_split('/[,\r\n]+/', $raw);
        $out=[];
        foreach($parts as $part){
            $name=sanitize_text_field(trim($part));
            if($name!=='') $out[]=$name;
        }
        return array_values(array_unique($out));
    }


    function prompt_page($o) {
        
        echo '<form method="post" action="options.php">'; settings_fields('jaf_group'); echo '<input type="hidden" name="jaf_save_context" value="prompt">';
        echo '<div class="jwp-card"><div class="jwp-card-head"><div><h2>Master Prompt WordPress</h2><p>Prompt khusus output WordPress. Dipakai ketika Tujuan Publikasi = WordPress.</p></div><span class="jwp-badge">WordPress</span></div><div class="jwp-prompt-body"><textarea class="large-text code" rows="32" name="jaf_options[master_prompt]">'.esc_textarea($o['master_prompt']).'</textarea></div></div>';
        echo '<div class="jwp-card"><div class="jwp-card-head"><div><h2>Universal Master Prompt Blogger</h2><p>Prompt dasar untuk semua profil Blogger. Prompt profil menjadi supplement.</p></div><span class="jwp-badge">Blogger</span></div><div class="jwp-prompt-body"><textarea class="large-text code" rows="32" name="jaf_options[blogger_master_prompt]">'.esc_textarea($o['blogger_master_prompt']??JAF_Prompt::default_blogger_prompt()).'</textarea></div></div>';
        echo '<div class="jwp-card"><div class="jwp-card-head"><div><h2>Prompt Thumbnail Global</h2><p>Prompt thumbnail yang dipakai untuk WordPress maupun Blogger.</p></div><span class="jwp-badge">Gambar</span></div><div class="jwp-prompt-body"><textarea class="large-text code" rows="8" name="jaf_options[image_prompt]">'.esc_textarea($o['image_prompt']).'</textarea></div><div class="jwp-actions"><button type="submit" class="button button-primary button-large"><span class="dashicons dashicons-yes" aria-hidden="true"></span> Simpan Prompt</button></div></div></form>';
    }
    private function row($label,$key,$o,$type='text',$desc='') {
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">'.esc_html($label).'</div>'.($desc!==''?'<div class="jwp-desc">'.esc_html($desc).'</div>':'').'</div><div class="jwp-input"><input type="'.$type.'" name="jaf_options['.esc_attr($key).']" value="'.esc_attr($o[$key]??'').'" autocomplete="off"></div></div>';
    }

    private function secret_row($label,$key,$o,$desc='') {
        $has=!empty($o[$key]);
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">'.esc_html($label).'</div><div class="jwp-desc">'.esc_html($desc).'</div></div><div class="jwp-input"><input type="password" name="jaf_options['.esc_attr($key).']" value="" placeholder="'.esc_attr($has?'Tersimpan — isi untuk mengganti':'Masukkan secret'). '" autocomplete="new-password"></div></div>';
    }

    function refresh_local_taxonomy() {
        if(!$this->auth()) return;
        $out=['categories'=>[],'tags'=>[]];
        foreach(['categories','tags'] as $taxonomy){
            $terms=$taxonomy==='categories'
                ? get_categories(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC'])
                : get_tags(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
            if(is_array($terms)) foreach($terms as $term){
                if(is_object($term) && !empty($term->term_id) && isset($term->name)){
                    $out[$taxonomy][]=[
                        'id'=>absint($term->term_id),
                        'name'=>sanitize_text_field($term->name),
                        'slug'=>sanitize_title($term->slug??$term->name)
                    ];
                }
            }
        }
        wp_send_json_success([
            'categories'=>$out['categories'],
            'tags'=>$out['tags'],
            'message'=>'Daftar Kategori dan Tag berhasil diperbarui dari WordPress.'
        ]);
    }

    function load_wp_taxonomies() {
        if(!$this->auth()) return;
        $profile_id=sanitize_key($_POST['profile']??'');
        if($profile_id==='') wp_send_json_error(['message'=>'Website Ini memakai taxonomy otomatis. Pilih profil WordPress tambahan terlebih dahulu.']);
        $o=$this->get_secure_options();
        $profiles=is_array($o['wordpress_profiles']??null)?$o['wordpress_profiles']:[];
        $profile=$profiles[$profile_id]??null;
        if(!$profile) wp_send_json_error(['message'=>'Profil WordPress tidak ditemukan.']);
        if(empty($profile['site_url'])||empty($profile['username'])||empty($profile['app_password'])) wp_send_json_error(['message'=>'Profil WordPress belum lengkap. Isi URL, Username, dan Application Password lalu simpan profil.']);

        $results=[];
        $manual=is_array($profile['taxonomy_manual']??null)?$profile['taxonomy_manual']:[];
        foreach(['categories'=>'kategori','tags'=>'tag'] as $taxonomy=>$label){
            $items=$this->remote_wp_taxonomy($profile,$taxonomy);
            if(is_wp_error($items)) wp_send_json_error(['message'=>'Gagal mengambil '. $label .': '.$items->get_error_message()]);
            $manual[$taxonomy]=is_array($manual[$taxonomy]??null)?$manual[$taxonomy]:[];
            $seen=[]; $clean=[];
            foreach($manual[$taxonomy] as $name){
                $name=sanitize_text_field(trim((string)$name));
                if($name==='') continue;
                $key=mb_strtolower($name,'UTF-8');
                if(isset($seen[$key])) continue;
                $seen[$key]=1; $clean[]=$name;
            }
            foreach($items as $it){
                $name=sanitize_text_field(trim((string)($it['name']??'')));
                if($name==='') continue;
                $key=mb_strtolower($name,'UTF-8');
                if(isset($seen[$key])) continue;
                $seen[$key]=1; $clean[]=$name;
            }
            $manual[$taxonomy]=$clean;
            $results[$taxonomy]=['items'=>$items,'manual'=>$clean,'count'=>count($items),'manual_count'=>count($clean)];
        }
        $profiles[$profile_id]['taxonomy_manual']=$manual;
        $profiles[$profile_id]['taxonomy_cache']=is_array($profiles[$profile_id]['taxonomy_cache']??null)?$profiles[$profile_id]['taxonomy_cache']:[];
        $profiles[$profile_id]['taxonomy_cache']['categories']=$results['categories']['items'];
        $profiles[$profile_id]['taxonomy_cache']['tags']=$results['tags']['items'];
        $profiles[$profile_id]['taxonomy_cache']['updated_at']=time();
        $o['wordpress_profiles']=$profiles;
        $this->save_secure_options($o);

        wp_send_json_success([
            'profile'=>$profile_id,
            'categories'=>$results['categories']['items'],
            'tags'=>$results['tags']['items'],
            'manual_categories'=>$results['categories']['manual'],
            'manual_tags'=>$results['tags']['manual'],
            'message'=>'Kategori dan Tag berhasil diambil dan dimasukkan ke daftar profil. Anda bisa menghapus atau mengeditnya, lalu klik Simpan Profil.'
        ]);
    }

    function load_wp_taxonomy() {
        if(!$this->auth()) return;
        $profile_id=sanitize_key($_POST['profile']??'');
        $taxonomy=sanitize_key($_POST['taxonomy']??'categories');
        $force=!empty($_POST['force']);
        if(!in_array($taxonomy,['categories','tags'],true)) $taxonomy='categories';
        $o=$this->get_secure_options();
        $label=$taxonomy==='categories'?'kategori':'tag';
        if($profile_id===''){
            $cache=is_array($o['local_taxonomy_cache']??null)?$o['local_taxonomy_cache']:[];
            if(!$force && array_key_exists($taxonomy,$cache) && is_array($cache[$taxonomy])){
                $items=$cache[$taxonomy]; $sel=is_array($o['local_taxonomy_selected'][$taxonomy]??null)?array_map('absint',$o['local_taxonomy_selected'][$taxonomy]):array_map(function($it){return absint($it['id']??0);},$items);
                wp_send_json_success(['taxonomy'=>$taxonomy,'items'=>$items,'selected'=>$sel,'source'=>'local-cache','cached'=>true,'message'=>count($items).' '.($taxonomy==='categories'?'kategori':'tag').' tersedia dari cache.']);
            }
            $terms=$taxonomy==='categories'?get_categories(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC']):get_tags(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
            $items=[]; foreach($terms as $term)$items[]=['id'=>absint($term->term_id),'name'=>(string)$term->name,'slug'=>(string)$term->slug];
            $o['local_taxonomy_cache']=is_array($o['local_taxonomy_cache']??null)?$o['local_taxonomy_cache']:[]; $o['local_taxonomy_cache'][$taxonomy]=$items;
            $sel=is_array($o['local_taxonomy_selected'][$taxonomy]??null)?array_map('absint',$o['local_taxonomy_selected'][$taxonomy]):[];
            if(!$sel)$sel=array_map(function($it){return absint($it['id']??0);},$items);
            $o['local_taxonomy_selected']=is_array($o['local_taxonomy_selected']??null)?$o['local_taxonomy_selected']:[]; $o['local_taxonomy_selected'][$taxonomy]=$sel; $this->save_secure_options($o);
            wp_send_json_success(['taxonomy'=>$taxonomy,'items'=>$items,'selected'=>$sel,'source'=>'local','cached'=>false,'message'=>count($items).' '.($taxonomy==='categories'?'kategori':'tag').' berhasil diambil dan disimpan sebagai cache.']);
        }
        $profiles=is_array($o['wordpress_profiles']??null)?$o['wordpress_profiles']:[]; $profile=$profiles[$profile_id]??null;
        if(!$profile)wp_send_json_error(['message'=>'Profil WordPress tidak ditemukan.']);
        if(empty($profile['site_url'])||empty($profile['username'])||empty($profile['app_password']))wp_send_json_error(['message'=>'Profil WordPress belum lengkap. Isi URL, Username, dan Application Password lalu simpan profil.']);
        $cache=is_array($profile['taxonomy_cache']??null)?$profile['taxonomy_cache']:[];
        if(!$force && array_key_exists($taxonomy,$cache)&&is_array($cache[$taxonomy])){
            $items=$cache[$taxonomy]; $sel=is_array($profile['taxonomy_selected'][$taxonomy]??null)?array_map('absint',$profile['taxonomy_selected'][$taxonomy]):array_map(function($it){return absint($it['id']??0);},$items);
            wp_send_json_success(['taxonomy'=>$taxonomy,'items'=>$items,'selected'=>$sel,'source'=>'remote-cache','profile'=>$profile_id,'cached'=>true,'message'=>count($items).' '.($taxonomy==='categories'?'kategori':'tag').' tersedia dari cache.']);
        }
        $items=$this->remote_wp_taxonomy($profile,$taxonomy); if(is_wp_error($items))wp_send_json_error(['message'=>$items->get_error_message()]);
        $o=$this->get_secure_options(); $profiles=is_array($o['wordpress_profiles']??null)?$o['wordpress_profiles']:[];
        if(isset($profiles[$profile_id])&&is_array($profiles[$profile_id])){
            $profiles[$profile_id]['taxonomy_cache']=is_array($profiles[$profile_id]['taxonomy_cache']??null)?$profiles[$profile_id]['taxonomy_cache']:[]; $profiles[$profile_id]['taxonomy_cache'][$taxonomy]=$items; $profiles[$profile_id]['taxonomy_cache']['updated_at']=time();
            $profiles[$profile_id]['taxonomy_selected']=is_array($profiles[$profile_id]['taxonomy_selected']??null)?$profiles[$profile_id]['taxonomy_selected']:[];
            $old_sel=array_key_exists($taxonomy,$profiles[$profile_id]['taxonomy_selected'])&&is_array($profiles[$profile_id]['taxonomy_selected'][$taxonomy])?array_map('absint',$profiles[$profile_id]['taxonomy_selected'][$taxonomy]):[];
            $available=array_map(function($it){return absint($it['id']??0);},$items);
            $sel=$old_sel?array_values(array_intersect($old_sel,$available)):$available;

            // Legacy handler: hasil Ambil menjadi isi Kategori/Tag profil.
            // Dengan begitu data tetap ada walaupun JavaScript lama masih tersimpan
            // di cache browser; pengguna tinggal menghapus/edit lalu klik Simpan Profil.
            $manual=is_array($profiles[$profile_id]['taxonomy_manual']??null)?$profiles[$profile_id]['taxonomy_manual']:[];
            $manual[$taxonomy]=is_array($manual[$taxonomy]??null)?$manual[$taxonomy]:[];
            $seen=[]; $clean=[];
            foreach($manual[$taxonomy] as $name){
                $name=sanitize_text_field(trim((string)$name));
                if($name==='') continue;
                $key=mb_strtolower($name,'UTF-8');
                if(isset($seen[$key])) continue;
                $seen[$key]=1; $clean[]=$name;
            }
            foreach($items as $it){
                $name=sanitize_text_field(trim((string)($it['name']??'')));
                if($name==='') continue;
                $key=mb_strtolower($name,'UTF-8');
                if(isset($seen[$key])) continue;
                $seen[$key]=1; $clean[]=$name;
            }
            $manual[$taxonomy]=$clean;
            $profiles[$profile_id]['taxonomy_manual']=$manual;
            $profiles[$profile_id]['taxonomy_selected'][$taxonomy]=$sel;
            $o['wordpress_profiles']=$profiles; $this->save_secure_options($o);
        }
        $sel=is_array($profiles[$profile_id]['taxonomy_selected'][$taxonomy]??null)?array_map('absint',$profiles[$profile_id]['taxonomy_selected'][$taxonomy]):[];
        $manual_now=is_array($profiles[$profile_id]['taxonomy_manual'][$taxonomy]??null)?$profiles[$profile_id]['taxonomy_manual'][$taxonomy]:[];
        wp_send_json_success(['taxonomy'=>$taxonomy,'items'=>$items,'selected'=>$sel,'manual'=>$manual_now,'manual_count'=>count($manual_now),'source'=>'remote','profile'=>$profile_id,'cached'=>false,'message'=>count($items).' '.($taxonomy==='categories'?'kategori':'tag').' berhasil diambil dan dimasukkan ke kolom '.($taxonomy==='categories'?'Kategori':'Tag').' Manual. Silakan edit bila perlu, lalu klik Simpan Profil.']);
    }

    private function posted_taxonomy_ids($raw) {
        // jQuery biasanya mengirim array (ids[]), tetapi terima juga JSON/string
        // agar pilihan tetap tersimpan pada browser/WP yang melakukan normalisasi POST.
        if(is_string($raw)){
            $decoded=json_decode(wp_unslash($raw),true);
            if(is_array($decoded)) $raw=$decoded;
            else $raw=preg_split('/\s*,\s*/',wp_unslash($raw),-1,PREG_SPLIT_NO_EMPTY);
        }
        if(!is_array($raw)) $raw=[$raw];
        $ids=[];
        foreach($raw as $id){
            $id=absint($id);
            if($id>0) $ids[]=$id;
        }
        return array_values(array_unique($ids));
    }

    function save_wp_term_selection() {
        if(!$this->auth_settings()) return;
        $profile_id=sanitize_key($_POST['profile']??''); $taxonomy=sanitize_key($_POST['taxonomy']??'');
        $ids_raw=array_key_exists('ids_json',$_POST)?$_POST['ids_json']:($_POST['ids']??[]);
        $ids=$this->posted_taxonomy_ids($ids_raw);
        if(!in_array($taxonomy,['categories','tags'],true))wp_send_json_error(['message'=>'Jenis daftar tidak valid.'],400);
        $o=$this->get_secure_options();
        if($profile_id===''){
            // Website Ini bersifat OTOMATIS. Tidak ada konsep pilihan manual untuk
            // taxonomy lokal. Jika request lama/eksternal mencoba menyimpan pilihan
            // lokal (termasuk array kosong), jangan pernah menyimpan 0 item karena
            // itu dapat membuat daftar workflow terlihat kosong. Pulihkan cache dari
            // taxonomy WordPress live dan selalu anggap semua item aktif.
            $terms=$taxonomy==='categories'
                ? get_categories(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC'])
                : get_tags(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
            $items=[];
            if(is_array($terms)) foreach($terms as $term){
                if(is_object($term) && !empty($term->term_id) && isset($term->name)){
                    $items[]=['id'=>absint($term->term_id),'name'=>sanitize_text_field($term->name),'slug'=>sanitize_title($term->slug??$term->name)];
                }
            }
            $o['local_taxonomy_cache']=is_array($o['local_taxonomy_cache']??null)?$o['local_taxonomy_cache']:[];
            $o['local_taxonomy_cache'][$taxonomy]=$items;
            // Bersihkan state pilihan lama agar Website Ini tidak lagi terjebak
            // pada kondisi "0 dipilih". Workflow lokal tetap membaca data live.
            if(isset($o['local_taxonomy_selected']) && is_array($o['local_taxonomy_selected'])){
                unset($o['local_taxonomy_selected'][$taxonomy]);
            }
            $this->save_secure_options($o);
            $ids=array_map(function($it){return absint($it['id']??0);},$items);
            $label=$taxonomy==='categories'?'kategori':'tag';
            wp_send_json_success(['message'=>'Website Ini menggunakan '.$label.' secara otomatis. '.count($ids).' '.$label.' aktif di Japur Suite. Website WordPress tidak diubah.','taxonomy'=>$taxonomy,'selected'=>$ids,'saved_count'=>count($ids),'items'=>$items,'plugin_only'=>true,'source'=>'local-live']);
        } else {
            $profiles=is_array($o['wordpress_profiles']??null)?$o['wordpress_profiles']:[]; if(!isset($profiles[$profile_id])||!is_array($profiles[$profile_id]))wp_send_json_error(['message'=>'Profil WordPress tidak ditemukan.'],404);
            $cache=is_array($profiles[$profile_id]['taxonomy_cache']??null)?$profiles[$profile_id]['taxonomy_cache']:[]; $items=is_array($cache[$taxonomy]??null)?$cache[$taxonomy]:[]; $allowed=array_map(function($it){return absint($it['id']??0);},$items); $ids=array_values(array_intersect($ids,$allowed));

            // Model gabungan: pilihan dari daftar remote dimasukkan ke kolom Kategori dan Tag.
            // Daftar lama tetap dipertahankan, lalu digabung tanpa duplikat.
            $manual=is_array($profiles[$profile_id]['taxonomy_manual']??null)?$profiles[$profile_id]['taxonomy_manual']:[];
            $manual[$taxonomy]=is_array($manual[$taxonomy]??null)?$manual[$taxonomy]:[];
            $selected_names=[];
            foreach($items as $it){
                $tid=absint($it['id']??0); $name=sanitize_text_field($it['name']??'');
                if($tid>0 && $name!=='' && in_array($tid,$ids,true)) $selected_names[]=$name;
            }
            $merged=array_merge($manual[$taxonomy],$selected_names);
            $clean=[];
            foreach($merged as $name){
                $name=sanitize_text_field(trim((string)$name));
                if($name!=='' && !in_array($name,$clean,true)) $clean[]=$name;
            }
            $manual[$taxonomy]=$clean;
            $profiles[$profile_id]['taxonomy_manual']=$manual;

            // Tetap simpan ID pilihan agar UI sinkronisasi mengingat checkbox terakhir.
            $sel=is_array($profiles[$profile_id]['taxonomy_selected']??null)?$profiles[$profile_id]['taxonomy_selected']:[];
            $sel[$taxonomy]=$ids;
            $profiles[$profile_id]['taxonomy_selected']=$sel;
            $o['wordpress_profiles']=$profiles; $this->save_secure_options($o);

            $manual_items=array_map(function($name){return ['id'=>0,'name'=>(string)$name,'slug'=>sanitize_title($name)];},$clean);
            wp_send_json_success(['message'=>count($selected_names).' '.$label.' dipilih. Daftar digabung ke kolom '.($taxonomy==='categories'?'Kategori':'Tag').' ('.count($clean).' total). Website WordPress tidak diubah.','taxonomy'=>$taxonomy,'selected'=>$ids,'saved_count'=>count($selected_names),'manual_count'=>count($clean),'manual_items'=>$manual_items,'plugin_only'=>true]);
        }
        $label=$taxonomy==='categories'?'kategori':'tag';
        wp_send_json_success(['message'=>count($ids).' '.$label.' dipilih dan disimpan untuk Japur Suite. Website WordPress tidak diubah.','taxonomy'=>$taxonomy,'selected'=>$ids,'saved_count'=>count($ids),'plugin_only'=>true]);
    }

    function delete_wp_terms() {
        if(!$this->auth_settings()) return;
        $profile_id=sanitize_key($_POST['profile']??''); $taxonomy=sanitize_key($_POST['taxonomy']??''); $ids=$this->posted_taxonomy_ids($_POST['ids']??[]);
        if(!in_array($taxonomy,['categories','tags'],true))wp_send_json_error(['message'=>'Jenis daftar tidak valid.'],400); if(empty($ids))wp_send_json_error(['message'=>'Pilih minimal satu item.'],400);
        $o=$this->get_secure_options();
        if($profile_id===''){$cache=is_array($o['local_taxonomy_cache']??null)?$o['local_taxonomy_cache']:[]; $items=is_array($cache[$taxonomy]??null)?$cache[$taxonomy]:[]; $cache[$taxonomy]=array_values(array_filter($items,function($it)use($ids){return !in_array(absint($it['id']??0),$ids,true);})); $o['local_taxonomy_cache']=$cache; $sel=is_array($o['local_taxonomy_selected']??null)?$o['local_taxonomy_selected']:[]; $sel[$taxonomy]=array_values(array_diff(array_map('absint',$sel[$taxonomy]??[]),$ids)); $o['local_taxonomy_selected']=$sel; $this->save_secure_options($o); $items=$cache[$taxonomy];}
        else {$profiles=is_array($o['wordpress_profiles']??null)?$o['wordpress_profiles']:[]; if(!isset($profiles[$profile_id])||!is_array($profiles[$profile_id]))wp_send_json_error(['message'=>'Profil WordPress tidak ditemukan.'],404); $cache=is_array($profiles[$profile_id]['taxonomy_cache']??null)?$profiles[$profile_id]['taxonomy_cache']:[]; $items=is_array($cache[$taxonomy]??null)?$cache[$taxonomy]:[]; $cache[$taxonomy]=array_values(array_filter($items,function($it)use($ids){return !in_array(absint($it['id']??0),$ids,true);})); $profiles[$profile_id]['taxonomy_cache']=$cache; $sel=is_array($profiles[$profile_id]['taxonomy_selected']??null)?$profiles[$profile_id]['taxonomy_selected']:[]; $sel[$taxonomy]=array_values(array_diff(array_map('absint',$sel[$taxonomy]??[]),$ids)); $profiles[$profile_id]['taxonomy_selected']=$sel; $o['wordpress_profiles']=$profiles; $this->save_secure_options($o); $items=$cache[$taxonomy];}
        $label=$taxonomy==='categories'?'kategori':'tag';
        $remaining_selected=[];
        if($profile_id===''){
            $ls=is_array($o['local_taxonomy_selected']??null)?$o['local_taxonomy_selected']:[];
            $remaining_selected=is_array($ls[$taxonomy]??null)?array_values(array_map('absint',$ls[$taxonomy])):[];
        } else {
            $ps=is_array($profiles[$profile_id]['taxonomy_selected']??null)?$profiles[$profile_id]['taxonomy_selected']:[];
            $remaining_selected=is_array($ps[$taxonomy]??null)?array_values(array_map('absint',$ps[$taxonomy])):[];
        }
        wp_send_json_success(['message'=>count($ids).' '.$label.' dikeluarkan dari daftar plugin. Website WordPress tidak diubah.','taxonomy'=>$taxonomy,'deleted'=>count($ids),'items'=>$items,'selected'=>$remaining_selected,'plugin_only'=>true]);
    }

    private function preflight_check_item($key,$label,$ok,$detail='',$http=0,$severity='error') {
        return ['key'=>sanitize_key($key),'label'=>(string)$label,'ok'=>(bool)$ok,'detail'=>(string)$detail,'http'=>(int)$http,'severity'=>$severity];
    }

    private function preflight_remote_error($error,$stage,$target='') {
        $data=is_wp_error($error)?$error->get_error_data():[];
        $http=is_array($data)?absint($data['http_code']??0):0;
        return ['stage'=>(string)$stage,'target'=>(string)$target,'http'=>$http,'message'=>is_wp_error($error)?$error->get_error_message():(string)$error];
    }

    private function preflight_openai_model($model) {
        $model=trim((string)$model);
        if($model==='') return new WP_Error('openai_model','Model belum dikonfigurasi.');
        $key=(string)($this->o['api_key']??'');
        if($key==='') return new WP_Error('openai_auth','OpenAI API Key belum diisi.');
        $r=wp_remote_get('https://api.openai.com/v1/models/'.rawurlencode($model),['timeout'=>30,'headers'=>['Authorization'=>'Bearer '.$key,'Accept'=>'application/json']]);
        if(is_wp_error($r)) return $r;
        $http=(int)wp_remote_retrieve_response_code($r); $body=wp_remote_retrieve_body($r); $data=json_decode($body,true);
        if($http<200||$http>=300) return new WP_Error('openai_model','Model '.$model.' tidak dapat digunakan (HTTP '.$http.').',['http_code'=>$http]);
        return ['http'=>$http,'id'=>(string)($data['id']??$model)];
    }

    /* Background Article Processing — v1.3.237.
     * The existing foreground AJAX pipeline remains untouched. Background jobs
     * use the same generate/thumbnail/publish methods through a signed loopback
     * request, so the proven publisher remains the single source of truth.
     */
    private function bg_jobs() {
        $jobs=get_option('jaf_background_jobs',[]);
        return is_array($jobs)?$jobs:[];
    }
    private function bg_save_jobs($jobs) {
        update_option('jaf_background_jobs',$jobs,false);
    }
    private function bg_secret($job_id,$user_id) {
        return hash_hmac('sha256',(string)$job_id.'|'.absint($user_id),wp_salt('auth'));
    }
    private function bg_context() {
        return !empty($GLOBALS['jaf_background_context']);
    }
    private function bg_schedule($job_id,$delay=1) {
        wp_schedule_single_event(time()+max(1,absint($delay)),'jaf_background_worker',[$job_id]);
        if(function_exists('spawn_cron')) @spawn_cron(time());
    }
    private function bg_update($job_id,$patch) {
        $jobs=$this->bg_jobs(); if(!isset($jobs[$job_id])||!is_array($jobs[$job_id])) return false;
        $jobs[$job_id]=array_merge($jobs[$job_id],$patch,['updated'=>time()]);
        $this->bg_save_jobs($jobs); return true;
    }
    private function bg_current_item(&$job) {
        if(($job['mode']??'single')!=='multi') return null;
        $i=absint($job['index']??0); return isset($job['items'][$i])&&is_array($job['items'][$i])?$job['items'][$i]:null;
    }
    private function bg_response_json($response) {
        if(is_wp_error($response)) return ['success'=>false,'data'=>['message'=>$response->get_error_message()]];
        $body=wp_remote_retrieve_body($response); $json=json_decode($body,true);
        return is_array($json)?$json:['success'=>false,'data'=>['message'=>'Background worker menerima respons yang tidak valid.']];
    }
    function start_background() {
        if(!$this->auth()) return;
        $target=sanitize_key($_POST['target']??'');
        if(!in_array($target,['wordpress','blogger','multi'],true)) wp_send_json_error(['message'=>'Tujuan Publish tidak valid.']);
        $material=trim(wp_unslash($_POST['material']??''));
        if(mb_strlen($material)<30) wp_send_json_error(['stage'=>'MATERI','message'=>'Materi terlalu pendek.']);
        $type=sanitize_key($_POST['type']??'');
        if(!in_array($type,['berita','panduan','pengalaman','opini','review'],true)) wp_send_json_error(['stage'=>'TYPE','message'=>'Jenis Artikel wajib dipilih sebelum proses background dimulai.']);
        $job_id=wp_generate_uuid4(); $user_id=get_current_user_id();
        $base=[
            'id'=>$job_id,'user_id'=>$user_id,'mode'=>$target==='multi'?'multi':'single','target'=>$target,
            'status'=>'queued','stage'=>'ARTICLE','percent'=>3,'message'=>'Menunggu worker background.',
            'created'=>time(),'updated'=>time(),'started_at'=>0,'diagnostics'=>[],'result'=>[],'article_job_token'=>'','auto_publish'=>class_exists('Japur_Master_Workflow_Settings') ? Japur_Master_Workflow_Settings::enabled('background_auto_publish_single') : true,
            'input'=>[
                'material'=>$material,'type'=>$type,'location'=>sanitize_text_field($_POST['location']??''),
                'language'=>sanitize_key($_POST['language']??'id'),'target'=>$target,
                'blogger_profile'=>sanitize_key($_POST['blogger_profile']??''),'wordpress_profile'=>sanitize_key($_POST['wordpress_profile']??''),
                'categories'=>array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['categories']??[]))))),
                'tags'=>array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['tags']??[]))))),
                'labels'=>array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['labels']??[])))))
            ]
        ];
        if($base['mode']==='multi') {
            $items=json_decode(wp_unslash($_POST['items_json']??''),true);
            if(!is_array($items)||empty($items)) wp_send_json_error(['message'=>'Belum ada website/blog tujuan yang dipilih.']);
            $clean=[]; foreach($items as $it){ if(!is_array($it)) continue; $key=sanitize_text_field($it['key']??''); if($key==='') continue; $clean[]=[
                'key'=>$key,'name'=>sanitize_text_field($it['name']??'Website'),'platform'=>sanitize_text_field($it['platform']??''),
                'target'=>sanitize_key($it['target']??''),'wordpress_profile'=>sanitize_key($it['wordpress_profile']??''),
                'blogger_profile'=>sanitize_key($it['blogger_profile']??''),'language'=>sanitize_key($it['language']??'id'),
                'categories'=>array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($it['categories']??[]))))),
                'tags'=>array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($it['tags']??[]))))),
                'labels'=>array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($it['labels']??[]))))),
                'status'=>'queued','stage'=>'ARTICLE','percent'=>0,'message'=>'Menunggu giliran.'
            ]; }
            if(empty($clean)) wp_send_json_error(['message'=>'Tujuan Multi Website tidak valid.']);
            $base['items']=$clean; $base['index']=0; $base['used_titles']=[];
        }
        $jobs=$this->bg_jobs(); $jobs[$job_id]=$base;
        // Keep the queue bounded without touching active jobs.
        if(count($jobs)>80){ uasort($jobs,function($a,$b){return (int)($a['updated']??0)<=> (int)($b['updated']??0);}); foreach($jobs as $k=>$v){ if(in_array($v['status']??'', ['queued','running'],true)) continue; unset($jobs[$k]); if(count($jobs)<=60) break; } }
        $this->bg_save_jobs($jobs); $this->bg_schedule($job_id,1);
        wp_send_json_success(['job_id'=>$job_id,'message'=>'Proses dimasukkan ke background.']);
    }
    // v1.3.237: Background mode is an explicit full-workflow request.
    // Single and Multi both continue automatically from thumbnail to publish.
    function start_background_publish() {
        if(!$this->auth(true)) return;
        $id=sanitize_text_field($_POST['job_id']??''); $jobs=$this->bg_jobs(); $job=$jobs[$id]??null;
        if(!$job || absint($job['user_id']??0)!==get_current_user_id()) wp_send_json_error(['message'=>'Background job tidak ditemukan.'],404);
        if(($job['mode']??'single')!=='single' || ($job['stage']??'')!=='READY_PUBLISH') wp_send_json_error(['message'=>'Job belum siap untuk publikasi.']);
        $job['stage']='PUBLISH'; $job['status']='queued'; $job['percent']=88; $job['message']='Publikasi dimasukkan ke background.'; $job['updated']=time(); $jobs[$id]=$job; $this->bg_save_jobs($jobs); $this->bg_schedule($id,1);
        wp_send_json_success(['job_id'=>$id,'message'=>'Publikasi dimasukkan ke background.']);
    }
    function background_status() {
        if(!$this->auth()) return;
        $id=sanitize_text_field($_POST['job_id']??''); $jobs=$this->bg_jobs(); $job=$jobs[$id]??null;
        if(!$job || absint($job['user_id']??0)!==get_current_user_id()) wp_send_json_error(['message'=>'Background job tidak ditemukan.'],404);
        wp_send_json_success($job);
    }
    function cancel_background() {
        if(!$this->auth()) return;
        $id=sanitize_text_field($_POST['job_id']??'');
        if($id==='') wp_send_json_error(['message'=>'ID background job kosong.'],400);
        $jobs=$this->bg_jobs(); $job=$jobs[$id]??null;
        if(!$job || absint($job['user_id']??0)!==get_current_user_id()) wp_send_json_error(['message'=>'Background job tidak ditemukan.'],404);
        if(in_array($job['status']??'', ['done','failed','cancelled'], true)) { wp_send_json_success($job); }
        $job['status']='cancelled'; $job['stage']='CANCELLED'; $job['message']='Proses dihentikan oleh pengguna.'; $job['cancelled_at']=time(); $job['updated']=time();
        $jobs[$id]=$job; $this->bg_save_jobs($jobs);
        if(function_exists('wp_clear_scheduled_hook')) { @wp_clear_scheduled_hook('jaf_background_worker', [$id]); }
        wp_send_json_success($job);
    }
    function background_execute() {
        $id=sanitize_text_field($_POST['job_id']??''); $uid=absint($_POST['user_id']??0); $sig=sanitize_text_field($_POST['signature']??'');
        if($id===''||!$uid||!hash_equals($this->bg_secret($id,$uid),$sig)) wp_send_json_error(['message'=>'Background signature tidak valid.'],403);
        $jobs=$this->bg_jobs(); $job=$jobs[$id]??null;
        if(!$job||absint($job['user_id']??0)!==$uid) wp_send_json_error(['message'=>'Background job tidak ditemukan.'],404);
        if(($job['status']??'')==='cancelled' || ($job['stage']??'')==='CANCELLED') { wp_send_json_success(['cancelled'=>true]); return; }
        $GLOBALS['jaf_background_context']=true; wp_set_current_user($uid);
        $stage=strtoupper(sanitize_text_field($job['stage']??'')); $input=$job['input']??[]; $item=$this->bg_current_item($job);
        $_POST=['nonce'=>'','job'=>$job['article_job_token']??'','material'=>$input['material']??'','type'=>$input['type']??'','location'=>$input['location']??'','language'=>$input['language']??'id','target'=>$input['target']??$job['target']??'',
            'blogger_profile'=>$input['blogger_profile']??'','wordpress_profile'=>$input['wordpress_profile']??'','categories'=>$input['categories']??[],'tags'=>$input['tags']??[],'labels'=>$input['labels']??[]];
        if($item){ $_POST['target']=$item['target']; $_POST['wordpress_profile']=$item['wordpress_profile']; $_POST['blogger_profile']=$item['blogger_profile']; $_POST['language']=$item['language']; $_POST['categories']=$item['categories']; $_POST['tags']=$item['tags']; $_POST['labels']=$item['labels']; $_POST['used_titles']=$job['used_titles']??[]; }
        if($stage==='ARTICLE'){ $this->generate_article(); return; }
        if($stage==='THUMBNAIL'){ $this->generate_thumbnail(); return; }
        if($stage==='PUBLISH'){
            $publish_target=$item['target']??$job['target']??'';
            if($publish_target==='blogger' && ($job['mode']??'single')==='single'){
                $result=$this->publish_blogger_internal($job['article_job_token']??'');
                if(is_wp_error($result)){
                    $ed=$result->get_error_data(); $ed=is_array($ed)?$ed:[];
                    $message=$result->get_error_message();
                    $this->bg_update($id,['status'=>'failed','stage'=>'FAILED','percent'=>min(99,max(88,absint($job['percent']??88))),'message'=>$message,'diagnostics'=>array_merge((array)($job['diagnostics']??[]),[[
                        'stage'=>'PUBLISH','http'=>(int)($ed['http_code']??0),'message'=>$message,'target'=>$ed['target']??''
                    ]])]);
                    wp_send_json_error(['stage'=>'PUBLISH','message'=>$message,'http'=>(int)($ed['http_code']??0),'target'=>(string)($ed['target']??'Profil Blogger')]);
                }
                $res=$job['result']??[]; $res['url']=esc_url_raw($result['url']??''); $res['post_id']=sanitize_text_field($result['post_id']??'');
                $this->bg_update($id,['status'=>'done','stage'=>'DONE','percent'=>100,'message'=>'Artikel berhasil dipublikasikan.','result'=>$res]);
                wp_send_json_success(['stage'=>'PUBLISH','message'=>'Artikel berhasil dipublikasikan.','url'=>$res['url'],'post_id'=>$res['post_id']]);
            }
            if($publish_target==='blogger') $this->blogger_publish(); else $this->apply_publish(); return;
        }
        wp_send_json_error(['stage'=>'JOB','message'=>'Tahap background tidak dikenal.']);
    }
    private function bg_next_multi_index($items,$from) {
        $items=is_array($items)?$items:[]; $from=absint($from);
        for($j=$from+1;$j<count($items);$j++) {
            $status=(string)($items[$j]['status']??'queued');
            if(!in_array($status,['success','failed'],true)) return $j;
        }
        return -1;
    }

    function retry_multi_item() {
        if(!$this->auth()) return;
        $id=sanitize_text_field($_POST['job_id']??''); $key=sanitize_text_field($_POST['key']??'');
        if($id===''||$key==='') wp_send_json_error(['message'=>'Job atau tujuan retry tidak valid.'],400);
        $jobs=$this->bg_jobs(); $job=$jobs[$id]??null;
        if(!$job || absint($job['user_id']??0)!==get_current_user_id()) wp_send_json_error(['message'=>'Background job tidak ditemukan.'],404);
        if(($job['mode']??'single')!=='multi') wp_send_json_error(['message'=>'Retry hanya tersedia untuk Multi Website.'],400);
        if(in_array(($job['status']??''),['running','queued'],true)) wp_send_json_error(['message'=>'Batch masih berjalan. Tunggu batch selesai terlebih dahulu.'],409);
        $found=-1;
        foreach((array)($job['items']??[]) as $i=>$it){ if((string)($it['key']??'')===$key){$found=(int)$i;break;} }
        if($found<0) wp_send_json_error(['message'=>'Tujuan yang ingin di-retry tidak ditemukan.'],404);
        $item=$job['items'][$found];
        if(($item['status']??'')!=='failed') wp_send_json_error(['message'=>'Tujuan tersebut tidak berstatus gagal.'],409);
        $retry_stage=strtoupper(sanitize_text_field($item['failed_stage']??'ARTICLE'));
        if(!in_array($retry_stage,['ARTICLE','THUMBNAIL','PUBLISH'],true)) $retry_stage='ARTICLE';
        $mw=class_exists('Japur_Master_Workflow_Settings') ? Japur_Master_Workflow_Settings::get_all() : [];
        if(array_key_exists('retry_enabled',$mw) && empty($mw['retry_enabled'])) wp_send_json_error(['message'=>'Retry sedang dinonaktifkan di Master Workflow.'],403);
        $stage_setting=['ARTICLE'=>'retry_article','THUMBNAIL'=>'retry_thumbnail','PUBLISH'=>'retry_publish'][$retry_stage]??'';
        if($stage_setting!=='' && array_key_exists($stage_setting,$mw) && empty($mw[$stage_setting])) wp_send_json_error(['stage'=>$retry_stage,'message'=>'Retry untuk tahap '.$retry_stage.' sedang dinonaktifkan di Master Workflow.'],403);
        $max_attempts=absint($mw['retry_max_attempts']??1); if(!in_array($max_attempts,[1,2,3],true)) $max_attempts=1;
        $attempts=absint($item['retry_count']??0);
        if($attempts >= $max_attempts) wp_send_json_error(['stage'=>$retry_stage,'message'=>'Batas Retry untuk tujuan ini sudah tercapai ('.$max_attempts.' kali).'],409);
        if($retry_stage==='ARTICLE'){
            $new_type=sanitize_key($_POST['type']??'');
            if(!in_array($new_type,['berita','panduan','pengalaman','opini','review'],true)) wp_send_json_error(['stage'=>'TYPE','message'=>'Pilih Jenis Artikel terlebih dahulu sebelum Retry Artikel.'],400);
            if(!isset($job['input'])||!is_array($job['input'])) $job['input']=[];
            $job['input']['type']=$new_type;
            if(isset($_POST['location'])) $job['input']['location']=sanitize_text_field($_POST['location']);
            if(isset($_POST['language'])) $job['input']['language']=sanitize_key($_POST['language']??'id');
        }
        $job['index']=$found; $job['stage']=$retry_stage; $job['status']='queued';
        $job['percent']=$retry_stage==='ARTICLE' ? round(($found/max(1,count($job['items'])))*100) : ($retry_stage==='THUMBNAIL' ? 52 : 82);
        $job['message']='Retry '.$item['name'].' dimasukkan ke antrean dari tahap '.$retry_stage.'.';
        $job['article_job_token']=$retry_stage==='ARTICLE' ? '' : sanitize_text_field($item['article_job_token']??'');
        $job['result']=$retry_stage==='ARTICLE' ? [] : (is_array($item['result']??null)?$item['result']:[]);
        if($retry_stage!=='ARTICLE' && $job['article_job_token']==='') {
            wp_send_json_error(['message'=>'Data hasil tahap sebelumnya untuk retry '.$retry_stage.' tidak tersedia. Jalankan ulang dari tahap Artikel.'],409);
        }
        $job['items'][$found]['status']='queued'; $job['items'][$found]['stage']=$retry_stage; $job['items'][$found]['percent']=$retry_stage==='ARTICLE'?0:($retry_stage==='THUMBNAIL'?52:82); $job['items'][$found]['message']='Menunggu retry tahap '.$retry_stage.'.';
        $job['items'][$found]['retry_count']=absint($job['items'][$found]['retry_count']??0)+1;
        $jobs[$id]=$job; $this->bg_save_jobs($jobs); $this->bg_schedule($id,1);
        wp_send_json_success(['job_id'=>$id,'message'=>'Retry dimasukkan ke antrean untuk '.$item['name'].'.']);
    }

    function background_worker($job_id) {
        $jobs=$this->bg_jobs(); $job=$jobs[$job_id]??null; if(!$job||in_array($job['status']??'', ['done','failed','cancelled'],true)) return;
        $uid=absint($job['user_id']??0); if(!$uid) return;
        $this->bg_update($job_id,['status'=>'running','message'=>'Worker menjalankan tahap '.($job['stage']??'ARTICLE').'.']);
        $url=admin_url('admin-ajax.php');
        $args=['timeout'=>110,'redirection'=>0,'blocking'=>true,'body'=>['action'=>'jaf_background_execute','job_id'=>$job_id,'user_id'=>$uid,'signature'=>$this->bg_secret($job_id,$uid)]];
        $r=wp_remote_post($url,$args); $json=$this->bg_response_json($r);
        $latest_jobs=$this->bg_jobs(); $latest=$latest_jobs[$job_id]??null;
        if($latest && (($latest['status']??'')==='cancelled' || ($latest['stage']??'')==='CANCELLED')) return;
        // Jika request loopback kehilangan body/terputus setelah publisher sudah
        // menyimpan status final, jangan menimpa hasil sukses dengan false failure.
        if(empty($json['success']) && $latest && in_array(($latest['status']??''),['done','failed'],true)) return;
        if(empty($json['success'])){
            $data=is_array($json['data']??null)?$json['data']:[];
            if(($job['mode']??'single')==='multi'){
                $jobs=$this->bg_jobs(); $cur=$jobs[$job_id]??$job; $i=absint($cur['index']??0); $msg=$data['message']??'Proses gagal.'; $failed_stage=strtoupper((string)($cur['stage']??'UNKNOWN')); $diag=['target'=>$cur['items'][$i]['name']??'Website','stage'=>$failed_stage,'http'=>(int)($data['http']??wp_remote_retrieve_response_code($r)),'message'=>$msg];
                if(isset($cur['items'][$i])){ $cur['items'][$i]['status']='failed'; $cur['items'][$i]['stage']='FAILED'; $cur['items'][$i]['failed_stage']=$failed_stage; $cur['items'][$i]['percent']=0; $cur['items'][$i]['message']=$msg; $cur['items'][$i]['diagnostic']=$diag; if($failed_stage==='ARTICLE'){ $cur['items'][$i]['article_job_token']=''; $cur['items'][$i]['result']=[]; } else { $cur['items'][$i]['article_job_token']=sanitize_text_field($cur['article_job_token']??''); $cur['items'][$i]['result']=is_array($cur['result']??null)?$cur['result']:[]; } }
                $cur['diagnostics']=array_merge((array)($cur['diagnostics']??[]),[$diag]);
                $next=$this->bg_next_multi_index($cur['items'],$i); if($next>=0){ $cur['index']=$next; $cur['stage']='ARTICLE'; $cur['percent']=round(($next/count($cur['items']))*100); $cur['message']='Satu website gagal. Melanjutkan website berikutnya.'; $cur['article_job_token']=''; $cur['result']=[]; $cur['status']='running'; $jobs[$job_id]=$cur; $this->bg_save_jobs($jobs); $this->bg_schedule($job_id,1); return; }
                $ok=0;$fail=0;foreach($cur['items'] as $it){if(($it['status']??'')==='success')$ok++;elseif(($it['status']??'')==='failed')$fail++;} $cur['status']='done';$cur['stage']='DONE';$cur['percent']=100;$cur['message']=$ok.' website berhasil dipublikasikan'.($fail?' • '.$fail.' gagal.':' .');$cur['result']=['ok'=>$ok,'failed'=>$fail,'items'=>$cur['items']];$jobs[$job_id]=$cur;$this->bg_save_jobs($jobs);return;
            }
            $this->bg_update($job_id,['status'=>'failed','percent'=>min(99,absint($job['percent']??0)),'message'=>$data['message']??'Background worker gagal.','diagnostics'=>array_merge((array)($job['diagnostics']??[]),[[ 'stage'=>$job['stage']??'UNKNOWN','http'=>(int)($data['http']??wp_remote_retrieve_response_code($r)),'message'=>$data['message']??'Background worker gagal.','target'=>$data['target']??'']])]); return;
        }
        $data=is_array($json['data']??null)?$json['data']:[]; $stage=$job['stage']??'ARTICLE';
        if($stage==='ARTICLE'){
            $patch=['article_job_token'=>sanitize_text_field($data['job']??''),'stage'=>'THUMBNAIL','percent'=>52,'message'=>'Artikel selesai. Membuat thumbnail.'];
            if(!empty($data['fields'])) $patch['result']=['fields'=>$data['fields'],'payload'=>$data['payload']??''];
            if(($job['mode']??'single')==='multi'){ $jobs=$this->bg_jobs(); $cur=$jobs[$job_id]??$job; $i=absint($cur['index']??0); if(isset($cur['items'][$i])){ $cur['items'][$i]['article_job_token']=$patch['article_job_token']; $cur['items'][$i]['result']=$patch['result']??(is_array($cur['result']??null)?$cur['result']:[]); $cur['items'][$i]['stage']='THUMBNAIL'; $cur['items'][$i]['message']='Artikel selesai. Membuat thumbnail.'; } $jobs[$job_id]=array_merge($cur,$patch,['updated'=>time()]); $this->bg_save_jobs($jobs); $this->bg_schedule($job_id,1); return; }
            $this->bg_update($job_id,$patch); $this->bg_schedule($job_id,1); return;
        }
        if($stage==='THUMBNAIL'){
            $res=$job['result']??[]; $res['image_url']=esc_url_raw($data['image_url']??'');
            if(($job['mode']??'single')==='multi'){ $jobs=$this->bg_jobs(); $cur=$jobs[$job_id]??$job; $i=absint($cur['index']??0); if(isset($cur['items'][$i])){ $cur['items'][$i]['article_job_token']=sanitize_text_field($cur['article_job_token']??''); $cur['items'][$i]['result']=$res; $cur['items'][$i]['stage']='PUBLISH'; $cur['items'][$i]['message']='Thumbnail selesai. Menerbitkan artikel.'; } $cur['result']=$res; $cur['stage']='PUBLISH'; $cur['percent']=82; $cur['message']='Thumbnail selesai. Menerbitkan artikel.'; $jobs[$job_id]=$cur; $this->bg_save_jobs($jobs); $this->bg_schedule($job_id,1); return; }
            if(($job['mode']??'single')==='single' && empty($job['auto_publish'])){
                $this->bg_update($job_id,['result'=>$res,'stage'=>'READY_PUBLISH','status'=>'ready_publish','percent'=>86,'message'=>'Artikel dan thumbnail selesai. Siap dipublikasikan.']); return;
            }
            $this->bg_update($job_id,['result'=>$res,'stage'=>'PUBLISH','percent'=>82,'message'=>'Thumbnail selesai. Menerbitkan artikel.']); $this->bg_schedule($job_id,1); return;
        }
        if($stage==='PUBLISH'){
            $res=$job['result']??[]; $res['url']=esc_url_raw($data['url']??''); $res['post_id']=sanitize_text_field($data['post_id']??'');
            if(($job['mode']??'single')==='multi'){
                $jobs=$this->bg_jobs(); $cur=$jobs[$job_id]??$job; $i=absint($cur['index']??0); if(isset($cur['items'][$i])){ $cur['items'][$i]['status']='success'; $cur['items'][$i]['stage']='DONE'; $cur['items'][$i]['percent']=100; $cur['items'][$i]['message']='Berhasil dipublikasikan.'; $cur['items'][$i]['url']=$res['url']; $title=$res['fields']['title']??($res['article_title']??''); if(isset($cur['result']['fields']['title'])) $title=$cur['result']['fields']['title']; if($title!=='') $cur['used_titles'][]=$title; }
                $next=$this->bg_next_multi_index($cur['items'],$i); if($next>=0){ $cur['index']=$next; $cur['stage']='ARTICLE'; $cur['percent']=round(($next/count($cur['items']))*100); $cur['message']='Melanjutkan website berikutnya.'; $cur['article_job_token']=''; $cur['result']=[]; $jobs[$job_id]=$cur; $this->bg_save_jobs($jobs); $this->bg_schedule($job_id,1); return; }
                $ok=0; $fail=0; foreach($cur['items'] as $it){ if(($it['status']??'')==='success')$ok++; if(($it['status']??'')==='failed')$fail++; } $cur['status']='done'; $cur['stage']='DONE'; $cur['percent']=100; $cur['message']=$ok.' website berhasil dipublikasikan'.($fail?' • '.$fail.' gagal.':' .'); $cur['result']=['ok'=>$ok,'failed'=>$fail,'items'=>$cur['items']]; $jobs[$job_id]=$cur; $this->bg_save_jobs($jobs); return;
            }
            $this->bg_update($job_id,['status'=>'done','stage'=>'DONE','percent'=>100,'message'=>'Artikel berhasil dipublikasikan.','result'=>$res]); return;
        }
    }

    function preflight_check() {
        if(!$this->auth()) return;
        $target=sanitize_key($_POST['target']??'');
        $type=sanitize_key($_POST['type']??'');
        $checks=[]; $ready=true;
        if(!in_array($type,['berita','panduan','pengalaman','opini','review'],true)){
            $checks[]=$this->preflight_check_item('article_type','Jenis Artikel',false,'Jenis Artikel belum dipilih. Pilih jenis artikel terlebih dahulu.');
            $ready=false;
        } else {
            $checks[]=$this->preflight_check_item('article_type','Jenis Artikel',true,'Jenis Artikel siap diproses: '.$type,'','warning');
        }
        $api_key=(string)($this->o['api_key']??'');
        $text_model=(string)($this->o['text_model']??''); $image_model=(string)($this->o['image_model']??'');
        if($api_key===''){ $checks[]=$this->preflight_check_item('openai_key','OpenAI API Key',false,'API Key belum diisi.'); $ready=false; }
        else $checks[]=$this->preflight_check_item('openai_key','OpenAI API Key',true,'API Key tersedia.','', 'warning');
        $r=$this->preflight_openai_model($text_model);
        if(is_wp_error($r)){ $checks[]=$this->preflight_check_item('text_model','Model Artikel',false,$r->get_error_message(),is_array($r->get_error_data())?absint($r->get_error_data()['http_code']??0):0); $ready=false; }
        else $checks[]=$this->preflight_check_item('text_model','Model Artikel',true,'Model dapat diakses: '.$text_model,(int)$r['http'],'warning');
        $r=$this->preflight_openai_model($image_model);
        if(is_wp_error($r)){ $checks[]=$this->preflight_check_item('image_model','Model Gambar',false,$r->get_error_message(),is_array($r->get_error_data())?absint($r->get_error_data()['http_code']??0):0); $ready=false; }
        else $checks[]=$this->preflight_check_item('image_model','Model Gambar',true,'Model dapat diakses: '.$image_model,(int)$r['http'],'warning');

        $selected=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['selected_targets']??[])))));
        $targets=[];
        if($target==='multi') {
            if(empty($selected)){ $checks[]=$this->preflight_check_item('targets','Website Tujuan',false,'Belum ada website/blog yang dipilih.'); $ready=false; }
            else {
                $wp=$this->wordpress_profiles(); $bp=$this->blogger_profiles();
                foreach($selected as $key){
                    $parts=explode(':',$key,2); $kind=$parts[0]??''; $id=$parts[1]??'';
                    if($kind==='wordpress' && ($id==='' || $id==='local')) {
                        $local_host=(string)wp_parse_url(home_url('/'),PHP_URL_HOST); $local_host=preg_replace('/^www\./i','',$local_host); $local_name=$local_host!==''?$local_host:get_bloginfo('name');
                        $targets[]=['key'=>'wordpress:local','kind'=>'wordpress','id'=>'','name'=>$local_name];
                    } else {
                        $targets[]=['key'=>$key,'kind'=>$kind,'id'=>$id,'name'=>$kind==='wordpress'?(string)($wp[$id]['name']??'Website WordPress'):(string)($bp[$id]['name']??'Profil Blogger')];
                    }
                }
            }
        } elseif($target==='wordpress') {
            $id=sanitize_key($_POST['wordpress_profile']??''); $targets[]=['key'=>$id!==''?'wordpress:'.$id:'wordpress:local','kind'=>'wordpress','id'=>$id,'name'=>$id!==''?(string)($this->wordpress_profiles()[$id]['name']??'Website WordPress'):get_bloginfo('name')];
        } elseif($target==='blogger') {
            $id=sanitize_key($_POST['blogger_profile']??''); $targets[]=['key'=>'blogger:'.$id,'kind'=>'blogger','id'=>$id,'name'=>(string)($this->blogger_profiles()[$id]['name']??'Profil Blogger')];
        } else { $checks[]=$this->preflight_check_item('target','Tujuan Publikasi',false,'Tujuan publikasi belum dipilih.'); $ready=false; }

        foreach($targets as $t){
            if($t['kind']==='wordpress'){
                if($t['id']===''){
                    $ok=current_user_can('publish_posts') && current_user_can('upload_files');
                    $checks[]=$this->preflight_check_item('wp-local','WordPress Website Ini',$ok,$ok?'Hak publish dan upload media tersedia.':'Akun pengguna tidak memiliki hak publish/upload media.');
                    if(!$ok)$ready=false;
                } else {
                    $profiles=$this->wordpress_profiles(); $p=$profiles[$t['id']]??null;
                    if(!$p){$checks[]=$this->preflight_check_item('wp-'.$t['id'],$t['name'],false,'Profil WordPress tidak ditemukan.');$ready=false;continue;}
                    if(empty($p['site_url'])||empty($p['username'])||empty($p['app_password'])){$checks[]=$this->preflight_check_item('wp-'.$t['id'],$t['name'],false,'Profil belum lengkap: URL, Username, atau Application Password kosong.');$ready=false;continue;}
                    if(!wp_http_validate_url($p['site_url'])){$checks[]=$this->preflight_check_item('wp-'.$t['id'],$t['name'],false,'URL website WordPress tidak valid.');$ready=false;continue;}
                    $r=$this->remote_wp_request($p,'GET','wp/v2/users/me');
                    if(is_wp_error($r)){$d=$this->preflight_remote_error($r,'PREFLIGHT',$t['name']);$checks[]=$this->preflight_check_item('wp-'.$t['id'],$t['name'],false,$d['message'],$d['http']);$ready=false;}
                    else {$caps=is_array($r['capabilities']??null)?$r['capabilities']:[]; $can_publish=array_key_exists('publish_posts',$caps)?!empty($caps['publish_posts']):true; $can_upload=array_key_exists('upload_files',$caps)?!empty($caps['upload_files']):true; $ok=$can_publish&&$can_upload; $checks[]=$this->preflight_check_item('wp-'.$t['id'],$t['name'],$ok,$ok?'REST API aktif dan kredensial diterima; hak publish/media terdeteksi.': 'REST API aktif tetapi hak publish atau upload media tidak tersedia.',200); if(!$ok)$ready=false;}
                }
            } elseif($t['kind']==='blogger'){
                $profiles=$this->blogger_profiles(); $p=$profiles[$t['id']]??null;
                if(!$p){$checks[]=$this->preflight_check_item('blogger-'.$t['id'],$t['name'],false,'Profil Blogger tidak ditemukan.');$ready=false;continue;}
                if(empty($p['blog_id'])){$checks[]=$this->preflight_check_item('blogger-'.$t['id'],$t['name'],false,'Blog ID belum diisi.');$ready=false;continue;}
                $access=$this->blogger_access_token();
                if(is_wp_error($access)){$checks[]=$this->preflight_check_item('blogger-'.$t['id'],$t['name'],false,$access->get_error_message());$ready=false;continue;}
                $url='https://www.googleapis.com/blogger/v3/blogs/'.rawurlencode($p['blog_id']); $r=$this->blogger_api_request('GET',$url,null);
                if(is_wp_error($r)){$checks[]=$this->preflight_check_item('blogger-'.$t['id'],$t['name'],false,$r->get_error_message());$ready=false;}
                else { $http=(int)$r['http']; $data=$r['data']; $ok=$http>=200&&$http<300; $msg=$ok?'Blogger API aktif, token valid, dan blog dapat diakses.':'Blogger API menolak akses (HTTP '.$http.').'; $checks[]=$this->preflight_check_item('blogger-'.$t['id'],$t['name'],$ok,$msg,$http); if(!$ok)$ready=false; }
            }
        }
        wp_send_json_success(['ready'=>$ready,'checks'=>$checks,'message'=>$ready?'Semua pemeriksaan yang diperlukan lolos. Siap membuat artikel dan thumbnail.':'Ada pemeriksaan yang belum lolos. Perbaiki terlebih dahulu sebelum menjalankan proses AI.']);
    }

    function test_wp_profile() {
        if(!$this->auth_settings()) return;
        $id=sanitize_key($_POST['profile']??'');
        $profiles=$this->wordpress_profiles();
        $p=$profiles[$id]??null;
        if(!$p) wp_send_json_error(['message'=>'Profil WordPress tidak ditemukan.']);

        // Uji nilai yang sedang ada di form. Jika Application Password kosong,
        // gunakan password terenkripsi yang sudah tersimpan di profil.
        $form_url=esc_url_raw(wp_unslash($_POST['site_url']??''));
        $form_user=sanitize_text_field(wp_unslash($_POST['username']??''));
        $form_pass=sanitize_text_field(wp_unslash($_POST['app_password']??''));
        if($form_url!=='') $p['site_url']=$form_url;
        if($form_user!=='') $p['username']=$form_user;
        if($form_pass!=='') $p['app_password']=$form_pass;

        if(empty($p['site_url']) || empty($p['username']) || empty($p['app_password'])) wp_send_json_error(['message'=>'Profil WordPress belum lengkap. Isi URL, Username, dan Application Password.']);
        if(!wp_http_validate_url($p['site_url'])) wp_send_json_error(['message'=>'URL website WordPress tidak valid.']);
        $r=$this->remote_wp_request($p,'GET','wp/v2/users/me');
        if(is_wp_error($r)) wp_send_json_error(['message'=>$r->get_error_message()]);
        wp_send_json_success(['message'=>'Koneksi WordPress berhasil.']);
    }

    function test_api() {
        if(!$this->auth_settings()) return;
        if(empty($this->o['api_key'])) wp_send_json_error(['message'=>'API Key belum diisi.']);
        $r=(new JAF_OpenAI($this->o['api_key']))->test($this->o['text_model']);
        if(is_wp_error($r)) wp_send_json_error(['message'=>$r->get_error_message()]);
        wp_send_json_success(['message'=>'OpenAI API OK.']);
    }

    function extract_url() {
        if(!$this->auth()) return;
        $url=esc_url_raw(wp_unslash($_POST['url']??''));
        if(!$url || !wp_http_validate_url($url)) wp_send_json_error(['stage'=>'EXTRACT','message'=>'URL tidak valid.']);
        $r=JAF_Extractor::extract($url);
        if(is_wp_error($r)) wp_send_json_error(['stage'=>'EXTRACT','message'=>$r->get_error_message()]);
        wp_send_json_success(['material'=>$r['material'],'url'=>$r['url'],'length'=>$r['length'],'paragraphs'=>$r['paragraphs']??0,'words'=>$r['words']??0,'mode'=>$r['mode']??'full_article']);
    }

    function extract_material() {
        if(!$this->auth()) return;
        $material=trim(wp_unslash($_POST['material']??''));
        if(mb_strlen($material)<30) wp_send_json_error(['stage'=>'EXTRACT','message'=>'Materi terlalu pendek untuk diekstrak.']);
        $r=JAF_Extractor::extract_text($material);
        if(is_wp_error($r)) wp_send_json_error(['stage'=>'EXTRACT','message'=>$r->get_error_message()]);
        wp_send_json_success(['material'=>$r['material'],'length'=>$r['length'],'paragraphs'=>$r['paragraphs']??0,'words'=>$r['words']??0,'mode'=>$r['mode']??'text']);
    }

    function generate_article() {
        if(!$this->auth()) return;
        if(empty($this->o['api_key'])) wp_send_json_error(['stage'=>'API','message'=>'OpenAI API Key belum diisi.']);
        $material=trim(wp_unslash($_POST['material']??''));
        if(mb_strlen($material)<30) wp_send_json_error(['stage'=>'MATERI','message'=>'Materi terlalu pendek.']);
        $type=sanitize_key($_POST['type']??'');
        if(!in_array($type,['berita','panduan','pengalaman','opini','review'],true)) wp_send_json_error(['stage'=>'TYPE','message'=>'Pilih jenis Artikel terlebih dahulu.']);
        $loc=sanitize_text_field($_POST['location']??'');
        $profile_id=sanitize_key($_POST['blogger_profile']??'');
        $profile=$this->get_blogger_profile($profile_id);
        $allowed_blogger_labels=$profile ? $this->profile_labels($profile) : [];
        $category_names=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['categories']??[])))));
        $selected_tags=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['tags']??[])))));
        $selected_labels=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['labels']??[])),function($label) use ($allowed_blogger_labels){ return in_array($label,$allowed_blogger_labels,true); })));
        $category_text=implode(', ',$category_names);
        $tag_text=implode(', ',$selected_tags);
        $label_text=implode(', ',$selected_labels);
        $target=sanitize_key($_POST['target']??'');
        if(!in_array($target,['wordpress','blogger','multi'],true)) wp_send_json_error(['stage'=>'ARTIKEL','message'=>'Tujuan Publish tidak valid. Pilih WordPress, Blogger, atau Wordpress + Blogger.']);
        // Pada Multi Website, penanda Web Adsense hanya digunakan sebagai filter daftar tujuan.
        if($target==='blogger'){
            $profile_id=sanitize_key($_POST['blogger_profile']??'');
            $profile=$this->get_blogger_profile($profile_id);
            $allowed_blogger_labels=$profile ? $this->profile_labels($profile) : [];
            $selected_labels=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['labels']??[])),function($label) use ($allowed_blogger_labels){ return in_array($label,$allowed_blogger_labels,true); })));
            $label_text=implode(', ',$selected_labels);
        }
        $wordpress_profile_id=sanitize_key($_POST['wordpress_profile']??'');
        $wordpress_profile=$wordpress_profile_id!=='' ? ($this->wordpress_profiles()[$wordpress_profile_id]??null) : null;
        if($target==='wordpress' && $wordpress_profile_id!=='' && !$wordpress_profile) wp_send_json_error(['stage'=>'ARTIKEL','message'=>'Profil WordPress tidak ditemukan. Buka tab WordPress dan periksa profil tujuan.']);
        if($target==='blogger' && $label_text==='') wp_send_json_error(['stage'=>'ARTIKEL','message'=>'Label Blogger wajib dipilih. Buka tab Blogger dan isi daftar Label Blogger terlebih dahulu.']);
        if($target!=='blogger' && $category_text==='') wp_send_json_error(['stage'=>'ARTIKEL','message'=>'Kategori WordPress wajib dipilih.']);
        $target_url=$target==='blogger' ? ($profile['blog_url']??'') : ($wordpress_profile ? ($wordpress_profile['site_url']??'') : home_url('/'));
        if($target==='wordpress' && $wordpress_profile && (empty($wordpress_profile['username']) || empty($wordpress_profile['app_password'])) ) wp_send_json_error(['stage'=>'ARTIKEL','message'=>'Profil WordPress belum memiliki Username dan Application Password. Buka tab WordPress untuk melengkapinya.']);
        $target_domain=wp_parse_url($target_url,PHP_URL_HOST);
        if(!$target_domain) $target_domain=wp_parse_url(home_url(),PHP_URL_HOST);
        // Blogger lead selalu memakai domain profil tanpa protocol, www, port, atau slash.
        $target_domain=strtolower(trim((string)$target_domain));
        $target_domain=preg_replace('/^www\./i','',$target_domain);
        $target_domain=preg_replace('/:\d+$/','',$target_domain);
        // Teks domain pada Lead memakai huruf awal kapital; URL href tetap lowercase.
        $target_display_domain=$target_domain!=='' ? ucfirst($target_domain) : '';
        if($target==='blogger' && !$profile) wp_send_json_error(['stage'=>'ARTIKEL','message'=>'Profil Blogger belum dipilih atau tidak ditemukan.']);
        if($target==='blogger'){
            $master=$this->o['blogger_master_prompt']??JAF_Prompt::default_blogger_prompt();
            $profile_prompt=trim((string)($profile['prompt']??''));
            $base_prompt=$master;
            if($profile_prompt!=='' && trim($profile_prompt)!==trim(JAF_Prompt::default_blogger_prompt())) $base_prompt.="\n\nPROMPT KHUSUS PROFIL BLOGGER (SUPPLEMENT):\n".$profile_prompt;
        } else {
            $base_prompt=$this->o['master_prompt']??JAF_Prompt::default_prompt();
        }
        $language=sanitize_key($_POST['language']??'id');
        if(!in_array($language,['id','en'],true)) $language='id';
        $used_titles=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['used_titles']??[])))));
        $title_guard=implode("\n",$used_titles);
        $cost_settings=get_option('japur_openai_cost_settings',[]);
        $cost_config = class_exists('Japur_OpenAI_Cost') ? Japur_OpenAI_Cost::mode_config($cost_settings) : ['mode'=>(!empty($cost_settings['cost_saver_enabled']) ? 'hemat' : 'standard'),'max_output_tokens'=>(!empty($cost_settings['cost_saver_enabled']) ? 3000 : 7000),'image_quality'=>(!empty($cost_settings['cost_saver_enabled']) ? 'low' : 'auto'),'word_min'=>(!empty($cost_settings['cost_saver_enabled']) ? 300 : 500),'word_max'=>(!empty($cost_settings['cost_saver_enabled']) ? 400 : 700)];
        $max_output_tokens = (int)$cost_config['max_output_tokens'];
        $word_min = (int)($cost_config['word_min'] ?? 300);
        $word_max = (int)($cost_config['word_max'] ?? 400);
        $prompt=JAF_Prompt::build($base_prompt,$material,$type,$loc,$target==='blogger'?$label_text:$category_text,$target==='blogger'?'':$tag_text,$target_domain,$language,$title_guard,$word_min,$word_max);
        $a=(new JAF_OpenAI($this->o['api_key']))->article($prompt,$this->o['text_model'],$max_output_tokens);
        if(is_wp_error($a)) wp_send_json_error(['stage'=>'ARTIKEL','message'=>$a->get_error_message()]);
        $d=$a['data'];
        // Multi Website: jika model tetap mengulang judul yang sudah dipakai, regenerasi JUDUL saja.
        if(!empty($used_titles) && !empty($d['title'])){
            $norm=function($v){ $v=wp_strip_all_tags((string)$v); $v=preg_replace('/\s+/u',' ',trim($v)); return function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v); };
            $current_norm=$norm($d['title']);
            $used_norm=array_map($norm,$used_titles);
            if(in_array($current_norm,$used_norm,true)){
                $title_ok=false;
                for($title_attempt=1;$title_attempt<=2;$title_attempt++){
                    $title_prompt="Buat SATU judul artikel baru yang berbeda dari semua judul terlarang berikut. Jangan mengubah fakta. Judul harus sesuai isi artikel, SEO natural, tidak clickbait, maksimal 15 kata, dan menggunakan bahasa yang sama dengan artikel. Percobaan ke-".$title_attempt.". Jangan gunakan ulang judul yang sama, termasuk dengan perubahan tanda baca kecil.\n\nJUDUL TERLARANG:\n".implode("\n",$used_titles)."\n\nISI ARTIKEL:\n".$d['content_html'];
                    $tr=(new JAF_OpenAI($this->o['api_key']))->title($title_prompt,$this->o['text_model'],120);
                    if(!is_wp_error($tr) && !empty($tr['data']['title'])){
                        $new_title=sanitize_text_field($tr['data']['title']);
                        if($new_title!=='' && !in_array($norm($new_title),$used_norm,true)){
                            $d['title']=$new_title;
                            $title_ok=true;
                            do_action('japur_openai_cost_event', [
                                'kind'=>'article_title','model'=>(string)$this->o['text_model'],'usage'=>is_array($tr['usage']??null)?$tr['usage']:[],
                                'response_id'=>(string)($tr['response_id']??''),'article_title'=>$new_title,
                                'article_content'=>'','user_id'=>get_current_user_id(),'target'=>$target??'wordpress'
                            ]);
                            break;
                        }
                    }
                }
                // Fallback deterministik: tetap unik tanpa membuat ulang artikel.
                if(!$title_ok){
                    $suffix=$target_domain!=='' ? ucfirst($target_domain) : 'Website Tujuan';
                    $fallback=sanitize_text_field($d['title']).' — '.$suffix;
                    if(in_array($norm($fallback),$used_norm,true)) $fallback=sanitize_text_field($d['title']).' — '.$suffix.' '.substr(wp_generate_uuid4(),0,6);
                    $d['title']=$fallback;
                }
            }
        }
        do_action('japur_openai_cost_event', [
            'kind'=>'article','model'=>(string)$this->o['text_model'],'usage'=>is_array($a['usage']??null)?$a['usage']:[],
            'response_id'=>(string)($a['response_id']??''),'article_title'=>(string)($d['title']??''),
            'article_content'=>(string)($d['content_html']??''),
            'user_id'=>get_current_user_id(),'target'=>$target??'wordpress'
        ]);
        $d['content_html']=html_entity_decode($d['content_html'],ENT_QUOTES|ENT_HTML5,'UTF-8');
        $d['content_html']=wp_kses($d['content_html'],['h2'=>[],'h3'=>[],'p'=>[],'strong'=>[],'ul'=>[],'ol'=>[],'li'=>[],'blockquote'=>[],'a'=>['href'=>true,'target'=>true,'rel'=>true]]);
        // Blogger Lead: kapitalisasi lokasi dibuat natural (bukan ALL CAPS) tanpa mengubah fakta.
        // Normalisasi final dilakukan setelah guard domain/link agar tetap bekerja meskipun model
        // menghasilkan struktur lead yang sedikit berbeda.
        $lead_location='';
        if($target==='blogger' && $loc!=='') $lead_location=$this->normalize_lead_location($loc);
        // Guard terakhir: paksa nama domain pada awal lead Blogger mengikuti profil yang dipilih.
        // Hanya elemen pertama pada lead yang dinormalisasi agar URL/link lain di artikel tidak tersentuh.
        if($target==='blogger' && $target_domain!==''){
            $lead_domain=preg_quote($target_domain,'/');
            $d['content_html']=preg_replace(
                '/(<p\b[^>]*>\s*<strong>)(?:https?:\/\/)?(?:www\.)?'.$lead_domain.'(?:\/)?(<\/strong>\s*-)/i',
                '$1'.$target_display_domain.'$2',
                $d['content_html'],
                1
            );
            // Jika model menambahkan www/protocol pada nama domain pertama, koreksi juga berdasarkan
            // pola domain umum, tetapi hanya sebelum pemisah lead " - ".
            $d['content_html']=preg_replace_callback(
                '/(<p\b[^>]*>\s*<strong>)([^<]+)(<\/strong>\s*-)/i',
                function($m) use ($target_domain,$target_display_domain){
                    $candidate=trim($m[2]);
                    $normalized=preg_replace('/^https?:\/\//i','',$candidate);
                    $normalized=preg_replace('/^www\./i','',$normalized);
                    $normalized=rtrim($normalized,'/');
                    if(preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?:\.[a-z]{2,})$/i',$normalized)){
                        return $m[1].$target_display_domain.$m[3];
                    }
                    return $m[0];
                },
                $d['content_html'],
                1
            );
            // SEO guard: domain pada awal lead WAJIB menjadi tautan HTTPS ke root domain profil Blogger.
            // Normalisasi dulu jika model menulis URL/domain dalam tag <strong> atau <a>.
            $canonical_url='https://'.$target_domain;
            $d['content_html']=preg_replace(
                '/(<p\b[^>]*>\s*)<strong>(?:<a\b[^>]*>)?(?:https?:\/\/)?(?:www\.)?'.preg_quote($target_domain,'/').'\/?(?:<\/a>)?<\/strong>(\s*-)/i',
                '$1<strong><a href="'.esc_url($canonical_url).'">'.$target_display_domain.'</a></strong>$2',
                $d['content_html'],
                1
            );
            // Fallback: jika model memakai domain biasa tanpa strong/tautan, jadikan elemen lead pertama sebagai tautan.
            $d['content_html']=preg_replace_callback(
                '/(<p\b[^>]*>\s*)(?:<strong>)?((?:https?:\/\/)?(?:www\.)?'.preg_quote($target_domain,'/').'\/?)(?:<\/strong>)?(\s*-)/i',
                function($m) use ($target_domain,$target_display_domain,$canonical_url){
                    return $m[1].'<strong><a href="'.esc_url($canonical_url).'">'.$target_display_domain.'</a></strong>'.$m[3];
                },
                $d['content_html'],
                1
            );

            // Final SEO guard: setelah domain dipastikan menjadi link aktif, pastikan lokasi pada
            // lead juga menggunakan kapitalisasi natural. Tidak menambahkan lokasi baru.
            if($lead_location!=='') {
                $d['content_html']=preg_replace_callback(
                    '/(<p\b[^>]*>\s*<strong><a\b[^>]*>.*?<\/a><\/strong>\s*-\s*<strong>)([^<]*)(<\/strong>)/is',
                    function($m) use ($lead_location){ return $m[1].esc_html($lead_location).$m[3]; },
                    $d['content_html'],
                    1
                );
            }
        }
        $token=wp_generate_uuid4();
        $d['_selected_category_names']=$category_names;
        $d['_selected_tag_names']=$selected_tags;
        $d['_selected_blogger_labels']=$selected_labels;
        $state=['fields'=>$d,'payload'=>$this->format_payload($d,$target==='blogger'?$label_text:$category_text,$target==='blogger'?'':$tag_text),'attachment_id'=>0,'created'=>time(),'target'=>$target,'blogger_profile_id'=>$target==='blogger'?$profile_id:'','wordpress_profile_id'=>$target==='wordpress'?$wordpress_profile_id:'','selected_blogger_labels'=>$target==='blogger'?$selected_labels:[]];
        set_transient('jaf_v3_'.get_current_user_id().'_'.$token,$state,2*HOUR_IN_SECONDS);
        wp_send_json_success(['job'=>$token,'fields'=>$d,'payload'=>$state['payload']]);
    }

    function generate_thumbnail() {
        if(!$this->auth()) return;
        if(empty($this->o['api_key'])) wp_send_json_error(['stage'=>'API','message'=>'OpenAI API Key belum diisi.']);
        $token=sanitize_text_field($_POST['job']??'');
        $key='jaf_v3_'.get_current_user_id().'_'.$token;
        $state=get_transient($key);
        if(!is_array($state)) wp_send_json_error(['stage'=>'JOB','message'=>'Data artikel tidak ditemukan atau sudah kedaluwarsa.']);
        if(!empty($state['staged_file']) && file_exists($state['staged_file'])) wp_send_json_success(['image_url'=>$this->staged_preview_url($state['staged_file'])]);
        $d=$state['fields'];
        $prompt=trim(($this->o['image_prompt']??'')."\n\nTopik artikel: ".$d['title']."\nArahan visual: ".$d['image_prompt']);
        // Reuse the existing OpenAI Cost Mode Hemat switch. It now also lowers
        // image quality to 'low' while keeping the same 16:9 landscape size.
        $cost_settings=get_option('japur_openai_cost_settings',[]);
        $cost_config = class_exists('Japur_OpenAI_Cost') ? Japur_OpenAI_Cost::mode_config($cost_settings) : ['mode'=>(!empty($cost_settings['cost_saver_enabled']) ? 'hemat' : 'standard'),'max_output_tokens'=>(!empty($cost_settings['cost_saver_enabled']) ? 3000 : 7000),'image_quality'=>(!empty($cost_settings['cost_saver_enabled']) ? 'low' : 'auto')];
        $image_quality = $cost_config['image_quality'];
        $im=(new JAF_OpenAI($this->o['api_key']))->image($prompt,$this->o['image_model'],$image_quality);
        if(is_wp_error($im)) wp_send_json_error(['stage'=>'THUMBNAIL','message'=>$im->get_error_message()]);
        do_action('japur_openai_cost_event', [
            'kind'=>'image','model'=>(string)$this->o['image_model'],'usage'=>is_array($im['usage']??null)?$im['usage']:[],
            'response_id'=>(string)($im['response_id']??''),'article_title'=>(string)($d['title']??''),
            'user_id'=>get_current_user_id(),'target'=>$state['target']??'wordpress'
        ]);
        $staged=$this->stage_image($im,$d,$token);
        if(is_wp_error($staged)) wp_send_json_error(['stage'=>'STAGING','message'=>$staged->get_error_message()]);

        // Blogger memakai pipeline gambar khusus: watermark profil + WebP <= 50 KB.
        // WordPress tetap memakai alur gambar lama tanpa perubahan.
        if(($state['target']??'wordpress')==='blogger'){
            $profile=$this->get_blogger_profile(sanitize_key($state['blogger_profile_id']??''));
            $domain=$profile ? $this->normalize_blogger_domain($profile['blog_url']??'') : '';
            $processed=$this->process_blogger_thumbnail($staged,$d,$domain,$token);
            if(is_wp_error($processed)){ @unlink($staged); wp_send_json_error(['stage'=>'THUMBNAIL_OPTIMIZE','message'=>$processed->get_error_message()]); }
            @unlink($staged);
            $staged=$processed;
        }
        $state['staged_file']=$staged;
        $state['staged_mime']=($state['target']??'wordpress')==='blogger' ? 'image/webp' : 'image/png';
        set_transient($key,$state,2*HOUR_IN_SECONDS);
        wp_send_json_success(['image_url'=>$this->staged_preview_url($staged)]);
    }

    function apply_publish() {
        if(!$this->auth(true)) return;
        $token=sanitize_text_field($_POST['job']??'');
        $key='jaf_v3_'.get_current_user_id().'_'.$token;
        $state=get_transient($key);
        if(!is_array($state)) wp_send_json_error(['message'=>'Hasil artikel sudah kedaluwarsa. Buat artikel lagi.']);
        $d=$state['fields']; $attachment_id=absint($state['attachment_id']??0);
        $staged_file=sanitize_text_field($state['staged_file']??'');
        if(($state['target']??'wordpress')==='wordpress' && !empty($state['wordpress_profile_id'])) {
            $result=$this->publish_to_remote_wordpress($state,$d,$staged_file);
            if(is_wp_error($result)){ $ed=$result->get_error_data(); $ed=is_array($ed)?$ed:[]; wp_send_json_error(['stage'=>$ed['stage']??'PUBLISH','message'=>$result->get_error_message(),'http'=>(int)($ed['http_code']??0),'endpoint'=>(string)($ed['endpoint']??''),'target'=>(string)($this->wordpress_profiles()[$state['wordpress_profile_id']]['name']??'WordPress')]); }
            delete_transient($key);
            do_action('japur_article_published', [
                'event_key'=>'jaf:'.get_current_user_id().':'.$token.':wp_remote',
                'platform'=>'wordpress',
                'target_key'=>'wordpress:'.sanitize_key($state['wordpress_profile_id']??''),
                'target_name'=>(string)($this->wordpress_profiles()[$state['wordpress_profile_id']]['name']??'Website WordPress'),
                'article_title'=>(string)($d['title']??''),
                'post_id'=>(string)($result['post_id']??''),
                'article_url'=>(string)($result['url']??''),
                'user_id'=>get_current_user_id(),
            ]);
            wp_send_json_success($result);
        }
        if(!$attachment_id && (!$staged_file || !file_exists($staged_file))) wp_send_json_error(['message'=>'Thumbnail belum tersedia.']);
        $category_names=$state['fields']['_selected_category_names']??array_values(array_filter(array_map('sanitize_text_field',(array)($_POST['categories']??[]))));
        $category_names=array_values(array_unique(array_filter($category_names)));
        $categories=[];
        foreach($category_names as $category_name){
            $term=get_term_by('name',$category_name,'category');
            if(!$term){
                $created=wp_insert_term($category_name,'category');
                if(is_wp_error($created)) wp_send_json_error(['message'=>'Gagal menyiapkan kategori '.$category_name.': '.$created->get_error_message()]);
                $categories[]=(int)$created['term_id'];
            } else { $categories[]=(int)$term->term_id; }
        }
        $tags=$state['fields']['_selected_tag_names']??array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($_POST['tags']??[])),function($tag){ return in_array($tag,['Discover','Trending'],true); })));
        // Normalisasi defensif: state/POST dapat berbentuk string atau array.
        if(!is_array($categories)) $categories=(array)$categories;
        if(!is_array($tags)) $tags=array($tags);
        $categories=array_values(array_filter(array_map('absint',$categories)));
        $tags=array_values(array_unique(array_filter(array_map('sanitize_text_field',$tags))));
        if(empty($categories)) wp_send_json_error(['message'=>'Kategori wajib dipilih.']);
        $content=$this->html_to_blocks($d['content_html']);
        if($content==='') wp_send_json_error(['message'=>'Konten gagal dikonversi ke Gutenberg blocks.']);
        $post=[
            'post_title'=>sanitize_text_field($d['title']),
            'post_excerpt'=>sanitize_textarea_field(wp_strip_all_tags($d['description'])),
            'post_content'=>$content,
            'post_status'=>'publish',
            'post_type'=>'post',
            'post_author'=>get_current_user_id(),
            'post_category'=>$categories,
            'tags_input'=>$tags
        ];
        $post_id=wp_insert_post($post,true);
        if(is_wp_error($post_id)) wp_send_json_error(['message'=>'Gagal membuat post: '.$post_id->get_error_message()]);
        $focus=sanitize_text_field($d['focus_keyword']??'');
        if($focus!=='') update_post_meta($post_id,'_yoast_wpseo_focuskw',$focus);
        $desc=sanitize_textarea_field(wp_strip_all_tags($d['description']??''));
        if($desc!=='') update_post_meta($post_id,'_yoast_wpseo_metadesc',$desc);

        if(!$attachment_id){
            $attachment_id=$this->attach_staged_image($staged_file,$d,$post_id);
            if(is_wp_error($attachment_id)){ wp_delete_post($post_id,true); wp_send_json_error(['message'=>'Post dibatalkan karena thumbnail gagal disimpan: '.$attachment_id->get_error_message()]); }
        }

        // Sinkronkan attachment dengan post. Auto WebP + Watermark diproses
        // setelah post tersedia sehingga modul Suite dapat membaca post_id.
        // Jangan menimpa metadata attachment dari AI. Setelah attachment dikaitkan
        // ke post, Auto WebP + Watermark milik Japur Suite menjadi pemilik metadata
        // gambar berdasarkan aturan plugin tersebut.
        wp_update_post(['ID'=>$attachment_id,'post_parent'=>$post_id]);
        set_post_thumbnail($post_id,$attachment_id);
        clean_post_cache($post_id);
        delete_transient($key);
        do_action('japur_article_published', [
                'event_key'=>'jaf:'.get_current_user_id().':'.$token.':wp_local',
                'platform'=>'wordpress',
                'target_key'=>'local:jayapurnama',
                'target_name'=>get_bloginfo('name'),
                'article_title'=>(string)($d['title']??''),
                'post_id'=>(string)$post_id,
                'article_url'=>(string)get_permalink($post_id),
                'user_id'=>get_current_user_id(),
            ]);
        wp_send_json_success([
            'message'=>'Artikel berhasil diterapkan dan dipublish.',
            'post_id'=>$post_id,
            'url'=>get_permalink($post_id),
            'edit_url'=>get_edit_post_link($post_id,'')
        ]);
    }


    private function render_save_notice() {
        $saved=sanitize_key($_GET['saved']??'');
        $deleted=sanitize_key($_GET['deleted']??'');
        $connected=sanitize_key($_GET['connected']??'');
        $blogs_refreshed=sanitize_key($_GET['blogs_refreshed']??'');
        $message=''; $type='success';
        if($saved==='1'){
            $tab=sanitize_key($_GET['tab']??'');
            $message=$tab==='blogger'?'Profil Blogger berhasil disimpan.':($tab==='wordpress'?'Profil WordPress berhasil disimpan.':'Pengaturan berhasil disimpan.');
        } elseif($deleted!==''){
            if($deleted==='1') $message='Profil WordPress berhasil dihapus.';
            else { $message='Profil WordPress gagal dihapus.'; $type='error'; }
        } elseif($connected==='1'){
            $message='Google Blogger berhasil terhubung.';
        } elseif($blogs_refreshed==='1'){
            $message='Daftar blog berhasil diperbarui.';
        }
        if($message==='') return;
        echo '<div class="jaf-floating-notice is-'.esc_attr($type).'" data-jaf-toast="1" role="status" aria-live="polite"><span class="dashicons '.($type==='success'?'dashicons-yes-alt':'dashicons-warning').'" aria-hidden="true"></span><span class="jaf-toast-message">'.esc_html($message).'</span><button type="button" class="jaf-toast-close" aria-label="Tutup">&times;</button></div>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var n=document.querySelector(".jaf-floating-notice[data-jaf-toast=\"1\"]");if(n){if(document.body&&n.parentNode!==document.body)document.body.appendChild(n);var t=window.setTimeout(function(){n.classList.add("is-hiding");window.setTimeout(function(){if(n&&n.parentNode)n.remove();},350);},3200);var c=n.querySelector(".jaf-toast-close");if(c)c.addEventListener("click",function(){window.clearTimeout(t);n.remove();});}});</script>';
    }

    function save_blogger_profile() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        if(!check_admin_referer('jaf_group-options')) wp_die('Nonce tidak valid.');
        $id=sanitize_key(wp_unslash($_POST['profile']??''));
        if($id==='') wp_die('Profil Blogger tidak valid.');
        $o=$this->get_secure_options();
        $profiles=is_array($o['blogger_profiles']??null)?$o['blogger_profiles']:[];
        if(!isset($profiles[$id]) || !is_array($profiles[$id])) wp_die('Profil Blogger tidak ditemukan.');
        $incoming=is_array($_POST['jaf_options']['blogger_profiles'][$id]??null) ? wp_unslash($_POST['jaf_options']['blogger_profiles'][$id]) : [];
        $current=$profiles[$id];
        $current['name']=sanitize_text_field($incoming['name']??($current['name']??$id));
        $current['blog_id']=sanitize_text_field($incoming['blog_id']??($current['blog_id']??''));
        $current['blog_url']=esc_url_raw($incoming['blog_url']??($current['blog_url']??''));
        $current['labels']=sanitize_textarea_field($incoming['labels']??($current['labels']??''));
        $current['prompt']=wp_kses_post($incoming['prompt']??($current['prompt']??''));
        $current['adsense']=!empty($incoming['adsense']) ? 1 : 0;
        $lang=sanitize_key($incoming['language']??($current['language']??'id'));
        $current['language']=in_array($lang,['id','en'],true)?$lang:'id';
        $profiles[$id]=$current; $o['blogger_profiles']=$profiles;
        $this->save_secure_options($o);
        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=blogger&saved=1'));
        exit;
    }

    function save_wp_profile() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        $id=sanitize_key(wp_unslash($_POST['profile']??''));
        if($id==='') wp_die('Profil WordPress tidak valid.');
        $nonce=sanitize_text_field(wp_unslash($_POST['_wpnonce']??''));
        if(!wp_verify_nonce($nonce,'jaf_save_wp_profile_'.$id)) wp_die('Nonce tidak valid.');
        if(!isset($_POST['wp_profile']) || !is_array($_POST['wp_profile'])) wp_die('Data profil WordPress tidak diterima.');

        $o=$this->get_secure_options();
        $profiles=is_array($o['wordpress_profiles']??null)?$o['wordpress_profiles']:[];
        if(!isset($profiles[$id]) || !is_array($profiles[$id])) wp_die('Profil WordPress tidak ditemukan.');

        /*
         * Kategori & Tag adalah data konfigurasi milik PROFIL ini.
         * Jangan pernah membuat/mengubah taxonomy WordPress utama pada tahap ini.
         * Nilai textarea adalah source of truth saat tombol Simpan Profil ditekan.
         */
        $incoming=wp_unslash($_POST['wp_profile']);
        $current=$profiles[$id];

        $current['name']=sanitize_text_field($incoming['name']??($current['name']??$id));
        $current['site_url']=esc_url_raw($incoming['site_url']??($current['site_url']??''));
        $current['username']=sanitize_text_field($incoming['username']??($current['username']??''));
        $current['adsense']=!empty($incoming['adsense']) ? 1 : 0;
        $lang=sanitize_key($incoming['language']??($current['language']??'id'));
        $current['language']=in_array($lang,['id','en'],true)?$lang:'id';

        $pass=trim((string)($incoming['app_password']??''));
        if($pass!=='') $current['app_password']=sanitize_text_field($pass);
        elseif(array_key_exists('app_password',$current)) $current['app_password']=(string)$current['app_password'];

        $old_manual=is_array($current['taxonomy_manual']??null)?$current['taxonomy_manual']:[];
        $cat_raw=array_key_exists('taxonomy_categories',$incoming)
            ? (string)$incoming['taxonomy_categories']
            : implode(', ',(array)($old_manual['categories']??[]));
        $tag_raw=array_key_exists('taxonomy_tags',$incoming)
            ? (string)$incoming['taxonomy_tags']
            : implode(', ',(array)($old_manual['tags']??[]));

        $current['taxonomy_manual']=[
            'categories'=>$this->parse_manual_terms($cat_raw),
            'tags'=>$this->parse_manual_terms($tag_raw),
        ];
        $current['taxonomy_cache']=is_array($current['taxonomy_cache']??null)?$current['taxonomy_cache']:[];
        $current['taxonomy_selected']=is_array($current['taxonomy_selected']??null)?$current['taxonomy_selected']:[];

        $profiles[$id]=$current;
        $o['wordpress_profiles']=$profiles;

        /*
         * Simpan satu-satunya option plugin. Jalur Kelola Profil
         * hanya menulis option konfigurasi plugin, bukan taxonomy WordPress.
         */
        $write_ok=$this->save_secure_options($o);

        /*
         * update_option() dapat mengembalikan false ketika nilai dianggap
         * tidak berubah, jadi false BUKAN otomatis berarti gagal.
         * Verifikasi dilakukan terhadap RAW option agar tidak terpengaruh
         * decrypt/encrypt secret atau object-cache hasil pembacaan wrapper.
         */
        wp_cache_delete('jaf_options','options');
        $verify_raw=get_option('jaf_options',[]);
        if(!is_array($verify_raw)) $verify_raw=[];

        $saved_profiles=is_array($verify_raw['wordpress_profiles']??null)?$verify_raw['wordpress_profiles']:[];
        $saved_profile=is_array($saved_profiles[$id]??null)?$saved_profiles[$id]:[];
        $actual_categories=is_array($saved_profile['taxonomy_manual']['categories']??null)
            ? $saved_profile['taxonomy_manual']['categories'] : [];
        $actual_tags=is_array($saved_profile['taxonomy_manual']['tags']??null)
            ? $saved_profile['taxonomy_manual']['tags'] : [];

        /*
         * Jika object cache/hook menahan update, paksa satu kali melalui
         * $wpdb. Ini tetap hanya menulis option jaf_options milik plugin.
         */
        $expected_categories=$current['taxonomy_manual']['categories'];
        $expected_tags=$current['taxonomy_manual']['tags'];
        $canonicalize=function($items){
            $items=is_array($items)?$items:[];
            $out=[];
            foreach($items as $item){
                $item=sanitize_text_field(trim((string)$item));
                if($item==='') continue;
                $out[]=$item;
            }
            return array_values($out);
        };
        $same_taxonomy=function($a,$b) use ($canonicalize){
            return $canonicalize($a)===$canonicalize($b);
        };

        if(!$same_taxonomy($actual_categories,$expected_categories) || !$same_taxonomy($actual_tags,$expected_tags)){
            global $wpdb;
            $raw_to_write=$this->secure_options($o);
            $updated_rows=$wpdb->update(
                $wpdb->options,
                ['option_value'=>maybe_serialize($raw_to_write)],
                ['option_name'=>'jaf_options'],
                ['%s'],
                ['%s']
            );
            wp_cache_delete('jaf_options','options');

            $verify_raw=get_option('jaf_options',[]);
            if(!is_array($verify_raw)) $verify_raw=[];
            $saved_profiles=is_array($verify_raw['wordpress_profiles']??null)?$verify_raw['wordpress_profiles']:[];
            $saved_profile=is_array($saved_profiles[$id]??null)?$saved_profiles[$id]:[];
            $actual_categories=is_array($saved_profile['taxonomy_manual']['categories']??null)
                ? $saved_profile['taxonomy_manual']['categories'] : [];
            $actual_tags=is_array($saved_profile['taxonomy_manual']['tags']??null)
                ? $saved_profile['taxonomy_manual']['tags'] : [];

            if(!$same_taxonomy($actual_categories,$expected_categories) || !$same_taxonomy($actual_tags,$expected_tags)){
                wp_die('Profil gagal disimpan: Kategori atau Tag belum berhasil dipertahankan di database. Tidak ada perubahan taxonomy WordPress yang dilakukan.');
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=wordpress&saved=1'));
        exit;
    }

    private function is_local_adsense_enabled() {
        $value=get_option('jaf_local_adsense', null);
        if($value === null) {
            $raw=get_option('jaf_options',[]);
            $value=is_array($raw) && !empty($raw['local_adsense']) ? 1 : 0;
        }
        return !empty($value);
    }

    private function migrate_local_adsense_flag() {
        $existing=get_option('jaf_local_adsense', null);
        if($existing === null) {
            $raw=get_option('jaf_options',[]);
            $value=is_array($raw) && !empty($raw['local_adsense']) ? 1 : 0;
            add_option('jaf_local_adsense', $value, '', false);
        }
    }

    function save_local_adsense() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        $nonce=sanitize_text_field(wp_unslash($_POST['_wpnonce']??''));
        if(!wp_verify_nonce($nonce,'jaf_save_local_adsense')) wp_die('Nonce tidak valid.');
        $raw=isset($_POST['local_adsense']) ? wp_unslash($_POST['local_adsense']) : '0';
        $values=is_array($raw)?$raw:[$raw];
        $local_adsense=in_array('1',array_map('strval',$values),true) ? 1 : 0;

        // Website Ini mempunyai state Web Adsense sendiri. Simpan pada option khusus
        // agar penyimpanan profil WordPress/Blogger atau Settings API tidak dapat
        // menimpa flag ini.
        $updated=update_option('jaf_local_adsense',$local_adsense,false);
        wp_cache_delete('jaf_local_adsense','options');
        $verify=get_option('jaf_local_adsense',null);
        $verify_local=!empty($verify) ? 1 : 0;

        // Jika object cache/hook menghalangi update_option(), lakukan satu fallback
        // langsung ke tabel options. Hanya option khusus ini yang disentuh.
        if($verify_local !== $local_adsense) {
            global $wpdb;
            $row=$wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name=%s LIMIT 1",'jaf_local_adsense'));
            if($row) {
                $wpdb->update($wpdb->options,['option_value'=>maybe_serialize($local_adsense)],['option_name'=>'jaf_local_adsense'],['%s'],['%s']);
            } else {
                $wpdb->insert($wpdb->options,['option_name'=>'jaf_local_adsense','option_value'=>maybe_serialize($local_adsense),'autoload'=>'no'],['%s','%s','%s']);
            }
            wp_cache_delete('jaf_local_adsense','options');
            $verify=get_option('jaf_local_adsense',null);
            $verify_local=!empty($verify) ? 1 : 0;
        }

        if($verify_local !== $local_adsense) {
            wp_die('Penanda Web Adsense gagal disimpan. Pengaturan lain tidak diubah.');
        }
        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=wordpress&profile=local&saved=1'));
        exit;
    }

    function save_local_taxonomy() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        $nonce=sanitize_text_field(wp_unslash($_POST['_wpnonce']??''));
        if(!wp_verify_nonce($nonce,'jaf_save_local_taxonomy')) wp_die('Nonce tidak valid.');
        $o=$this->get_secure_options();
        $incoming=is_array($_POST['local_taxonomy']??null)?wp_unslash($_POST['local_taxonomy']):[];
        $o['local_taxonomy_manual']=[
            'categories'=>$this->parse_manual_terms($incoming['categories']??''),
            'tags'=>$this->parse_manual_terms($incoming['tags']??''),
        ];
        $this->save_secure_options($o);
        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=wordpress&profile=local&saved=1'));
        exit;
    }

    function delete_wp_profile() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))!=='POST') wp_die('Metode penghapusan tidak valid.');
        $id=sanitize_key(wp_unslash($_POST['profile']??''));
        if($id==='') wp_die('Profil WordPress tidak valid.');
        $nonce=sanitize_text_field(wp_unslash($_POST['_wpnonce']??''));
        if(!wp_verify_nonce($nonce,'jaf_delete_wp_profile_'.$id)) wp_die('Nonce tidak valid.');

        $raw=get_option('jaf_options',[]);
        if(!is_array($raw)) $raw=[];
        $profiles=is_array($raw['wordpress_profiles']??null)?$raw['wordpress_profiles']:[];
        if(!array_key_exists($id,$profiles)) {
            wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=wordpress&deleted=0'));
            exit;
        }

        unset($profiles[$id]);
        $raw['wordpress_profiles']=$profiles;

        // Jalur normal WordPress terlebih dahulu.
        update_option('jaf_options',$raw,false);
        wp_cache_delete('jaf_options','options');
        $verify=get_option('jaf_options',[]);
        $verify_profiles=is_array($verify['wordpress_profiles']??null)?$verify['wordpress_profiles']:[];

        // Fallback jika update_option() ditolak/ditahan oleh object cache atau hook.
        if(array_key_exists($id,$verify_profiles)) {
            global $wpdb;
            $wpdb->update(
                $wpdb->options,
                ['option_value'=>maybe_serialize($raw)],
                ['option_name'=>'jaf_options'],
                ['%s'],
                ['%s']
            );
            wp_cache_delete('jaf_options','options');
            $verify=get_option('jaf_options',[]);
            $verify_profiles=is_array($verify['wordpress_profiles']??null)?$verify['wordpress_profiles']:[];
        }

        if(array_key_exists($id,$verify_profiles)) {
            wp_die('Profil WordPress gagal dihapus. Database belum menerima perubahan.');
        }

        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=wordpress&deleted=1'));
        exit;
    }

    private function wordpress_profiles() {
        $o=$this->get_secure_options();
        $profiles=$o['wordpress_profiles']??[];
        return is_array($profiles)?$profiles:[];
    }

    private function sanitize_wordpress_profiles($incoming,$existing=[]) {
        $incoming=is_array($incoming)?$incoming:[]; $existing=is_array($existing)?$existing:[]; $out=$existing;
        foreach($incoming as $id=>$pr){
            $id=sanitize_key($id); if($id==='') continue; if(!is_array($pr)) continue;
            $old=is_array($existing[$id]??null)?$existing[$id]:[];
            $pass=trim((string)($pr['app_password']??''));
            if($pass==='') $pass=(string)($old['app_password']??'');
            $site_url=esc_url_raw($pr['site_url']??($old['site_url']??''));
            $old_manual=is_array($old['taxonomy_manual']??null)?$old['taxonomy_manual']:[];
            $manual=is_array($pr['taxonomy_manual']??null)?$pr['taxonomy_manual']:[];
            $old_cache=is_array($old['taxonomy_cache']??null)?$old['taxonomy_cache']:[];
            $old_selected=is_array($old['taxonomy_selected']??null)?$old['taxonomy_selected']:[];
            $cat_raw=array_key_exists('taxonomy_categories',$pr)?$pr['taxonomy_categories']:implode(", ",(array)($old_manual['categories']??[]));
            $tag_raw=array_key_exists('taxonomy_tags',$pr)?$pr['taxonomy_tags']:implode(", ",(array)($old_manual['tags']??[]));
            // Kolom Manual adalah source of truth. Setiap submit profil yang membawa
            // kedua textarea ini harus menyimpan isi terakhirnya, termasuk hasil
            // tombol Ambil Kategori dan Tag dan edit/hapus manual dari pengguna.
            $taxonomy_manual=['categories'=>$this->parse_manual_terms($cat_raw),'tags'=>$this->parse_manual_terms($tag_raw)];
            $out[$id]=[
                'name'=>sanitize_text_field($pr['name']??($old['name']??$id)),
                'site_url'=>$site_url,
                'username'=>sanitize_text_field($pr['username']??($old['username']??'')),
                'app_password'=>sanitize_text_field($pass),
                'taxonomy_manual'=>$taxonomy_manual,
                'taxonomy_cache'=>$old_cache,
                'taxonomy_selected'=>$old_selected,
                'adsense'=>!empty($pr['adsense']) ? 1 : (!empty($old['adsense']) ? 1 : 0),
                'language'=>in_array(($pr['language']??($old['language']??'id')),['id','en'],true)?($pr['language']??($old['language']??'id')):'id',
            ];
        }
        return $out;
    }

    private function sanitize_profiles($incoming,$existing=[]) {
        $incoming=is_array($incoming)?$incoming:[]; $existing=is_array($existing)?$existing:[]; $out=$existing;
        foreach($incoming as $id=>$pr){
            $id=sanitize_key($id); if($id==='') continue; $old=is_array($existing[$id]??null)?$existing[$id]:[];
            if(!is_array($pr)) continue;
            $out[$id]=[
                'name'=>sanitize_text_field($pr['name']??($old['name']??$id)),
                'blog_id'=>sanitize_text_field($pr['blog_id']??($old['blog_id']??'')),
                'blog_url'=>esc_url_raw($pr['blog_url']??($old['blog_url']??'')),
                'labels'=>sanitize_textarea_field($pr['labels']??($old['labels']??'')),
                'prompt'=>wp_kses_post($pr['prompt']??($old['prompt']??JAF_Prompt::default_blogger_prompt())),
                'adsense'=>!empty($pr['adsense']) ? 1 : (!empty($old['adsense']) ? 1 : 0),
                'language'=>in_array(($pr['language']??($old['language']??'id')),['id','en'],true)?($pr['language']??($old['language']??'id')):'id'
            ];
        }
        return $out;
    }

    private function secret_key() {
        return hash('sha256', AUTH_KEY.'|'.SECURE_AUTH_KEY.'|'.LOGGED_IN_KEY.'|'.NONCE_KEY, true);
    }

    private function encrypt_secret($value) {
        $value=(string)$value;
        if($value==='') return '';
        if(strpos($value,'jafenc:v1:')===0) return $value;
        if(!function_exists('openssl_encrypt')) return $value;
        $cipher='aes-256-cbc';
        $ivlen=openssl_cipher_iv_length($cipher);
        if(!$ivlen) return $value;
        try { $iv=random_bytes($ivlen); } catch(Exception $e) { $iv=openssl_random_pseudo_bytes($ivlen); }
        $enc=openssl_encrypt($value,$cipher,$this->secret_key(),OPENSSL_RAW_DATA,$iv);
        if($enc===false) return $value;
        return 'jafenc:v1:'.base64_encode($iv).':'.base64_encode($enc);
    }

    private function decrypt_secret($value) {
        $value=(string)$value;
        if($value==='' || strpos($value,'jafenc:v1:')!==0) return $value;
        if(!function_exists('openssl_decrypt')) return '';
        $parts=explode(':',$value,4);
        if(count($parts)!==4) return '';
        $iv=base64_decode($parts[2],true); $enc=base64_decode($parts[3],true);
        if($iv===false || $enc===false) return '';
        $plain=openssl_decrypt($enc,'aes-256-cbc',$this->secret_key(),OPENSSL_RAW_DATA,$iv);
        return $plain===false?'':$plain;
    }

    private function decrypt_secret_deep($value) {
        $value=(string)$value;
        for($i=0;$i<3 && strpos($value,'jafenc:v1:')===0;$i++) {
            $next=$this->decrypt_secret($value);
            if($next==='' || $next===$value) break;
            $value=$next;
        }
        return $value;
    }

    private function get_secure_options() {
        $o=get_option('jaf_options',[]);
        if(!is_array($o)) $o=[];
        // OpenAI is managed centrally by Japur Suite API Center. Keep the legacy
        // field as a compatibility mirror so existing generator code remains intact.
        $central_openai = (string) get_option('japur_api_openai_key', '');
        if (strpos($central_openai, 'jafenc:v1:') === 0) $central_openai = $this->decrypt_secret_deep($central_openai);
        if ($central_openai !== '') $o['api_key'] = $central_openai;
        foreach(['api_key','blogger_client_secret','blogger_refresh_token','blogger_access_token'] as $key) {
            if(array_key_exists($key,$o)) $o[$key]=$this->decrypt_secret_deep($o[$key]);
        }
        if(is_array($o['wordpress_profiles']??null)){ foreach($o['wordpress_profiles'] as $id=>$p){ if(is_array($p) && array_key_exists('app_password',$p)) $o['wordpress_profiles'][$id]['app_password']=$this->decrypt_secret($p['app_password']); } }
        return $o;
    }

    private function secure_options($o) {
        foreach(['api_key','blogger_client_secret','blogger_refresh_token','blogger_access_token'] as $key) {
            if(array_key_exists($key,$o)) $o[$key]=$this->encrypt_secret($o[$key]);
        }
        if(is_array($o['wordpress_profiles']??null)){ foreach($o['wordpress_profiles'] as $id=>$p){ if(is_array($p) && array_key_exists('app_password',$p)) $o['wordpress_profiles'][$id]['app_password']=$this->encrypt_secret($p['app_password']); } }
        return $o;
    }

    private function save_secure_options($o) {
        return update_option('jaf_options',$this->secure_options($o));
    }

    private function blogger_profiles() {
        $o=$this->get_secure_options();
        $profiles=$o['blogger_profiles']??[];
        return is_array($profiles)?$profiles:[];
    }

    private function migrate_blogger_profiles() {
        // Work on decrypted options. The previous implementation read the raw
        // encrypted option and then called save_secure_options(), encrypting
        // OAuth secrets again on every request. That broke the sequence:
        // OAuth succeeds -> next request -> token is double-encrypted ->
        // Blogger sees an invalid credential -> refresh returns invalid_grant.
        $o=$this->get_secure_options();
        $profiles=is_array($o['blogger_profiles']??null)?$o['blogger_profiles']:[];
        $changed=false;

        $legacy_migrated=(bool)get_option('jaf_blogger_oauth_legacy_migrated_v133',false);
        if(!$legacy_migrated) {
            $shared=get_option('jaf_blogger_oauth_tokens',[]);
            $shared=is_array($shared)?$shared:[];
            if(empty($o['blogger_refresh_token']) && !empty($shared['refresh_token'])) { $o['blogger_refresh_token']=sanitize_text_field($shared['refresh_token']); $changed=true; }
            if(empty($o['blogger_access_token']) && !empty($shared['access_token'])) { $o['blogger_access_token']=sanitize_text_field($shared['access_token']); $changed=true; }
            if(empty($o['blogger_token_expires']) && !empty($shared['expires_at'])) { $o['blogger_token_expires']=absint($shared['expires_at']); $changed=true; }
            if(!empty($shared['refresh_token']) || (!empty($shared['access_token']) && (int)($shared['expires_at']??0)>time())) { $o['blogger_connected']=1; $changed=true; }
            delete_option('jaf_blogger_oauth_tokens');
            update_option('jaf_blogger_oauth_legacy_migrated_v133',1,false);
        }

        if(empty($o['blogger_client_id']) || empty($o['blogger_client_secret'])) {
            foreach($profiles as $pr){
                if(!is_array($pr)) continue;
                if(!empty($pr['client_id']) && !empty($pr['client_secret'])) {
                    $o['blogger_client_id']=sanitize_text_field($pr['client_id']);
                    $o['blogger_client_secret']=sanitize_text_field($pr['client_secret']);
                    $changed=true;
                    break;
                }
            }
        }

        $profile_token_migrated=(bool)get_option('jaf_blogger_profile_token_migrated_v133',false);
        if(!$profile_token_migrated && (empty($o['blogger_refresh_token']) || empty($o['blogger_access_token']))) {
            foreach($profiles as $pr){
                if(!is_array($pr)) continue;
                $t=is_array($pr['tokens']??null)?$pr['tokens']:[];
                if(empty($o['blogger_refresh_token']) && !empty($t['refresh_token'])) { $o['blogger_refresh_token']=sanitize_text_field($t['refresh_token']); $changed=true; }
                if(empty($o['blogger_access_token']) && !empty($t['access_token'])) { $o['blogger_access_token']=sanitize_text_field($t['access_token']); $changed=true; }
                if(empty($o['blogger_token_expires']) && !empty($t['expires_at'])) { $o['blogger_token_expires']=absint($t['expires_at']); $changed=true; }
                if(!empty($o['blogger_refresh_token']) && !empty($o['blogger_access_token'])) break;
            }
        }
        if(!$profile_token_migrated) update_option('jaf_blogger_profile_token_migrated_v133',1,false);

        if(!empty($o['blogger_refresh_token'])) {
            if(empty($o['blogger_connected'])) { $o['blogger_connected']=1; $changed=true; }
        } elseif(!empty($o['blogger_access_token']) && (int)($o['blogger_token_expires']??0)>time()) {
            if(empty($o['blogger_connected'])) { $o['blogger_connected']=1; $changed=true; }
        } elseif(!isset($o['blogger_connected'])) {
            $o['blogger_connected']=0; $changed=true;
        }

        if($profiles){
            foreach($profiles as $id=>$pr){
                if(!is_array($pr)) continue;
                if(isset($pr['client_id']) || isset($pr['client_secret']) || isset($pr['tokens'])) {
                    unset($profiles[$id]['client_id'],$profiles[$id]['client_secret'],$profiles[$id]['tokens']);
                    $changed=true;
                }
            }
            $o['blogger_profiles']=$profiles;
        }

        if($changed) $this->save_secure_options($o);
    }

    private function get_blogger_profile($id) {
        $profiles=$this->blogger_profiles();
        if($id!=='' && isset($profiles[$id]) && is_array($profiles[$id])) return $profiles[$id];
        if(isset($profiles['default'])) return $profiles['default'];
        return null;
    }

    private function profile_labels($profile) {
        return array_values(array_filter(array_unique(array_map('sanitize_text_field',preg_split('/[\r\n,]+/',(string)($profile['labels']??''))))));
    }

    private function blogger_tokens($id) {
        $profile=$this->get_blogger_profile($id);
        if(!$profile) return [];
        return is_array($profile['tokens']??null)?$profile['tokens']:[];
    }

    function blogger_page($o) {
        $profiles=$this->blogger_profiles();
        $selected=isset($_GET['profile'])?sanitize_key($_GET['profile']):'';
        if($selected==='new') {
            $selected='blogger_'.substr(wp_generate_uuid4(),0,8);
            $profiles[$selected]=['name'=>'Profil Blogger Baru','blog_id'=>'','blog_url'=>'','labels'=>'','prompt'=>'','adsense'=>0,'language'=>'id'];
            $o['blogger_profiles']=$profiles;
            $this->save_secure_options($o);
            echo '<script>location.href='.wp_json_encode(admin_url('admin.php?page=jaf-extractor&tab=blogger&profile='.$selected)).';</script>';
            return;
        }
        $base=admin_url('admin.php?page=jaf-extractor&tab=blogger');
        // `blogger_connected` is authoritative after the token validation layer.
        // A mere stored refresh token is not proof that Google still accepts it.
        $conn_state=$this->get_blogger_connection_state();
        $connected=($conn_state==='connected');

        echo '<div class="jwp-card"><div class="jwp-card-head"><div><h2>Pengaturan Blogger</h2><p>Kelola koneksi daftar blog dan pengaturan profil Blogger.</p></div><span class="jwp-badge">Blogger</span></div>';
        echo '<div class="jwp-blogger-connection"><div class="jwp-section-head"><h2>Daftar Blog dari Google</h2><p>Ambil atau perbarui daftar blog dari akun Google yang sudah terhubung.</p></div>';
        if($connected) echo '<div class="jwp-blogger-connection-actions"><button type="button" class="button button-primary button-large" id="jaf-refresh-blogger-blogs">Ambil Daftar Blog dari Google</button></div>';
        else echo '<div class="jwp-info"><strong>Google belum terhubung</strong><p>Buka API Center di Japur Suite untuk menghubungkan atau memperbarui OAuth terlebih dahulu.</p></div>';
        echo '<div id="jaf-refresh-blogger-status" class="jwp-status-text" aria-live="polite"></div>';
        echo '</div></div>';
        echo '<script>(function(){var btn=document.getElementById("jaf-refresh-blogger-blogs");if(!btn)return;btn.addEventListener("click",function(){btn.disabled=true;var st=document.getElementById("jaf-refresh-blogger-status");if(st)st.textContent="Memuat daftar blog...";var fd=new FormData();fd.append("action","jaf_blogger_refresh_blogs");fd.append("nonce","'.esc_js(wp_create_nonce('jaf_blogger_refresh_blogs_ajax')).'");fetch("'.esc_js(admin_url('admin-ajax.php')).'",{method:"POST",credentials:"same-origin",body:fd}).then(function(r){return r.json();}).then(function(res){if(res.success){if(st)st.textContent=(res.data.message||"Daftar blog berhasil diperbarui.");setTimeout(function(){location.reload();},700);}else{if(st)st.textContent=(res.data&&res.data.message)?res.data.message:"Gagal mengambil daftar blog.";btn.disabled=false;}}).catch(function(){if(st)st.textContent="Tidak dapat menghubungi server WordPress.";btn.disabled=false;});});})();</script>';

        echo '<div class="jwp-card" style="margin-top:16px"><div class="jwp-card-head"><div><h2>Daftar Profil Blogger</h2><p>Kelola profil Blogger yang sudah tersimpan. Klik <strong>Kelola</strong> untuk membuka pengaturan profil.</p></div><span class="jwp-badge">'.count($profiles).' Profil</span></div><div class="jwp-profiles">';
        if($profiles){
            foreach($profiles as $id=>$pr){
                $c=$connected;
                echo '<div class="jwp-profile-item"><div><strong>'.esc_html($pr['name']??$id).'</strong><small>'.esc_html($pr['blog_url']??'').' · '.(($pr['language']??'id')==='en'?'🇬🇧 English':'🇮🇩 Indonesia').' · '.($c?'OAuth Terhubung':'OAuth Belum terhubung').'</small></div><a class="button" href="'.esc_url($base.'&profile='.rawurlencode($id).'#jaf-profile-editor').'">Kelola</a></div>';
            }
        } else {
            echo '<div class="jwp-info"><strong>Belum ada profil Blogger.</strong><p>Ambil daftar blog dari Google terlebih dahulu, lalu tambahkan profil yang ingin digunakan.</p></div>';
        }
        echo '</div><div class="jwp-actions"><a class="button button-primary button-large" href="'.esc_url(wp_nonce_url($base.'&profile=new','jaf_blogger_profile_new')).'"><span class="dashicons dashicons-plus-alt" aria-hidden="true"></span> Tambah Profil Blogger</a></div></div>';

        if($selected!==''){
            $p=isset($profiles[$selected])?$profiles[$selected]:null;
            if(!$p){
                echo '<div class="jwp-info" style="margin-top:16px"><strong>Profil tidak ditemukan.</strong><p>Silakan kembali ke daftar profil Blogger.</p></div>';
            } else {
                $profile_display_name=trim((string)($p['name']??''));
                if($profile_display_name==='') $profile_display_name='Profil Blogger';
                echo '<div class="jwp-profile-sticky"><div class="jwp-profile-sticky-copy"><span class="jwp-profile-sticky-label">Sedang mengelola</span><strong>'.esc_html($profile_display_name).'</strong></div><a class="button" href="'.esc_url($base).'">Kembali ke Daftar Profil</a></div>';
                echo '<div id="jaf-profile-editor" class="jwp-card jwp-profile-editor" style="margin-top:16px"><div class="jwp-card-head"><div><h2>Kelola Profil Blog Blogger</h2><p>Atur nama profil, Blog ID, URL, Label, dan Prompt khusus. OAuth tidak disimpan ulang di profil.</p></div><span class="jwp-badge">Profil</span></div><div class="jwp-fields">';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php?action=jaf_save_blogger_profile')).'">'; wp_nonce_field('jaf_group-options'); echo '<input type="hidden" name="profile" value="'.esc_attr($selected).'">';
                echo '<input type="hidden" name="jaf_options[blogger_profiles]['.esc_attr($selected).'][name]" value="'.esc_attr($p['name']).'">';
                $this->profile_row('Nama Profil','name',$p,$selected,'text','Contoh: Blog Teknologi');
                $this->profile_row('Blogger Blog ID','blog_id',$p,$selected,'text','Masukkan Blog ID atau gunakan daftar blog dari Google.');
                $this->profile_row('URL Blog','blog_url',$p,$selected,'url','https://contoh.blogspot.com');
                echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Bahasa Artikel</div><div class="jwp-desc">Bahasa default saat profil ini digunakan dalam Multi Website. Profil lama tetap Indonesia.</div></div><div class="jwp-input"><select name="jaf_options[blogger_profiles]['.esc_attr($selected).'][language]"><option value="id"'.selected(($p['language']??'id'),'id',false).'>🇮🇩 Bahasa Indonesia</option><option value="en"'.selected(($p['language']??'id'),'en',false).'>🇬🇧 English</option></select></div></div>';
                echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">Web Adsense</div><div class="jwp-desc">Tandai blog ini agar masuk filter Web Adsense di Buat Artikel.</div></div><div class="jwp-input"><label><input type="checkbox" name="jaf_options[blogger_profiles]['.esc_attr($selected).'][adsense]" value="1" '.checked(!empty($p['adsense']),true,false).'> Tandai sebagai Web Adsense</label></div></div>';
                echo '<div class="jwp-row jwp-row-block"><div class="jwp-copy"><div class="jwp-title">Daftar Label Blogger</div><div class="jwp-desc">Satu label per baris. Label ini digunakan saat membuat artikel ke profil tersebut.</div></div><div class="jwp-input"><textarea name="jaf_options[blogger_profiles]['.esc_attr($selected).'][labels]" rows="6" placeholder="Berita&#10;Teknologi&#10;Otomotif">'.esc_textarea($p['labels']??'').'</textarea></div></div>';
                echo '<div class="jwp-prompt-section"><div class="jwp-prompt-head"><div><div class="jwp-title">Prompt Khusus Profil Ini</div><div class="jwp-desc">Supplement khusus profil Blogger. Aturan universal tetap berasal dari Universal Master Prompt Blogger.</div></div><span class="jwp-badge">Prompt</span></div><textarea class="large-text code jwp-prompt-textarea" rows="20" name="jaf_options[blogger_profiles]['.esc_attr($selected).'][prompt]">'.esc_textarea($p['prompt']??JAF_Prompt::default_blogger_prompt()).'</textarea></div>';
                echo '</div><div class="jwp-actions"><a class="button" href="'.esc_url($base).'">Kembali ke Daftar Profil</a><button type="submit" class="button button-primary button-large"><span class="dashicons dashicons-yes" aria-hidden="true"></span> Simpan Profil Blogger</button></div></form></div></div>';
            }
        }

        echo '<div class="jwp-footer-card"><div class="jwp-footer-note"><h3>Catatan</h3><p>OAuth digunakan bersama oleh profil Blogger. Pengaturan profil menentukan Blog ID, URL, Label, dan Prompt khusus yang digunakan saat publikasi.</p></div></div>';
    }

    private function profile_row($label,$key,$p,$id,$type='text',$placeholder='',$scope='blogger_profiles',$input_id='') {
        $scope=sanitize_key($scope); if($scope==='') $scope='blogger_profiles';
        $field_name=$scope==='wp_profile' ? 'wp_profile['.esc_attr($key).']' : 'jaf_options['.esc_attr($scope).']['.esc_attr($id).']['.esc_attr($key).']';
        echo '<div class="jwp-row"><div class="jwp-copy"><div class="jwp-title">'.esc_html($label).'</div><div class="jwp-desc">'.esc_html($placeholder!==''?$placeholder:'Isi data sesuai profil yang digunakan.').'</div></div><div class="jwp-input"><input'.($input_id!==''?' id="'.esc_attr($input_id).'"':'').' type="'.esc_attr($type).'" name="'.$field_name.'" value="'.esc_attr($p[$key]??'').'" placeholder="'.esc_attr($placeholder).'" autocomplete="off"></div></div>';
    }

    function blogger_oauth_callback() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        if(!class_exists('JAI_Pro_GSC')) wp_die('Auto Index PRO belum tersedia sebagai OAuth Hub Google.');
        // Blogger sekarang memakai satu pintu OAuth Auto Index PRO. Jangan
        // menukar authorization code di sini karena callback resmi dimiliki
        // oleh Auto Index PRO. Setelah OAuth selesai, token bersama langsung
        // dapat digunakan oleh Extractor AI.
        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=blogger&connected=1')); exit;
    }

    private function get_blogger_connection_state() {
        if(!class_exists('JAI_Pro_GSC')) return 'unknown';
        $access=JAI_Pro_GSC::blogger_access_token(false);
        if(is_wp_error($access) || $access==='') return 'disconnected';
        $result=$this->blogger_blogs_request($access);
        if(is_array($result) && (int)$result['http']>=200 && (int)$result['http']<300) return 'connected';
        if(is_array($result) && (int)$result['http']===401) {
            $fresh=JAI_Pro_GSC::blogger_access_token(true);
            if(is_wp_error($fresh) || $fresh==='') return 'disconnected';
            $retry=$this->blogger_blogs_request($fresh);
            if(is_array($retry) && (int)$retry['http']>=200 && (int)$retry['http']<300) return 'connected';
        }
        return 'unknown';
    }

    private function blogger_access_token($force_refresh=false) {
        if(!class_exists('JAI_Pro_GSC')) return new WP_Error('blogger_auth','Auto Index PRO OAuth Hub belum tersedia.');
        return JAI_Pro_GSC::blogger_access_token($force_refresh);
    }

    private function blogger_blogs_request($access) {
        if($access==='') return new WP_Error('blogger_api','Access token kosong.');
        $r=wp_remote_get('https://www.googleapis.com/blogger/v3/users/self/blogs',['timeout'=>30,'headers'=>['Authorization'=>'Bearer '.$access,'Accept'=>'application/json']]);
        if(is_wp_error($r)) return new WP_Error('blogger_api',$r->get_error_message());
        $http=wp_remote_retrieve_response_code($r); $body=wp_remote_retrieve_body($r); $data=json_decode($body,true);
        return ['http'=>$http,'body'=>$body,'data'=>is_array($data)?$data:[]];
    }

    /**
     * Satu jalur request Blogger API. Seperti Auto Index PRO, HTTP 401 tidak
     * langsung dianggap gagal permanen: token di-refresh lalu request diulang
     * sekali. Ini mencegah access token cache yang sudah tidak diterima Google
     * membuat operasi Blogger gagal walaupun refresh token masih valid.
     */
    private function blogger_api_request($method,$url,$body=null,$retry=true) {
        $access=$this->blogger_access_token();
        if(is_wp_error($access)) return $access;
        $args=['method'=>strtoupper($method),'timeout'=>60,'headers'=>['Authorization'=>'Bearer '.$access,'Accept'=>'application/json']];
        if($body!==null){ $args['headers']['Content-Type']='application/json; charset=utf-8'; $args['body']=wp_json_encode($body); }
        $r=wp_remote_request($url,$args);
        if(is_wp_error($r)) return $r;
        $http=(int)wp_remote_retrieve_response_code($r); $raw=wp_remote_retrieve_body($r); $data=json_decode($raw,true);
        if($http===401 && $retry){
            $fresh=$this->blogger_access_token(true);
            if(!is_wp_error($fresh)) return $this->blogger_api_request($method,$url,$body,false);
            return $fresh;
        }
        return ['http'=>$http,'body'=>$raw,'data'=>is_array($data)?$data:[]];
    }

    private function fetch_and_store_blogger_blogs() {
        // Jangan langsung menganggap access token kadaluarsa hanya karena
        // metadata expires_at tidak cocok dengan jam server. Validasi token
        // yang baru saja diterbitkan langsung ke Blogger API terlebih dahulu.
        // Ini juga membuat proses tahan terhadap clock skew/cache expiry.
        $stored=$this->get_secure_options();
        $access=!empty($stored['blogger_access_token'])?(string)$stored['blogger_access_token']:'';
        $result=null;
        if($access!=='') {
            $result=$this->blogger_blogs_request($access);
            if(!is_wp_error($result) && (int)$result['http']>=200 && (int)$result['http']<300) {
                $data=$result['data'];
                // Token terbukti aktif. Tandai koneksi tanpa melakukan refresh
                // yang tidak perlu dan tanpa menyentuh refresh token.
                $stored['blogger_connected']=1;
                $this->save_secure_options($stored);
                return $this->store_blogger_blogs_result($data);
            }
        }

        // Access token tidak dapat dipakai. Baru pada titik ini lakukan refresh.
        $access=$this->blogger_access_token();
        if(is_wp_error($access)) return $access;
        $result=$this->blogger_blogs_request($access);
        if(is_wp_error($result)) return $result;
        if((int)$result['http']<200 || (int)$result['http']>=300) {
            $data=$result['data'];
            return new WP_Error('blogger_api','Blogger API HTTP '.absint($result['http']).': '.($data['error']['message']??'Gagal mengambil daftar blog.'));
        }
        return $this->store_blogger_blogs_result($result['data']);
    }

    private function store_blogger_blogs_result($data) {
        if(!is_array($data)) return new WP_Error('blogger_api','Respons Blogger API tidak valid.');
        $blogs=[];
        $raw_items=is_array($data['items']??null)?$data['items']:[];
        // Kompatibilitas tambahan: bila Google mengembalikan blogUserInfos, ambil objek blog di dalamnya.
        if(!$raw_items && !empty($data['blogUserInfos']) && is_array($data['blogUserInfos'])){
            foreach($data['blogUserInfos'] as $info){ if(is_array($info['blog']??null)) $raw_items[]=$info['blog']; }
        }
        foreach($raw_items as $b){
            if(!empty($b['id'])) $blogs[]=['id'=>sanitize_text_field($b['id']),'name'=>sanitize_text_field($b['name']??''),'url'=>esc_url_raw($b['url']??'')];
        }
        $o=$this->get_secure_options(); $profiles=is_array($o['blogger_profiles']??null)?$o['blogger_profiles']:[]; $added=0;
        foreach($blogs as $b){
            $exists=false; foreach($profiles as $pr){ if((string)($pr['blog_id']??'')===(string)$b['id']){$exists=true;break;} }
            if(!$exists){
                $id='blogger_'.substr(wp_generate_uuid4(),0,8);
                $profiles[$id]=['name'=>$b['name']?:'Blogger Baru','blog_id'=>$b['id'],'blog_url'=>$b['url'],'labels'=>'','prompt'=>'','adsense'=>0];
                $added++;
            }
        }
        $o['blogger_available_blogs']=$blogs; $o['blogger_profiles']=$profiles; $this->save_secure_options($o);
        $note='';
        if(!$blogs){
            $note='Google berhasil merespons, tetapi akun OAuth ini tidak mengembalikan blog. Pastikan akun Google yang dipilih saat OAuth adalah akun yang memiliki hak Penulis/Admin pada blog Blogger tersebut.';
        }
        return ['count'=>count($blogs),'added'=>$added,'note'=>$note];
    }

    function refresh_blogger_blogs() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        $nonce=sanitize_text_field(wp_unslash($_GET['_wpnonce']??'')); if(!wp_verify_nonce($nonce,'jaf_blogger_refresh_blogs')) wp_die('Nonce tidak valid.');
        $result=$this->fetch_and_store_blogger_blogs(); if(is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=blogger&blogs_refreshed=1')); exit;
    }

    function ajax_refresh_blogger_blogs() {
        if(!current_user_can('manage_options')) wp_send_json_error(['message'=>'Akses ditolak.'],403);
        $nonce=sanitize_text_field(wp_unslash($_POST['nonce']??'')); if(!wp_verify_nonce($nonce,'jaf_blogger_refresh_blogs_ajax')) wp_send_json_error(['message'=>'Nonce tidak valid.'],403);
        $result=$this->fetch_and_store_blogger_blogs(); if(is_wp_error($result)) wp_send_json_error(['message'=>$result->get_error_message()]);
        wp_send_json_success(['message'=>sprintf('%d blog ditemukan, %d profil baru ditambahkan.',(int)$result['count'],(int)$result['added']),'count'=>(int)$result['count'],'added'=>(int)$result['added'],'note'=>$result['note']??'']);
    }

    function add_blogger_profile() {
        if(!current_user_can('manage_options')) wp_die('Akses ditolak.');
        $bid=sanitize_text_field(wp_unslash($_GET['blog_id']??'')); if($bid==='') wp_die('Blog ID tidak ditemukan.');
        $nonce=sanitize_text_field(wp_unslash($_GET['_wpnonce']??'')); if(!wp_verify_nonce($nonce,'jaf_blogger_add_profile_'.$bid)) wp_die('Nonce tidak valid.');
        $o=$this->get_secure_options(); $profiles=is_array($o['blogger_profiles']??null)?$o['blogger_profiles']:[]; $blogs=is_array($o['blogger_available_blogs']??null)?$o['blogger_available_blogs']:[];
        foreach($profiles as $pr) if((string)($pr['blog_id']??'')===(string)$bid){ wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=blogger')); exit; }
        $found=null; foreach($blogs as $b) if((string)($b['id']??'')===(string)$bid){$found=$b;break;}
        if(!$found) wp_die('Blog tidak ditemukan pada daftar akun Google. Silakan ambil daftar blog lagi.');
        $id='blogger_'.substr(wp_generate_uuid4(),0,8); $profiles[$id]=['name'=>sanitize_text_field($found['name']??'Blogger Baru'),'blog_id'=>$bid,'blog_url'=>esc_url_raw($found['url']??''),'labels'=>'','prompt'=>'','adsense'=>0,'language'=>'id']; $o['blogger_profiles']=$profiles; $this->save_secure_options($o);
        wp_safe_redirect(admin_url('admin.php?page=jaf-extractor&tab=blogger&profile='.rawurlencode($id))); exit;
    }

    private function remote_wp_request($profile,$method,$path,$body=null,$headers=[]) {
        $base=trailingslashit(esc_url_raw($profile['site_url']??''));
        if($base==='/' || $base==='') return new WP_Error('wp_profile','URL website WordPress tidak valid.');
        $url=$base.'wp-json/'.ltrim($path,'/');
        $auth=base64_encode((string)$profile['username'].':'.(string)$profile['app_password']);
        $args=['timeout'=>45,'method'=>strtoupper($method),'headers'=>array_merge(['Authorization'=>'Basic '.$auth,'Accept'=>'application/json'],$headers)];
        if($body!==null){ $args['headers']['Content-Type']='application/json'; $args['body']=wp_json_encode($body); }
        $r=wp_remote_request($url,$args);
        if(is_wp_error($r)) return new WP_Error('wp_remote',$r->get_error_message());
        $code=wp_remote_retrieve_response_code($r); $data=json_decode(wp_remote_retrieve_body($r),true);
        if($code<200||$code>=300){ $msg=is_array($data)&&isset($data['message'])?$data['message']:'HTTP '.$code.' dari WordPress tujuan.'; return new WP_Error('wp_remote',$msg,['http_code'=>$code,'endpoint'=>$url,'method'=>strtoupper($method)]); }
        return is_array($data)?$data:[];
    }

    private function remote_wp_taxonomy($profile,$taxonomy) {
        if(!in_array($taxonomy,['categories','tags'],true)) return new WP_Error('wp_taxonomy','Jenis taxonomy tidak valid.');
        $base=trailingslashit(esc_url_raw($profile['site_url']??''));
        if($base==='/' || $base==='') return new WP_Error('wp_profile','URL website WordPress tidak valid.');
        $auth=base64_encode((string)$profile['username'].':'.(string)$profile['app_password']);
        $all=[]; $total_pages=1;
        for($page=1;$page<=$total_pages && $page<=100;$page++){
            $url=$base.'wp-json/wp/v2/'.$taxonomy.'?per_page=100&page='.$page.'&orderby=name&order=asc';
            $r=wp_remote_get($url,['timeout'=>45,'headers'=>['Authorization'=>'Basic '.$auth,'Accept'=>'application/json']]);
            if(is_wp_error($r)) return new WP_Error('wp_remote',$r->get_error_message());
            $code=wp_remote_retrieve_response_code($r); $data=json_decode(wp_remote_retrieve_body($r),true);
            if($code<200 || $code>=300){
                $msg=is_array($data)&&isset($data['message'])?$data['message']:'HTTP '.$code.' dari WordPress tujuan.';
                return new WP_Error('wp_taxonomy',$msg);
            }
            if(!is_array($data)) break;
            foreach($data as $it){
                $id=absint($it['id']??0); $name=sanitize_text_field($it['name']??'');
                if($id && $name!=='') $all[]=['id'=>$id,'name'=>$name,'slug'=>sanitize_title($it['slug']??$name)];
            }
            $header_pages=absint(wp_remote_retrieve_header($r,'x-wp-totalpages'));
            $total_pages=$header_pages>0?$header_pages:(count($data)<100?$page:$page+1);
        }
        return $all;
    }

    private function remote_wp_term_id($profile,$taxonomy,$name) {
        $q=rawurlencode($name); $items=$this->remote_wp_request($profile,'GET','wp/v2/'.$taxonomy.'?search='.$q.'&per_page=100');
        if(is_wp_error($items)) return $items;
        foreach($items as $it){ if(strcasecmp((string)($it['name']??''),$name)===0) return absint($it['id']); }
        $created=$this->remote_wp_request($profile,'POST','wp/v2/'.$taxonomy,['name'=>$name]);
        if(is_wp_error($created)) return $created;
        return absint($created['id']??0);
    }

    private function publish_to_remote_wordpress($state,$d,$staged_file) {
        $profiles=$this->wordpress_profiles(); $pid=sanitize_key($state['wordpress_profile_id']??''); $p=$profiles[$pid]??null;
        if(!$p) return new WP_Error('wp_profile','Profil WordPress tidak ditemukan.');
        if(empty($staged_file)||!file_exists($staged_file)) return new WP_Error('wp_media','Thumbnail belum tersedia.');
        $bytes=file_get_contents($staged_file); if($bytes===false||$bytes==='') return new WP_Error('wp_media','Thumbnail tidak dapat dibaca.');
        $filename=sanitize_file_name(($d['thumbnail_name']??$d['title']??'thumbnail').'.png');
        $base=trailingslashit(esc_url_raw($p['site_url']??'')); $url=$base.'wp-json/wp/v2/media'; $auth=base64_encode((string)$p['username'].':'.(string)$p['app_password']);
        $r=wp_remote_post($url,['timeout'=>60,'headers'=>['Authorization'=>'Basic '.$auth,'Content-Disposition'=>'attachment; filename="'.$filename.'"','Content-Type'=>'image/png','Accept'=>'application/json'],'body'=>$bytes]);
        if(is_wp_error($r)) return new WP_Error('wp_media',$r->get_error_message(),['stage'=>'THUMBNAIL_UPLOAD','target'=>(string)($p['name']??$p['site_url']??'')]);
        $code=wp_remote_retrieve_response_code($r); $media=json_decode(wp_remote_retrieve_body($r),true); if($code<200||$code>=300||empty($media['id'])) return new WP_Error('wp_media',is_array($media)&&!empty($media['message'])?$media['message']:'Gagal mengunggah thumbnail ke WordPress tujuan (HTTP '.$code.').',['http_code'=>$code,'stage'=>'THUMBNAIL_UPLOAD','endpoint'=>$url]);
        $media_id=absint($media['id']);
        // Tetapkan metadata gambar setelah upload. Endpoint media tetap memakai pipeline upload
        // WordPress sehingga hook Auto WebP + Watermark pada situs tujuan tetap ikut berjalan.
        $media_meta=['title'=>sanitize_text_field($d['thumbnail_title']??$d['title']??''),'alt_text'=>sanitize_text_field($d['thumbnail_alt']??$d['title']??'')];
        $media_updated=$this->remote_wp_request($p,'POST','wp/v2/media/'.$media_id,$media_meta);
        if(is_wp_error($media_updated)) {
            // Metadata bukan syarat publikasi; jangan menggagalkan artikel bila endpoint media
            // tujuan membatasi pembaruan metadata.
            $media_updated=null;
        }
        $categories=[]; foreach((array)($d['_selected_category_names']??[]) as $name){ $id=$this->remote_wp_term_id($p,'categories',sanitize_text_field($name)); if(is_wp_error($id)){ $this->remote_wp_request($p,'DELETE','wp/v2/media/'.$media_id.'?force=true'); return $id; } if($id)$categories[]=$id; }
        $tags=[]; foreach((array)($d['_selected_tag_names']??[]) as $name){ $id=$this->remote_wp_term_id($p,'tags',sanitize_text_field($name)); if(is_wp_error($id)){ $this->remote_wp_request($p,'DELETE','wp/v2/media/'.$media_id.'?force=true'); return $id; } if($id)$tags[]=$id; }
        $content=$this->html_to_blocks($d['content_html']); if($content===''){ $this->remote_wp_request($p,'DELETE','wp/v2/media/'.$media_id.'?force=true'); return new WP_Error('wp_post','Konten gagal dikonversi ke Gutenberg blocks.'); }
        $post=['title'=>sanitize_text_field($d['title']),'excerpt'=>sanitize_textarea_field(wp_strip_all_tags($d['description']??'')),'content'=>$content,'status'=>'publish','featured_media'=>$media_id];
        if($categories)$post['categories']=$categories; if($tags)$post['tags']=$tags;
        $created=$this->remote_wp_request($p,'POST','wp/v2/posts',$post);
        if(is_wp_error($created)){ $this->remote_wp_request($p,'DELETE','wp/v2/media/'.$media_id.'?force=true'); return $created; }
        $post_id=absint($created['id']??0); if(!$post_id){ $this->remote_wp_request($p,'DELETE','wp/v2/media/'.$media_id.'?force=true'); return new WP_Error('wp_post','WordPress tujuan tidak mengembalikan ID artikel.'); }
        @unlink($staged_file);
        return ['message'=>'Artikel berhasil diterapkan dan dipublish.','post_id'=>$post_id,'url'=>$created['link']??trailingslashit($p['site_url']),'edit_url'=>trailingslashit($p['site_url']).'wp-admin/post.php?post='.$post_id.'&action=edit'];
    }

    private function publish_blogger_internal($token){
        $token=sanitize_text_field($token); $key='jaf_v3_'.get_current_user_id().'_'.$token; $state=get_transient($key);
        if(!is_array($state)) return new WP_Error('blogger_job','Hasil artikel sudah kedaluwarsa. Buat artikel lagi.');
        $pid=sanitize_key($state['blogger_profile_id']??''); $profiles=$this->blogger_profiles(); $p=$profiles[$pid]??null;
        if(!$p) return new WP_Error('blogger_profile','Profil Blogger untuk artikel ini tidak ditemukan.');
        if(empty($p['blog_id'])) return new WP_Error('blogger_profile','Blogger Blog ID profil ini belum diisi.');
        $access=$this->blogger_access_token(); if(is_wp_error($access)) return $access;
        $d=$state['fields']??[]; $content=trim($d['content_html']??'');
        if($content==='') return new WP_Error('blogger_content','Konten artikel Blogger kosong.');
        $img_url=''; if(!empty($state['staged_file'])&&file_exists($state['staged_file'])) $img_url=$this->staged_preview_url($state['staged_file']);
        if($img_url){
            $title=sanitize_text_field($d['title']??''); $alt=sanitize_text_field($d['thumbnail_alt']??$title); $img_title=sanitize_text_field($d['thumbnail_title']??$title);
            $img_html='<p><img src="'.esc_url($img_url).'" alt="'.esc_attr($alt).'" title="'.esc_attr($img_title).'" /></p>';
            if(preg_match('/<\/p>\s*/i',$content,$m,PREG_OFFSET_CAPTURE)){ $pos=$m[0][1]+strlen($m[0][0]); $content=substr($content,0,$pos).$img_html.substr($content,$pos); } else $content=$img_html.$content;
        }
        $allowed=$this->profile_labels($p); $posted=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($state['selected_blogger_labels']??[]))))); $labels=array_values(array_intersect($posted,$allowed));
        $payload=['kind'=>'blogger#post','title'=>sanitize_text_field($d['title']??''),'content'=>$content]; if($labels)$payload['labels']=$labels;
        $url='https://www.googleapis.com/blogger/v3/blogs/'.rawurlencode($p['blog_id']).'/posts/?isDraft=false';
        $r=$this->blogger_api_request('POST',$url,$payload);
        if(is_wp_error($r)) return new WP_Error('blogger_api','Blogger API: '.$r->get_error_message(),['http_code'=>0,'target'=>(string)($p['name']??'Profil Blogger')]);
        $http=(int)($r['http']??0); $data=is_array($r['data']??null)?$r['data']:[];
        if($http<200||$http>=300) return new WP_Error('blogger_api','Blogger API HTTP '.$http.': '.($data['error']['message']??'Gagal menerbitkan artikel.'),['http_code'=>$http,'target'=>(string)($p['name']??'Profil Blogger')]);
        delete_transient($key);
        do_action('japur_article_published', [
            'event_key'=>'jaf:'.get_current_user_id().':'.$token.':blogger','platform'=>'blogger','target_key'=>'blogger:'.sanitize_key($pid),
            'target_name'=>(string)($p['name']??'Profil Blogger'),'article_title'=>(string)($d['title']??''),'post_id'=>(string)($data['id']??''),
            'article_url'=>(string)($data['url']??''),'user_id'=>get_current_user_id(),
        ]);
        return ['message'=>'Artikel berhasil dipublish ke Blogger.','post_id'=>$data['id']??'','url'=>$data['url']??''];
    }

    function blogger_publish() {
        if(!$this->auth(true)) return;
        $token=sanitize_text_field($_POST['job']??''); $key='jaf_v3_'.get_current_user_id().'_'.$token; $state=get_transient($key);
        if(!is_array($state)) wp_send_json_error(['message'=>'Hasil artikel sudah kedaluwarsa. Buat artikel lagi.']);
        $pid=sanitize_key($state['blogger_profile_id']??''); $profiles=$this->blogger_profiles(); $p=$profiles[$pid]??null;
        if(!$p) wp_send_json_error(['message'=>'Profil Blogger untuk artikel ini tidak ditemukan.']); if(empty($p['blog_id'])) wp_send_json_error(['message'=>'Blogger Blog ID profil ini belum diisi.']);
        $access=$this->blogger_access_token(); if(is_wp_error($access)) wp_send_json_error(['message'=>$access->get_error_message()]);
        $d=$state['fields']; $content=trim($d['content_html']??''); if($content==='') wp_send_json_error(['message'=>'Konten artikel Blogger kosong.']);
        $img_url=''; if(!empty($state['staged_file'])&&file_exists($state['staged_file'])) $img_url=$this->staged_preview_url($state['staged_file']);
        $alt=''; $img_title=''; if($img_url){ $title=sanitize_text_field($d['title']??''); $alt=sanitize_text_field($d['thumbnail_alt']??$title); $img_title=sanitize_text_field($d['thumbnail_title']??$title); $img_html='<p><img src="'.esc_url($img_url).'" alt="'.esc_attr($alt).'" title="'.esc_attr($img_title).'" /></p>'; if(preg_match('/</p>\s*/i',$content,$m,PREG_OFFSET_CAPTURE)){ $pos=$m[0][1]+strlen($m[0][0]); $content=substr($content,0,$pos).$img_html.substr($content,$pos); } else $content=$img_html.$content; }
        $allowed=$this->profile_labels($p); $posted=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($state['selected_blogger_labels']??$_POST['labels']??[]))))); $labels=array_values(array_intersect($posted,$allowed));
        $payload=['kind'=>'blogger#post','title'=>sanitize_text_field($d['title']??''),'content'=>$content]; if($labels) $payload['labels']=$labels;
        $url='https://www.googleapis.com/blogger/v3/blogs/'.rawurlencode($p['blog_id']).'/posts/?isDraft=false';
        $r=$this->blogger_api_request('POST',$url,$payload);
        if(is_wp_error($r)){ $ed=$r->get_error_data(); $ed=is_array($ed)?$ed:[]; wp_send_json_error(['stage'=>'PUBLISH','message'=>'Blogger API: '.$r->get_error_message(),'http'=>(int)($ed['http_code']??0),'target'=>(string)($p['name']??'Profil Blogger')]); }
        $http=(int)$r['http']; $data=$r['data'];
        if($http<200||$http>=300) wp_send_json_error(['stage'=>'PUBLISH','message'=>'Blogger API HTTP '.$http.': '.($data['error']['message']??'Gagal menerbitkan artikel.'),'http'=>$http,'target'=>(string)($p['name']??'Profil Blogger')]);
        delete_transient($key);
        do_action('japur_article_published', [
            'event_key'=>'jaf:'.get_current_user_id().':'.$token.':blogger',
            'platform'=>'blogger',
            'target_key'=>'blogger:'.sanitize_key($pid),
            'target_name'=>(string)($p['name']??'Profil Blogger'),
            'article_title'=>(string)($d['title']??''),
            'post_id'=>(string)($data['id']??''),
            'article_url'=>(string)($data['url']??''),
            'user_id'=>get_current_user_id(),
        ]);
        wp_send_json_success(['message'=>'Artikel berhasil dipublish ke Blogger.','post_id'=>$data['id']??'','url'=>$data['url']??'']);
    }

    private function normalize_lead_location($location) {
        $location=trim(wp_strip_all_tags((string)$location));
        if($location==='') return '';
        // Hanya menormalkan input yang seluruhnya lower/upper case; kapitalisasi resmi yang sudah baik dipertahankan.
        $is_flat=(mb_strtolower($location,'UTF-8')===$location || mb_strtoupper($location,'UTF-8')===$location);
        if($is_flat) {
            $location=mb_convert_case(mb_strtolower($location,'UTF-8'),MB_CASE_TITLE,'UTF-8');
        }
        // Singkatan wilayah umum tetap memakai bentuk resmi.
        $replacements=[
            '/\bDki\b/u'=>'DKI',
            '/\bDiy\b/u'=>'DIY',
            '/\bDi Yogyakarta\b/u'=>'DI Yogyakarta',
            '/\bKepri\b/u'=>'Kepri',
            '/\bBabel\b/u'=>'Babel',
            '/\bSulawesi Utara\b/u'=>'Sulawesi Utara',
        ];
        foreach($replacements as $pattern=>$replacement) $location=preg_replace($pattern,$replacement,$location);
        return trim(preg_replace('/\s+/u',' ',$location));
    }

    private function format_payload($d,$category_text='',$tag_text='') {
        return "[TITLE]\n".$d['title']."\n\n[DESCRIPTION]\n".$d['description']."\n\n[CATEGORY]\n".$category_text."\n\n[FOCUS_KEYPHRASE]\n".$d['focus_keyword']."\n\n[TAGS]\n".$tag_text."\n\n[CONTENT]\n".$d['content_html'];
    }

    private function stage_image($im,$d,$token) {
        if($im['type']==='base64') $bytes=base64_decode($im['value'],true);
        else {
            $r=wp_remote_get($im['value'],['timeout'=>90,'redirection'=>3]);
            if(is_wp_error($r)) return new WP_Error('image_download',$r->get_error_message());
            $code=(int)wp_remote_retrieve_response_code($r);
            if($code<200||$code>=300) return new WP_Error('image_download','Gagal mengunduh gambar dari penyedia gambar.');
            $bytes=wp_remote_retrieve_body($r);
        }
        if(empty($bytes)) return new WP_Error('image','Data gambar kosong.');
        $up=wp_upload_dir();
        if(!empty($up['error'])) return new WP_Error('upload_dir',$up['error']);
        $dir=trailingslashit($up['basedir']).'japur-extractor-staging/'.get_current_user_id();
        if(!wp_mkdir_p($dir)) return new WP_Error('staging','Tidak bisa membuat folder staging thumbnail.');
        $safe=sanitize_file_name($d['thumbnail_name']??$d['title']);
        $safe=preg_replace('/\.(png|jpe?g|webp)$/i','',$safe); if(!$safe)$safe='thumbnail';
        $path=trailingslashit($dir).$safe.'-'.$token.'.png';
        if(file_put_contents($path,$bytes)===false) return new WP_Error('staging','Gagal menyimpan thumbnail hasil AI.');
        return $path;
    }

    private function normalize_blogger_domain($url){
        $host=wp_parse_url(trim((string)$url),PHP_URL_HOST);
        if(!$host) $host=trim((string)$url);
        $host=strtolower(trim((string)$host));
        $host=preg_replace('/^www\./i','',$host);
        $host=preg_replace('/:\d+$/','',$host);
        return trim($host,"/ \t\n\r\0\x0B");
    }

    private function process_blogger_thumbnail($source,$d,$domain,$token){
        if(!file_exists($source) || !is_readable($source)) return new WP_Error('image_process','File thumbnail tidak dapat dibaca.');
        if(!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) return new WP_Error('image_process','Server WordPress tidak menyediakan GD/WebP untuk memproses thumbnail Blogger.');
        $bytes=@file_get_contents($source);
        if($bytes===false || $bytes==='') return new WP_Error('image_process','Data thumbnail kosong.');
        $img=@imagecreatefromstring($bytes);
        if(!$img) return new WP_Error('image_process','Format thumbnail AI tidak dapat diproses oleh GD.');
        $src_w=imagesx($img); $src_h=imagesy($img);
        if($src_w<1 || $src_h<1){ imagedestroy($img); return new WP_Error('image_process','Dimensi thumbnail tidak valid.'); }

        // Meniru prinsip halaman WebP manual: sisi maksimum 600 px, watermark putih 60%.
        $max_dim=600;
        $scale=min($max_dim/$src_w,$max_dim/$src_h,1);
        $base_w=max(1,(int)round($src_w*$scale)); $base_h=max(1,(int)round($src_h*$scale));
        $up=wp_upload_dir();
        if(!empty($up['error'])){ imagedestroy($img); return new WP_Error('upload_dir',$up['error']); }
        $dir=trailingslashit($up['basedir']).'japur-extractor-staging/'.get_current_user_id();
        if(!wp_mkdir_p($dir)){ imagedestroy($img); return new WP_Error('staging','Tidak bisa membuat folder staging thumbnail.'); }
        $safe=sanitize_file_name($d['thumbnail_name']??$d['title']??'thumbnail');
        $safe=preg_replace('/\.(png|jpe?g|webp)$/i','',$safe); if(!$safe) $safe='thumbnail';
        $path=trailingslashit($dir).$safe.'-'.$token.'.webp';

        // 50 KB adalah batas keras. Kualitas dan dimensi diturunkan bertahap sampai lolos.
        $limit=50*1024;
        $w=$base_w; $h=$base_h; $quality=82; $result=null;
        $watermark=$domain!=='' ? $domain : 'Blogger';
        for($attempt=0;$attempt<28;$attempt++){
            $canvas=imagecreatetruecolor($w,$h);
            imagealphablending($canvas,true); imagesavealpha($canvas,false);
            imagecopyresampled($canvas,$img,0,0,0,0,$w,$h,$src_w,$src_h);
            $font_size=max(12,min(20,(int)round($w/35)));
            $font=dirname(__FILE__).'/../assets/fonts/';
            // Gunakan built-in GD font agar tidak bergantung pada file font eksternal.
            $font_id=$w>=500 ? 3 : 2;
            $tw=imagefontwidth($font_id)*strlen($watermark); $th=imagefontheight($font_id);
            $x=max(8,$w-$tw-10); $y=max($th+6,$h-$th-10);
            $white=imagecolorallocatealpha($canvas,255,255,255,50);
            imagestring($canvas,$font_id,$x,$y-$th,$watermark,$white);

            $tmp=$path.'.tmp';
            @imagewebp($canvas,$tmp,$quality);
            imagedestroy($canvas);
            $size=@filesize($tmp);
            if($size!==false && $size<=$limit){ $result=$tmp; break; }
            @unlink($tmp);
            if($quality>32){ $quality-=6; continue; }
            $w=(int)floor($w*0.88); $h=(int)floor($h*0.88);
            if($w<240 || $h<135){
                $w=max(160,$w); $h=max(90,$h);
                $quality=28;
                // Satu percobaan akhir pada dimensi minimum yang masih wajar.
            }
        }
        imagedestroy($img);
        if(!$result) return new WP_Error('image_process','Thumbnail tidak dapat dikompresi hingga 50 KB. Coba ulangi generate thumbnail.');
        if(!@rename($result,$path)){ @unlink($result); return new WP_Error('staging','Gagal menyimpan WebP thumbnail.'); }
        $final_size=@filesize($path);
        if($final_size===false || $final_size>$limit){ @unlink($path); return new WP_Error('image_process','Ukuran WebP masih melebihi batas 50 KB.'); }
        return $path;
    }

    private function staged_preview_url($file) {
        $up=wp_upload_dir();
        if(strpos($file,trailingslashit($up['basedir']))!==0) return '';
        $rel=ltrim(str_replace(trailingslashit($up['basedir']),'',$file),'/');
        return trailingslashit($up['baseurl']).str_replace('%2F','/',rawurlencode($rel));
    }

    private function attach_staged_image($file,$d,$post_id) {
        if(!file_exists($file)) return new WP_Error('staging','File thumbnail staging tidak ditemukan.');
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        // Pada titik ini post sudah dibuat, sehingga Auto WebP + Watermark
        // bisa membaca post_id dan menerapkan nama file/metadata berdasarkan judul.
        $_REQUEST['post_id']=$post_id;
        $_POST['post_id']=$post_id;
        $name=sanitize_file_name($d['thumbnail_name']??$d['title']);
        $name=preg_replace('/\.(png|jpe?g|webp)$/i','',$name); if(!$name)$name='thumbnail';
        $tmp=$file;
        $file_array=['name'=>$name.'.png','type'=>'image/png','tmp_name'=>$tmp,'error'=>0,'size'=>filesize($tmp)];

        // Use sideload for a server-generated file, then explicitly run the
        // standard upload filters so Suite's Auto WebP + Watermark module can
        // process the result without relying on is_uploaded_file().
        $pre=apply_filters('wp_handle_upload_prefilter',$file_array);
        $moved=wp_handle_sideload($pre,['test_form'=>false,'test_type'=>true,'test_upload'=>false]);
        if(isset($moved['error'])){ unset($_REQUEST['post_id'],$_POST['post_id']); return new WP_Error('upload',$moved['error']); }
        $moved=apply_filters('wp_handle_upload',$moved,['action'=>'wp_handle_sideload']);
        if(isset($moved['error'])){ unset($_REQUEST['post_id'],$_POST['post_id']); return new WP_Error('upload',$moved['error']); }
        $att=['post_mime_type'=>$moved['type'],'post_title'=>sanitize_text_field($d['thumbnail_title']??$d['title']),'post_excerpt'=>sanitize_text_field($d['thumbnail_caption']??''),'post_content'=>sanitize_textarea_field($d['thumbnail_description']??''),'post_status'=>'inherit','post_parent'=>$post_id];
        $id=wp_insert_attachment($att,$moved['file'],$post_id,true);
        if(is_wp_error($id)){ unset($_REQUEST['post_id'],$_POST['post_id']); return $id; }
        wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$moved['file']));
        update_post_meta($id,'_wp_attachment_image_alt',sanitize_text_field($d['thumbnail_alt']??$d['title']));
        unset($_REQUEST['post_id'],$_POST['post_id']);
        @unlink($file);
        return $id;
    }

    private function html_to_blocks($content) {
        $content=trim($content);
        if($content==='') return '';
        if(strpos($content,'<!-- wp:')!==false) return serialize_blocks(parse_blocks($content));
        $content=wp_kses_post($content);
        preg_match_all('~(<(?:p|h[1-6]|ul|ol|blockquote|pre|figure|table|hr|div)\\b[^>]*>.*?</(?:p|h[1-6]|ul|ol|blockquote|pre|figure|table|hr|div)>|<hr\\b[^>]*/?>)~is',$content,$m);
        $blocks=[];
        foreach(($m[1]??[]) as $html){
            $html=trim($html); if($html==='') continue;
            if(preg_match('/^<h([1-6])\\b([^>]*)>(.*?)<\\/h\\1>$/is',$html,$x)) $blocks[]= ['blockName'=>'core/heading','attrs'=>['level'=>(int)$x[1]],'innerBlocks'=>[],'innerHTML'=>$html,'innerContent'=>[$html]];
            elseif(preg_match('/^<p\\b/i',$html)) $blocks[]= ['blockName'=>'core/paragraph','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>$html,'innerContent'=>[$html]];
            elseif(preg_match('/^<(ul|ol)\\b/i',$html,$x)) $blocks[]= ['blockName'=>'core/list','attrs'=>strtolower($x[1])==='ol'?['ordered'=>true]:[],'innerBlocks'=>[],'innerHTML'=>$html,'innerContent'=>[$html]];
            elseif(preg_match('/^<blockquote\\b/i',$html)) $blocks[]= ['blockName'=>'core/quote','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>$html,'innerContent'=>[$html]];
            elseif(preg_match('/^<figure\\b/i',$html)) $blocks[]= ['blockName'=>'core/image','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>$html,'innerContent'=>[$html]];
            elseif(preg_match('/^<table\\b/i',$html)) $blocks[]= ['blockName'=>'core/table','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>$html,'innerContent'=>[$html]];
            elseif(preg_match('/^<hr\\b/i',$html)) $blocks[]= ['blockName'=>'core/separator','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>$html,'innerContent'=>[$html]];
            else {
                $text=trim(wp_strip_all_tags($html));
                if($text!=='') $blocks[]=['blockName'=>'core/paragraph','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>'<p>'.wp_kses_post($text).'</p>','innerContent'=>['<p>'.wp_kses_post($text).'</p>']];
            }
        }
        return serialize_blocks($blocks);
    }
}
