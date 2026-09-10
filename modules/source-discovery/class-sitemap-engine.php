<?php
/**
 * Module: JaPur Sitemap Discovery Engine
 * Description: Passive sitemap discovery engine derived from the JaPur Sitemap Hunter reader.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 *
 * IMPORTANT: This engine is intentionally passive. It registers no hooks, AJAX actions,
 * cron jobs, menus, database options, or UI. It does not alter Source Sync v1.2.3.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Sitemap_Discovery_Engine')) {
final class JaPur_Sitemap_Discovery_Engine {
    const VER = '0.1.0';
    const LIMIT = 10;
    const MAX_CHILDREN = 20;

    public static function discover($site, $sitemap = 'sitemap.xml', $limit = self::LIMIT) {
        $base = self::site_url($site);
        if (!$base) return new WP_Error('domain', 'Domain website tidak valid.');

        $allowed = ['sitemap.xml', 'wp-sitemap.xml', 'sitemap_index.xml', 'sitemap-posts.xml'];
        $sitemap = sanitize_text_field((string) $sitemap);
        if (!in_array($sitemap, $allowed, true)) $sitemap = 'sitemap.xml';

        $limit = max(1, min(200, absint($limit)));
        $primary = self::read_sitemap($base . '/' . $sitemap, $base);
        if (is_wp_error($primary)) return $primary;

        $items = [];
        if ($primary['root'] === 'urlset') {
            $items = self::post_items($primary['urls'], $base);
        } elseif ($primary['root'] === 'sitemapindex') {
            foreach (array_slice($primary['children'], 0, self::MAX_CHILDREN) as $child) {
                $child_url = self::same_host_url($child['loc'], self::host($base));
                if (!$child_url) continue;
                $parsed = self::read_sitemap($child_url, $base);
                if (is_wp_error($parsed) || $parsed['root'] !== 'urlset') continue;
                $items = array_merge($items, self::post_items($parsed['urls'], $base));
            }
        }

        if (class_exists('JaPur_Discovery_Adapter')) {
            $items = JaPur_Discovery_Adapter::normalize_items($items, $limit);
        } else {
            usort($items, [__CLASS__, 'sort_newest']);
            $items = array_slice($items, 0, $limit);
        }

        return [
            'items' => $items,
            'method' => $items ? 'Sitemap' : '',
            'sitemap' => $base . '/' . $sitemap,
            'root' => $primary['root'],
            'version' => self::VER,
        ];
    }

    private static function site_url($url) {
        $url = esc_url_raw(trim((string) $url));
        if (!$url || !wp_http_validate_url($url)) return '';
        $p = wp_parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) return '';
        if (!in_array(strtolower((string) $p['scheme']), ['http', 'https'], true)) return '';
        return untrailingslashit($p['scheme'] . '://' . $p['host']);
    }

    private static function host($url) {
        return strtolower(preg_replace('/^www\./i', '', (string) wp_parse_url($url, PHP_URL_HOST)));
    }

    private static function same_host_url($url, $host) {
        $url = esc_url_raw(trim((string) $url));
        if (!$url || !wp_http_validate_url($url)) return '';
        $p = wp_parse_url($url);
        if (!$p || !in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true)) return '';
        $item_host = strtolower(preg_replace('/^www\./i', '', (string) ($p['host'] ?? '')));
        if ($item_host === '' || $item_host !== $host) return '';
        return untrailingslashit($url);
    }

    private static function fetch($url) {
        $r = wp_safe_remote_get($url, [
            'timeout' => 12,
            'redirection' => 3,
            'limit_response_size' => 5 * 1024 * 1024,
            'headers' => ['Accept' => 'application/xml,text/xml,text/plain;q=0.9,*/*;q=0.5'],
            'user-agent' => 'JaPur-Sitemap-Discovery/' . self::VER,
        ]);
        if (is_wp_error($r)) return $r;
        $code = (int) wp_remote_retrieve_response_code($r);
        if ($code < 200 || $code >= 300) return new WP_Error('http', 'HTTP ' . $code);
        return wp_remote_retrieve_body($r);
    }

    private static function parse_xml($body) {
        if (!function_exists('simplexml_load_string')) return new WP_Error('simplexml', 'PHP SimpleXML tidak tersedia.');
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        if ($xml === false) return new WP_Error('xml', 'XML tidak valid.');
        return $xml;
    }

    private static function read_sitemap($url, $base) {
        $host = self::host($base);
        $url = self::same_host_url($url, $host);
        if (!$url) return new WP_Error('sitemap_url', 'Alamat sitemap tidak valid atau berbeda domain.');
        $body = self::fetch($url);
        if (is_wp_error($body)) return $body;
        $xml = self::parse_xml($body);
        if (is_wp_error($xml)) return $xml;

        $root = strtolower((string) $xml->getName());
        $out = ['root' => $root, 'urls' => [], 'children' => []];
        if ($root === 'urlset') {
            foreach ($xml->children() as $node) {
                if (strtolower((string) $node->getName()) !== 'url') continue;
                $loc = self::same_host_url((string) $node->loc, $host);
                if (!$loc) continue;
                $out['urls'][] = ['loc' => $loc, 'lastmod' => sanitize_text_field((string) $node->lastmod)];
            }
        } elseif ($root === 'sitemapindex') {
            foreach ($xml->children() as $node) {
                if (strtolower((string) $node->getName()) !== 'sitemap') continue;
                $loc = self::same_host_url((string) $node->loc, $host);
                if (!$loc) continue;
                $out['children'][] = ['loc' => $loc, 'lastmod' => sanitize_text_field((string) $node->lastmod)];
            }
        }
        return $out;
    }

    private static function post_items($urls, $base) {
        $host = self::host($base);
        $items = [];
        foreach ((array) $urls as $u) {
            $url = self::same_host_url($u['loc'] ?? '', $host);
            if (!$url || !self::post_like($url)) continue;
            $items[$url] = [
                'url' => $url,
                'title' => '',
                'date' => sanitize_text_field((string) ($u['lastmod'] ?? '')),
                'source' => 'Sitemap',
            ];
        }
        return array_values($items);
    }

    private static function post_like($url) {
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        if (!$path || $path === '/') return false;
        foreach (['/category/', '/tag/', '/author/', '/page/', '/feed/', '/search/', '/wp-content/', '/wp-admin/', '/attachment/'] as $bad) {
            if (strpos($path, $bad) !== false) return false;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext && !in_array($ext, ['html', 'htm', 'php'], true)) return false;
        return true;
    }

    private static function sort_newest($a, $b) {
        $ta = strtotime((string) ($a['date'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['date'] ?? '')) ?: 0;
        if ($ta === $tb) return strcasecmp((string) ($a['url'] ?? ''), (string) ($b['url'] ?? ''));
        return $tb <=> $ta;
    }
}
}
