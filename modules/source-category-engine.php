<?php
/**
 * Module: JaPur Category Source Engine
 * Description: Isolated, read-only discovery engine for public WordPress REST API and RSS/Atom category feeds.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 *
 * This module is intentionally isolated. It does not replace or modify Source Sync.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Japur_Category_Source_Engine')) {
class Japur_Category_Source_Engine {
    const NONCE = 'jaf_nonce';

    public static function init() {
        add_action('wp_ajax_jcse_discover', [__CLASS__, 'ajax_discover']);
    }

    private static function auth() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message'=>'Akses ditolak.'], 403);
            return false;
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['message'=>'Nonce tidak valid.'], 403);
            return false;
        }
        return true;
    }

    private static function site_url($url) {
        $url = esc_url_raw(trim((string)$url));
        if (!$url || !wp_http_validate_url($url)) return '';
        $p = wp_parse_url($url);
        if (empty($p['scheme']) || empty($p['host'])) return '';
        return untrailingslashit($p['scheme'].'://'.$p['host']);
    }

    private static function host($url) {
        return strtolower(preg_replace('/^www\./i', '', (string)wp_parse_url($url, PHP_URL_HOST)));
    }

    private static function public_url($url, $base_host) {
        $url = esc_url_raw((string)$url);
        if (!$url || !wp_http_validate_url($url)) return '';
        if (!in_array(strtolower((string)wp_parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)) return '';
        if (self::host($url) !== $base_host) return '';
        return untrailingslashit($url);
    }

    private static function rest($base, $category) {
        $endpoint = $base.'/wp-json/wp/v2/posts';
        $cat_ids = [];
        if ($category !== '') {
            $tax = $base.'/wp-json/wp/v2/categories?search='.rawurlencode($category).'&per_page=20';
            $r = wp_safe_remote_get($tax, ['timeout'=>12,'redirection'=>3,'user-agent'=>'JaPurCategoryEngine/0.1']);
            if (is_wp_error($r) || (int)wp_remote_retrieve_response_code($r) < 200 || (int)wp_remote_retrieve_response_code($r) >= 300) return [];
            $cats = json_decode(wp_remote_retrieve_body($r), true);
            if (!is_array($cats)) return [];
            foreach ($cats as $c) {
                if (empty($c['id'])) continue;
                $name = strtolower(trim((string)($c['name'] ?? '')));
                $slug = strtolower(trim((string)($c['slug'] ?? '')));
                $needle = strtolower(trim($category));
                if ($name === $needle || $slug === sanitize_title($category) || stripos($name, $needle) !== false) $cat_ids[] = (int)$c['id'];
            }
            $cat_ids = array_values(array_unique($cat_ids));
            if (!$cat_ids) return [];
        }
        $args = ['per_page'=>10,'orderby'=>'date','order'=>'desc','_fields'=>'link,title,date,modified,categories'];
        if ($cat_ids) $args['categories'] = implode(',', $cat_ids);
        $r = wp_safe_remote_get($endpoint.'?'.http_build_query($args, '', '&'), ['timeout'=>15,'redirection'=>3,'user-agent'=>'JaPurCategoryEngine/0.1']);
        if (is_wp_error($r) || (int)wp_remote_retrieve_response_code($r) < 200 || (int)wp_remote_retrieve_response_code($r) >= 300) return [];
        $posts = json_decode(wp_remote_retrieve_body($r), true);
        if (!is_array($posts)) return [];
        $out = [];
        foreach ($posts as $post) {
            $url = self::public_url($post['link'] ?? '', self::host($base));
            if (!$url) continue;
            $out[$url] = [
                'url'=>$url,
                'title'=>wp_strip_all_tags($post['title']['rendered'] ?? $post['title'] ?? ''),
                'date'=>sanitize_text_field($post['date'] ?? ''),
                'source'=>'REST API',
            ];
        }
        return array_values($out);
    }

    private static function feed_urls($base, $category) {
        $feeds = [];
        if ($category !== '') {
            $slug = sanitize_title($category);
            $feeds[] = $base.'/category/'.$slug.'/feed/';
            $feeds[] = $base.'/'.$slug.'/feed/';
            $feeds[] = $base.'/?cat='.rawurlencode($slug);
        }
        $feeds[] = $base.'/feed/';
        $feeds[] = $base.'/rss/';
        $feeds[] = $base.'/atom.xml';
        return array_values(array_unique($feeds));
    }

    private static function feed($base, $category) {
        $host = self::host($base);
        foreach (self::feed_urls($base, $category) as $feed_url) {
            $r = wp_safe_remote_get($feed_url, ['timeout'=>12,'redirection'=>4,'user-agent'=>'JaPurCategoryEngine/0.1']);
            if (is_wp_error($r) || (int)wp_remote_retrieve_response_code($r) < 200 || (int)wp_remote_retrieve_response_code($r) >= 300) continue;
            $body = wp_remote_retrieve_body($r);
            if (!$body) continue;
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET|LIBXML_NOCDATA);
            libxml_clear_errors();
            if (!$xml) continue;
            $out = [];
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $url = self::public_url((string)$item->link, $host);
                    if (!$url) continue;
                    $out[$url] = ['url'=>$url,'title'=>sanitize_text_field((string)$item->title),'date'=>sanitize_text_field((string)$item->pubDate),'source'=>'RSS'];
                }
            } elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $url = '';
                    foreach ($entry->link as $ln) {
                        $a = $ln->attributes();
                        if (isset($a['href'])) { $url = self::public_url((string)$a['href'], $host); if ($url) break; }
                    }
                    if (!$url) continue;
                    $date = (string)($entry->published ?: $entry->updated);
                    $out[$url] = ['url'=>$url,'title'=>sanitize_text_field((string)$entry->title),'date'=>sanitize_text_field($date),'source'=>'Atom'];
                }
            }
            if ($out) return array_values($out);
        }
        return [];
    }

    public static function ajax_discover() {
        if (!self::auth()) return;
        $base = self::site_url(wp_unslash($_POST['url'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        if (!$base) wp_send_json_error(['message'=>'Domain website tidak valid.']);
        $items = self::rest($base, $category);
        if (!$items) $items = self::feed($base, $category);
        usort($items, function($a,$b){ return (strtotime((string)($b['date'] ?? '')) ?: 0) <=> (strtotime((string)($a['date'] ?? '')) ?: 0); });
        wp_send_json_success(['items'=>array_slice($items, 0, 10), 'method'=>$items ? ($items[0]['source'] ?? '') : '']);
    }
}
Japur_Category_Source_Engine::init();
}
