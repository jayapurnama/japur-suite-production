<?php
if (!defined('ABSPATH')) exit;
class JAI_Pro_Sitemap {
    public static function urls() {
        return array_values(array_unique(array_filter([home_url('/wp-sitemap.xml'), home_url('/sitemap_index.xml')])));
    }
    public static function discover() {
        foreach (self::urls() as $sitemap) {
            $r = wp_remote_head($sitemap, ['timeout'=>10, 'redirection'=>3]);
            if (!is_wp_error($r) && (int) wp_remote_retrieve_response_code($r) >= 200 && (int) wp_remote_retrieve_response_code($r) < 400) return $sitemap;
        }
        return home_url('/wp-sitemap.xml');
    }
}
