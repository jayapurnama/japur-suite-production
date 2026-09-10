<?php
/**
 * Module: Japur Extractor AI
 * Description: Modul pencipta artikel, thumbnail, URL extractor, WordPress dan Blogger untuk Japur Suite.
 * Module Version: 3.0.140
 * Author: Japur Ganteng
 * Plugin URI: https://jayapurnama.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;
define('JAF_VER','3.0.140');
define('JAF_DIR',plugin_dir_path(__FILE__));
define('JAF_URL',plugin_dir_url(__FILE__));
require_once JAF_DIR.'includes/class-extractor.php';
require_once JAF_DIR.'includes/class-openai.php';
require_once JAF_DIR.'includes/class-prompt.php';
require_once JAF_DIR.'includes/class-admin.php';

// Preserve existing standalone settings and initialize missing defaults when the
// module is first loaded inside Japur Suite.
if (!function_exists('japur_extractor_ai_bootstrap')) {
    function japur_extractor_ai_bootstrap() {
        $old = get_option('jaf_options', []);
        if (!is_array($old)) $old = [];
        $defaults = [
            'api_key'=>'','text_model'=>'gpt-5.6-luna','image_model'=>'gpt-image-2',
            'master_prompt'=>JAF_Prompt::default_prompt(),
            'blogger_prompt'=>JAF_Prompt::default_blogger_prompt(),
            'blogger_master_prompt'=>JAF_Prompt::default_blogger_prompt(),
            'image_prompt'=>'Buat thumbnail realistis seperti foto jurnalistik. Landscape 16:9, tanpa teks, tanpa tulisan, tanpa logo. Objek utama natural dan jelas, komposisi bersih, relevan dengan topik, cocok untuk berita dan Google Discover.',
            'blogger_client_id'=>'','blogger_client_secret'=>'','blogger_blog_id'=>'','blogger_blog_url'=>'','blogger_labels'=>'','blogger_refresh_token'=>'','blogger_access_token'=>'','blogger_token_expires'=>0,'blogger_connected'=>0,'blogger_available_blogs'=>[],'blogger_profiles'=>[], 'wordpress_profiles'=>[], 'local_adsense'=>0
        ];
        update_option('jaf_options', wp_parse_args($old, $defaults));
    }
}
japur_extractor_ai_bootstrap();

if (!class_exists('JapurSuite_Core') || JapurSuite_Core::module_enabled('extractor')) {
    new JAF_Admin();
}

