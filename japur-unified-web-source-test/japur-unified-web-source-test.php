<?php
/**
 * Plugin Name: JaPur Unified Web Source Test
 * Description: Optional test activator for the isolated Unified Web Sumber bridge in JaPur Suite.
 * Version: 0.1.0
 * Author: Japur Ganteng
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Unified_Web_Source_Test')) {
final class JaPur_Unified_Web_Source_Test {
    public static function boot() {
        $candidates = [];
        if (defined('JAPUR_SUITE_DIR')) {
            $candidates[] = rtrim(JAPUR_SUITE_DIR, '/\\') . '/modules/source-discovery/class-web-source-bridge.php';
        }
        $active = (array) get_option('active_plugins', []);
        foreach ($active as $plugin) {
            if (substr($plugin, -strlen('/japur-suite.php')) === '/japur-suite.php' || basename($plugin) === 'japur-suite.php') {
                $candidates[] = WP_PLUGIN_DIR . '/' . dirname($plugin) . '/modules/source-discovery/class-web-source-bridge.php';
            }
        }
        foreach (array_unique($candidates) as $file) {
            if (is_readable($file)) {
                require_once $file;
                return;
            }
        }
    }
}
JaPur_Unified_Web_Source_Test::boot();
