<?php
/**
 * Module: JaPur Unified Web Source Bridge
 * Description: Safe AJAX bridge for Unified Discovery without changing Source Sync.
 * Module Version: 0.1.1
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Web_Source_Bridge')) {
final class JaPur_Web_Source_Bridge {
    const ACTION = 'japur_unified_discover';
    const NONCE  = 'japur_unified_discover_nonce';

    public static function init() {
        add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'ajax_discover']);
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
}

add_action('init', ['JaPur_Web_Source_Bridge', 'init'], 20);
}
