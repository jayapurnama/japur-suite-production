<?php
/**
 * Module: JaPur Discovery Adapter
 * Description: Isolated normalization adapter for future Web Sumber discovery engines.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 *
 * IMPORTANT: This file is intentionally passive. It registers no hooks, AJAX actions,
 * cron jobs, menus, or database options. It does not alter Source Sync v1.2.3.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Discovery_Adapter')) {
final class JaPur_Discovery_Adapter {
    const VER = '0.1.0';

    /**
     * Normalize discovery results from any engine into one predictable shape.
     * Accepted input keys: url, title, date/lastmod, source/source_id/source_name.
     */
    public static function normalize_items($items, $limit = 10) {
        if (!is_array($items)) return [];

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;

            $url = self::normalize_url($item['url'] ?? '');
            if (!$url) continue;

            $date = sanitize_text_field((string) ($item['date'] ?? ($item['lastmod'] ?? '')));
            $title = sanitize_text_field((string) ($item['title'] ?? ''));
            $source_id = sanitize_key($item['source_id'] ?? '');
            $source_name = sanitize_text_field((string) ($item['source_name'] ?? ($item['source'] ?? '')));

            $key = md5(strtolower($url));
            $out[$key] = [
                'id' => $key,
                'url' => $url,
                'title' => $title,
                'date' => $date,
                'source_id' => $source_id,
                'source_name' => $source_name,
            ];
        }

        $out = array_values($out);
        usort($out, [__CLASS__, 'sort_newest']);

        $limit = max(1, min(200, absint($limit)));
        return array_slice($out, 0, $limit);
    }

    /**
     * Convert normalized discovery items to Source Sync-compatible queue entries.
     * No database write is performed here.
     */
    public static function to_queue_entries($items, $source_id = '', $source_name = '') {
        $normalized = self::normalize_items($items, 200);
        $source_id = sanitize_key($source_id);
        $source_name = sanitize_text_field((string) $source_name);
        $entries = [];

        foreach ($normalized as $item) {
            $id = $item['id'];
            $entries[$id] = [
                'id' => $id,
                'source_id' => $source_id !== '' ? $source_id : $item['source_id'],
                'source_name' => $source_name !== '' ? $source_name : $item['source_name'],
                'url' => $item['url'],
                'title' => $item['title'],
                'date' => $item['date'],
                'status' => 'new',
                'created' => time(),
                'material' => '',
            ];
        }

        return $entries;
    }

    /**
     * Merge multiple discovery engines while keeping one URL only.
     */
    public static function merge($groups, $limit = 10) {
        $all = [];
        if (!is_array($groups)) return [];

        foreach ($groups as $group) {
            if (!is_array($group)) continue;
            foreach ($group as $item) {
                if (!is_array($item)) continue;
                $url = self::normalize_url($item['url'] ?? '');
                if (!$url) continue;
                $all[md5(strtolower($url))] = $item;
            }
        }

        return self::normalize_items(array_values($all), $limit);
    }

    private static function normalize_url($url) {
        $url = esc_url_raw(trim((string) $url));
        if (!$url || !wp_http_validate_url($url)) return '';

        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) return '';
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) return '';

        return $url;
    }

    private static function sort_newest($a, $b) {
        $ta = strtotime((string) ($a['date'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['date'] ?? '')) ?: 0;
        if ($ta === $tb) return strcasecmp((string) $a['url'], (string) $b['url']);
        return $tb <=> $ta;
    }
}
}
