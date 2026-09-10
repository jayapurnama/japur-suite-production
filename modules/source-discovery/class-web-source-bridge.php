<?php
/**
 * Module: JaPur Web Source Bridge
 * Description: Safe AJAX bridge for the passive Unified Discovery Engine.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 *
 * Additive only. Does not modify Source Sync queue, seen history, or existing scanner.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Web_Source_Bridge')) {
final class JaPur_Web_Source_Bridge {
    const VER = '0.1.0';
    const NONCE = 'japur_web_source_bridge';

    public static function init() {
        add_action('wp_ajax_japur_unified_discover', [__CLASS__, 'ajax_discover']);
    }

    private static function auth() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Akses ditolak.'], 403);
            return false;
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce tidak valid.'], 403);
            return false;
        }
        return true;
    }

    public static function ajax_discover() {
        if (!self::auth()) return;

        $site = esc_url_raw(trim(wp_unslash($_POST['site'] ?? '')));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $sitemap = sanitize_text_field(wp_unslash($_POST['sitemap'] ?? 'sitemap.xml'));
        $limit = max(1, min(50, absint($_POST['limit'] ?? 10)));

        if (!$site || !wp_http_validate_url($site)) {
            wp_send_json_error(['message' => 'URL website sumber tidak valid.']);
        }

        $file = __DIR__ . '/class-unified-engine.php';
        if (!class_exists('JaPur_Unified_Discovery_Engine') && is_readable($file)) {
            require_once $file;
        }
        if (!class_exists('JaPur_Unified_Discovery_Engine')) {
            wp_send_json_error(['message' => 'Unified Discovery Engine tidak tersedia.']);
        }

        $result = JaPur_Unified_Discovery_Engine::discover($site, $category, $sitemap, $limit);
        if (!is_array($result)) {
            wp_send_json_error(['message' => 'Discovery gagal menghasilkan data.']);
        }

        wp_send_json_success([
            'items' => array_values((array) ($result['items'] ?? [])),
            'methods' => array_values((array) ($result['methods'] ?? [])),
            'method' => sanitize_text_field((string) ($result['method'] ?? '')),
            'version' => self::VER,
            'engine_version' => sanitize_text_field((string) ($result['version'] ?? '')),
        ]);
    }
}

JaPur_Web_Source_Bridge::init();
}
