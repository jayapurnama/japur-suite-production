<?php
/**
 * Module: Japur Article Task Manager
 * Description: Manajemen target produksi artikel harian untuk Web Adsense.
 * Module Version: 1.0.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

define('JAT_DIR', plugin_dir_path(__FILE__));
define('JAT_VER', '1.0.0');

if (!function_exists('japur_article_task_manager_bootstrap')) {
    function japur_article_task_manager_bootstrap() {
        require_once JAT_DIR . 'includes/class-task-manager.php';
        JAT_Task_Manager::instance();
    }
    add_action('plugins_loaded', 'japur_article_task_manager_bootstrap', 20);
}
