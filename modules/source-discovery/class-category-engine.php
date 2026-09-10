<?php
/**
 * Module: JaPur Category Discovery Engine
 * Description: Isolated discovery engine for newest public WordPress posts by category, using REST API with RSS/Atom fallback.
 * Module Version: 0.2.0
 * Author: Japur Ganteng
 *
 * IMPORTANT: This prototype is isolated. It does not modify Source Sync v1.2.3,
 * core loading, Web Sumber UI, queue storage, or the production article workflow.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Category_Discovery_Engine')) {
final class JaPur_Category_Discovery_Engine {
    const VER = '0.2.0';
    const NONCE = 'jaf_nonce';
    const LIMIT = 10;

    public static function init() {
        add_action('wp_ajax_japur_category_discover', [__CLASS__, 'ajax_discover']);
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

    private static function request_json($url) {
        $r = wp_safe_remote_get($url, [
            'timeout' => 15,
            'redirection' => 3,
            'user-agent' => 'JaPur-Category-Discovery/' . self::VER,
        ]);
        if (is_wp_error($r)) return $r;
        $code = (int) wp_remote_retrieve_response_code($r);
        if ($code < 200 || $code >= 300) return new WP_Error('http', 'HTTP ' . $code);
        $body = wp_remote_retrieve_body($r);
        $data = json_decode($body, true);
        return is_array($data) ? $data : new WP_Error('json', 'Respons JSON tidak valid.');
    }

    private static function find_category_ids($base, $category) {
        $category = trim((string) $category);
        if ($category === '') return [];

        $url = $base . '/wp-json/wp/v2/categories?' . http_build_query([
            'search' => $category,
            'per_page' => 20,
            '_fields' => 'id,name,slug',
        ], '', '&');
        $data = self::request_json($url);
        if (is_wp_error($data)) return [];

        $needle = strtolower($category);
        $slug = sanitize_title($category);
        $ids = [];
        foreach ($data as $cat) {
            $name = strtolower(trim((string) ($cat['name'] ?? '')));
            $cat_slug = strtolower(trim((string) ($cat['slug'] ?? '')));
            if ($name === $needle || $cat_slug === $slug || ($needle !== '' && strpos($name, $needle) !== false)) {
                if (!empty($cat['id'])) $ids[] = absint($cat['id']);
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private static function rest($base, $category) {
        $host = self::host($base);
        $args = [
            'per_page' => self::LIMIT,
            'orderby' => 'date',
            'order' => 'desc',
            '_fields' => 'link,title,date,modified,categories',
        ];
        if (trim((string) $category) !== '') {
            $ids = self::find_category_ids($base, $category);
            if (!$ids) return [];
            $args['categories'] = implode(',', $ids);
        }

        $data = self::request_json($base . '/wp-json/wp/v2/posts?' . http_build_query($args, '', '&'));
        if (is_wp_error($data)) return [];

        $items = [];
        foreach ($data as $post) {
            if (!is_array($post)) continue;
            $url = self::same_host_url($post['link'] ?? '', $host);
            if (!$url) continue;
            $title = $post['title']['rendered'] ?? $post['title'] ?? '';
            $items[$url] = [
                'url' => $url,
                'title' => wp_strip_all_tags((string) $title),
                'date' => sanitize_text_field((string) ($post['date'] ?? $post['modified'] ?? '')),
                'source' => 'REST API',
            ];
        }
        return array_values($items);
    }

    private static function feed_candidates($base, $category) {
        $urls = [];
        $category = trim((string) $category);
        if ($category !== '') {
            $slug = sanitize_title($category);
            if ($slug !== '') {
                $urls[] = $base . '/category/' . rawurlencode($slug) . '/feed/';
                $urls[] = $base . '/feed/?cat=' . rawurlencode($slug);
            }
        }
        $urls[] = $base . '/feed/';
        $urls[] = $base . '/rss/';
        $urls[] = $base . '/atom.xml';
        return array_values(array_unique($urls));
    }

    private static function feed($base, $category) {
        $host = self::host($base);
        foreach (self::feed_candidates($base, $category) as $feed_url) {
            $r = wp_safe_remote_get($feed_url, [
                'timeout' => 12,
                'redirection' => 4,
                'user-agent' => 'JaPur-Category-Discovery/' . self::VER,
            ]);
            if (is_wp_error($r)) continue;
            $code = (int) wp_remote_retrieve_response_code($r);
            if ($code < 200 || $code >= 300) continue;
            $body = wp_remote_retrieve_body($r);
            if (!$body || !function_exists('simplexml_load_string')) continue;

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            libxml_clear_errors();
            if (!$xml) continue;

            $items = [];
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $url = self::same_host_url((string) $item->link, $host);
                    if (!$url) continue;
                    $items[$url] = [
                        'url' => $url,
                        'title' => sanitize_text_field((string) $item->title),
                        'date' => sanitize_text_field((string) $item->pubDate),
                        'source' => 'RSS',
                    ];
                }
            } elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $url = '';
                    foreach ($entry->link as $link) {
                        $attrs = $link->attributes();
                        if (isset($attrs['href'])) {
                            $url = self::same_host_url((string) $attrs['href'], $host);
                            if ($url) break;
                        }
                    }
                    if (!$url) continue;
                    $date = (string) ($entry->published ?: $entry->updated);
                    $items[$url] = [
                        'url' => $url,
                        'title' => sanitize_text_field((string) $entry->title),
                        'date' => sanitize_text_field($date),
                        'source' => 'Atom',
                    ];
                }
            }
            if ($items) return array_values($items);
        }
        return [];
    }

    private static function sort_newest($a, $b) {
        $ta = strtotime((string) ($a['date'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['date'] ?? '')) ?: 0;
        if ($ta === $tb) return strcasecmp((string) ($a['url'] ?? ''), (string) ($b['url'] ?? ''));
        return $tb <=> $ta;
    }

    public static function discover($site, $category = '') {
        $base = self::site_url($site);
        if (!$base) return new WP_Error('domain', 'Domain website tidak valid.');

        $items = self::rest($base, $category);
        $method = 'REST API';
        if (!$items) {
            $items = self::feed($base, $category);
            $method = 'RSS/Atom';
        }

        usort($items, [__CLASS__, 'sort_newest']);
        $items = array_slice($items, 0, self::LIMIT);

        if (class_exists('JaPur_Discovery_Adapter')) {
            $items = JaPur_Discovery_Adapter::normalize_items($items, self::LIMIT);
        }
        return ['items' => $items, 'method' => $items ? $method : ''];
    }

    public static function ajax_discover() {
        if (!self::auth()) return;
        $site = wp_unslash($_POST['url'] ?? '');
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $result = self::discover($site, $category);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        if (empty($result['items'])) wp_send_json_error(['message' => 'Artikel kategori tidak ditemukan melalui REST API maupun RSS/Atom.']);
        wp_send_json_success(['items' => $result['items'], 'method' => $result['method'], 'version' => self::VER]);
    }
}
}
