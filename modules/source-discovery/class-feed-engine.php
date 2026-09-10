<?php
/**
 * Module: JaPur Feed Discovery Engine
 * Description: Isolated RSS/Atom discovery engine for newest public article URLs.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 *
 * IMPORTANT: Passive prototype. No hooks, AJAX, cron, menus, options, or Source Sync changes.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Feed_Discovery_Engine')) {
final class JaPur_Feed_Discovery_Engine {
    const VER = '0.1.0';

    public static function discover($site_url, $category = '', $limit = 10) {
        $base = self::site_url($site_url);
        if (!$base) return new WP_Error('domain', 'Domain website tidak valid.');
        $host = self::host($base);
        $category = sanitize_text_field((string) $category);
        $feeds = self::feed_urls($base, $category);
        $items = [];
        foreach ($feeds as $feed_url) {
            $parsed = self::read_feed($feed_url, $host);
            if (!$parsed) continue;
            foreach ($parsed as $item) $items[$item['url']] = $item;
            if (count($items) >= 50) break;
        }
        $items = array_values($items);
        usort($items, [__CLASS__, 'sort_newest']);
        $limit = max(1, min(200, absint($limit ?: 10)));
        return array_slice($items, 0, $limit);
    }

    private static function site_url($url) {
        $url = esc_url_raw(trim((string) $url));
        if (!$url || !wp_http_validate_url($url)) return '';
        $p = wp_parse_url($url);
        if (empty($p['scheme']) || empty($p['host'])) return '';
        if (!in_array(strtolower($p['scheme']), ['http','https'], true)) return '';
        return untrailingslashit($p['scheme'].'://'.$p['host']);
    }

    private static function host($url) {
        return strtolower(preg_replace('/^www\./i', '', (string) wp_parse_url($url, PHP_URL_HOST)));
    }

    private static function public_url($url, $host) {
        $url = esc_url_raw(trim((string) $url));
        if (!$url || !wp_http_validate_url($url)) return '';
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http','https'], true)) return '';
        return self::host($url) === $host ? untrailingslashit($url) : '';
    }

    private static function feed_urls($base, $category) {
        $out = [];
        if ($category !== '') {
            $slug = sanitize_title($category);
            $out[] = $base.'/category/'.$slug.'/feed/';
            $out[] = $base.'/'.$slug.'/feed/';
        }
        $out[] = $base.'/feed/';
        $out[] = $base.'/rss/';
        $out[] = $base.'/atom.xml';
        return array_values(array_unique($out));
    }

    private static function read_feed($url, $host) {
        $r = wp_safe_remote_get($url, ['timeout'=>12, 'redirection'=>4, 'user-agent'=>'JaPurFeedDiscovery/0.1']);
        if (is_wp_error($r)) return [];
        $code = (int) wp_remote_retrieve_response_code($r);
        if ($code < 200 || $code >= 300) return [];
        $body = wp_remote_retrieve_body($r);
        if ($body === '' || strlen($body) > 5242880) return [];
        if (!function_exists('simplexml_load_string')) return [];
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET|LIBXML_NOCDATA);
        libxml_clear_errors();
        if (!$xml) return [];

        $out = [];
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $link = self::public_url((string) $item->link, $host);
                if (!$link) continue;
                $out[$link] = [
                    'url'=>$link,
                    'title'=>sanitize_text_field((string) $item->title),
                    'date'=>sanitize_text_field((string) $item->pubDate),
                    'source'=>'RSS',
                ];
            }
        } else {
            $entries = $xml->entry;
            if ($entries) foreach ($entries as $entry) {
                $link = '';
                foreach ($entry->link as $ln) {
                    $attr = $ln->attributes();
                    $candidate = isset($attr['href']) ? (string) $attr['href'] : (string) $ln;
                    $link = self::public_url($candidate, $host);
                    if ($link) break;
                }
                if (!$link) continue;
                $date = (string) ($entry->published ?: $entry->updated);
                $out[$link] = [
                    'url'=>$link,
                    'title'=>sanitize_text_field((string) $entry->title),
                    'date'=>sanitize_text_field($date),
                    'source'=>'Atom',
                ];
            }
        }
        return array_values($out);
    }

    private static function sort_newest($a, $b) {
        $ta = strtotime((string) ($a['date'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['date'] ?? '')) ?: 0;
        if ($ta === $tb) return strcasecmp((string) ($a['url'] ?? ''), (string) ($b['url'] ?? ''));
        return $tb <=> $ta;
    }
}
}
