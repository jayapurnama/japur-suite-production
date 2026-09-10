<?php
/**
 * Plugin Name: JaPur Sitemap Hunter
 * Description: Discovery dan pembacaan sitemap untuk menemukan URL artikel/post. Mendukung sitemap.xml, wp-sitemap.xml, sitemap_index.xml, sitemap-posts.xml dan follow sitemap index.
 * Version: 2.0.1
 * Author: Japur Ganteng
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

final class JaPur_Sitemap_Hunter {
    const VER = '2.0.1';
    const NONCE = 'japur_sh_nonce';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_japur_sh_test_domain', [$this, 'ajax_test_domain']);
    }

    public function admin_menu() {
        add_management_page('JaPur Sitemap Hunter', 'Sitemap Hunter', 'manage_options', 'japur-sitemap-hunter', [$this, 'render']);
    }

    public function enqueue($hook) {
        if ($hook !== 'tools_page_japur-sitemap-hunter') return;
        wp_enqueue_script('jquery');
    }

    private function allowed_url($url) {
        $url = esc_url_raw(trim((string)$url));
        if (!$url || !wp_http_validate_url($url)) return '';
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return '';
        return $url;
    }

    private function fetch($url) {
        $url = $this->allowed_url($url);
        if (!$url) return new WP_Error('invalid_url', 'URL tidak valid.');
        $response = wp_safe_remote_get($url, [
            'timeout' => 20,
            'redirection' => 4,
            'user-agent' => 'JaPur-Sitemap-Hunter/' . self::VER,
        ]);
        if (is_wp_error($response)) return $response;
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) return new WP_Error('http_error', 'HTTP ' . $code);
        $body = wp_remote_retrieve_body($response);
        if ($body === '') return new WP_Error('empty', 'Respons kosong.');
        return $body;
    }

    private function xml($body) {
        if (!function_exists('simplexml_load_string')) return new WP_Error('simplexml_missing', 'SimpleXML tidak tersedia.');
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        if (!$xml) return new WP_Error('invalid_xml', 'Format XML sitemap tidak valid.');
        return $xml;
    }

    private function children($xml, $local) {
        $nodes = $xml->xpath('/*[local-name()="' . $local . '"]/*[local-name()="' . ($local === 'sitemapindex' ? 'sitemap' : 'url') . '"]');
        return is_array($nodes) ? $nodes : [];
    }

    private function post_like($url) {
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        return !preg_match('~(^|[-_/])(page|pages|category|categories|tag|tags|author|attachment|media|image|images|product|products|taxonomy|taxonomies)([-_/]|\.|$)~i', $path);
    }

    private function candidate_sitemaps($domain) {
        $domain = untrailingslashit($domain);
        return [
            $domain . '/wp-sitemap.xml',
            $domain . '/sitemap_index.xml',
            $domain . '/sitemap-index.xml',
            $domain . '/sitemap.xml',
            $domain . '/sitemap-posts.xml',
            $domain . '/sitemap_posts.xml',
            $domain . '/sitemap_news.xml',
            $domain . '/sitemap_web.xml',
        ];
    }

    private function discover_sitemaps($domain, &$visited = [], $depth = 0) {
        if ($depth > 3) return [];
        $found = [];
        foreach ($this->candidate_sitemaps($domain) as $url) {
            $key = md5(strtolower($url));
            if (isset($visited[$key])) continue;
            $visited[$key] = true;
            $body = $this->fetch($url);
            if (is_wp_error($body)) continue;
            $xml = $this->xml($body);
            if (is_wp_error($xml)) continue;
            $root = strtolower((string) $xml->getName());
            if ($root === 'urlset') {
                $found[] = $url;
                continue;
            }
            if ($root !== 'sitemapindex') continue;
            foreach ($this->children($xml, 'sitemapindex') as $node) {
                $loc = $this->allowed_url((string) $node->loc);
                if (!$loc) continue;
                if ($this->post_like($loc)) {
                    $found[] = $loc;
                } elseif (preg_match('~(sitemap[_-]?index|wp-sitemap)~i', (string) wp_parse_url($loc, PHP_URL_PATH))) {
                    $child_domain = untrailingslashit((string) wp_parse_url($loc, PHP_URL_SCHEME) . '://' . (string) wp_parse_url($loc, PHP_URL_HOST));
                    if ($child_domain) $found = array_merge($found, $this->discover_sitemaps($child_domain, $visited, $depth + 1));
                }
            }
            if ($found) break;
        }
        return array_values(array_unique($found));
    }

    private function read_sitemap($url, $limit = 50) {
        $body = $this->fetch($url);
        if (is_wp_error($body)) return [];
        $xml = $this->xml($body);
        if (is_wp_error($xml) || strtolower((string) $xml->getName()) !== 'urlset') return [];
        $items = [];
        foreach ($this->children($xml, 'urlset') as $node) {
            $loc = $this->allowed_url((string) $node->loc);
            if (!$loc || !$this->post_like($loc)) continue;
            $lastmod = sanitize_text_field((string) $node->lastmod);
            $items[$loc] = ['url' => $loc, 'lastmod' => $lastmod];
        }
        usort($items, function ($a, $b) {
            return (strtotime($b['lastmod']) ?: 0) <=> (strtotime($a['lastmod']) ?: 0);
        });
        return array_slice($items, 0, max(1, min(200, (int) $limit)));
    }

    private function discover($domain, $limit = 50) {
        $domain = $this->allowed_url($domain);
        if (!$domain) return new WP_Error('domain', 'Domain tidak valid.');
        $parts = wp_parse_url($domain);
        $root = strtolower(($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? ''));
        $visited = [];
        $sitemaps = $this->discover_sitemaps($root, $visited);
        if (!$sitemaps) return new WP_Error('not_found', 'Sitemap artikel tidak ditemukan.');
        $all = [];
        foreach ($sitemaps as $sitemap) {
            foreach ($this->read_sitemap($sitemap, $limit) as $item) $all[$item['url']] = $item;
            if (count($all) >= $limit) break;
        }
        $all = array_values($all);
        usort($all, function ($a, $b) {
            return (strtotime($b['lastmod']) ?: 0) <=> (strtotime($a['lastmod']) ?: 0);
        });
        return array_slice($all, 0, max(1, min(200, (int) $limit)));
    }

    public function ajax_test_domain() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Akses ditolak.'], 403);
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) wp_send_json_error(['message' => 'Nonce tidak valid.'], 403);
        $domain = wp_unslash($_POST['domain'] ?? '');
        $limit = absint($_POST['limit'] ?? 50);
        $items = $this->discover($domain, $limit);
        if (is_wp_error($items)) wp_send_json_error(['message' => $items->get_error_message()]);
        wp_send_json_success(['items' => $items, 'version' => self::VER]);
    }

    public function render() {
        if (!current_user_can('manage_options')) return;
        $nonce = wp_create_nonce(self::NONCE);
        ?>
        <div class="wrap">
            <h1>JaPur Sitemap Hunter <small>v<?php echo esc_html(self::VER); ?></small></h1>
            <p>Discovery aman untuk mencari URL artikel/post dari sitemap publik.</p>
            <table class="form-table"><tr><th><label for="japur-sh-domain">Domain</label></th><td><input id="japur-sh-domain" class="regular-text" type="url" placeholder="https://contoh.com"></td></tr><tr><th>Jumlah kandidat</th><td><select id="japur-sh-limit"><option value="10">10</option><option value="25">25</option><option value="50" selected>50</option><option value="100">100</option><option value="200">200</option></select></td></tr></table>
            <p><button type="button" class="button button-primary" id="japur-sh-test">Quick Test</button> <span id="japur-sh-status"></span></p>
            <div id="japur-sh-results"></div>
        </div>
        <script>
        jQuery(function($){
            $('#japur-sh-test').on('click',function(){
                var domain=$('#japur-sh-domain').val(), limit=$('#japur-sh-limit').val();
                $('#japur-sh-status').text('Mencari sitemap...'); $('#japur-sh-results').empty();
                $.post(ajaxurl,{action:'japur_sh_test_domain',nonce:'<?php echo esc_js($nonce); ?>',domain:domain,limit:limit})
                .done(function(r){
                    if(!r.success){$('#japur-sh-status').text(r.data&&r.data.message?r.data.message:'Gagal.');return;}
                    var rows=r.data.items||[], html='<table class="widefat striped"><thead><tr><th>URL Artikel</th><th>Lastmod</th></tr></thead><tbody>';
                    rows.forEach(function(x){html+='<tr><td><a href="'+$('<div>').text(x.url).html()+'" target="_blank" rel="noopener">'+$('<div>').text(x.url).html()+'</a></td><td>'+$('<div>').text(x.lastmod||'').html()+'</td></tr>';});
                    html+='</tbody></table>'; $('#japur-sh-results').html(html); $('#japur-sh-status').text(rows.length+' kandidat ditemukan.');
                }).fail(function(){ $('#japur-sh-status').text('Request gagal.'); });
            });
        });
        </script>
        <?php
    }
}

new JaPur_Sitemap_Hunter();
