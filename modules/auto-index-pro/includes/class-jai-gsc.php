<?php
if (!defined('ABSPATH')) exit;

class JAI_Pro_GSC {
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const API_BASE = 'https://www.googleapis.com/webmasters/v3';
    const INSPECT_BASE = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
    const SCOPE = 'https://www.googleapis.com/auth/webmasters https://www.googleapis.com/auth/blogger';

    public static function client_id() { return trim((string) get_option('jai_pro_gsc_client_id', '')); }
    public static function client_secret() { return trim((string) get_option('jai_pro_gsc_client_secret', '')); }
    public static function redirect_uri() { return admin_url('admin-post.php?action=jai_pro_gsc_callback'); }
    public static function site_url() { return JAI_Pro_Site::home_url(); }
    public static function site_host() { return JAI_Pro_Site::host(); }
    public static function connected() { return (bool) get_option('jai_pro_gsc_refresh_token', ''); }
    public static function access_token() { return trim((string) get_option('jai_pro_gsc_access_token', '')); }
    public static function property() { return trim((string) get_option('jai_pro_gsc_property', '')); }
    public static function sitemap() { return trim((string) get_option('jai_pro_gsc_sitemap', '')); }

    /** Shared Google credential hub for all Japur Suite modules. */
    public static function blogger_access_token($force_refresh = false) {
        if (!self::client_id() || !self::client_secret()) return new WP_Error('jai_google_credentials', 'Google Client ID/Secret Auto Index belum lengkap.');
        if (!$force_refresh) {
            $token = self::token();
            if ($token) return $token;
        }
        if (!self::refresh_access_token()) {
            return new WP_Error('jai_google_reauth', 'Token Google bersama tidak dapat diperbarui. Hubungkan ulang Google dari Auto Index PRO agar scope Blogger ikut diberikan.');
        }
        return self::access_token();
    }

    public static function has_blogger_scope() {
        return (bool) get_option('jai_pro_google_shared_v1', false);
    }

    public static function disconnect() {
        $token = self::access_token();
        if ($token) {
            wp_remote_post('https://oauth2.googleapis.com/revoke', [
                'timeout' => 10,
                'body' => ['token' => $token],
            ]);
        }
        delete_option('jai_pro_gsc_access_token');
        delete_option('jai_pro_gsc_refresh_token');
        delete_option('jai_pro_gsc_token_expires');
        delete_option('jai_pro_gsc_account');
        delete_option('jai_pro_gsc_property');
        delete_option('jai_pro_gsc_sitemap');
    }

    public static function auth_url() {
        if (!self::client_id() || !self::client_secret()) return '';
        $user_id = get_current_user_id();
        $state = wp_generate_password(48, false, false);
        set_transient('jai_gsc_state_' . $user_id, $state, 10 * MINUTE_IN_SECONDS);
        $args = [
            'client_id' => self::client_id(),
            'redirect_uri' => self::redirect_uri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];
        return add_query_arg($args, self::AUTH_URL);
    }

    public static function handle_callback() {
        if (!current_user_can('manage_options')) wp_die('Akses ditolak.');
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $expected = get_transient('jai_gsc_state_' . get_current_user_id());
        delete_transient('jai_gsc_state_' . get_current_user_id());
        if (!$state || !$expected || !hash_equals($expected, $state)) self::redirect_notice('Google OAuth gagal: state tidak valid.', 'error');
        if (!$code) {
            $error = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : 'Persetujuan Google dibatalkan.';
            self::redirect_notice('Google OAuth gagal: ' . $error, 'error');
        }
        $r = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 20,
            'body' => [
                'code' => $code,
                'client_id' => self::client_id(),
                'client_secret' => self::client_secret(),
                'redirect_uri' => self::redirect_uri(),
                'grant_type' => 'authorization_code',
            ],
        ]);
        if (is_wp_error($r)) self::redirect_notice('Google OAuth gagal: ' . $r->get_error_message(), 'error');
        $code_http = (int) wp_remote_retrieve_response_code($r);
        $data = json_decode(wp_remote_retrieve_body($r), true);
        if ($code_http < 200 || $code_http >= 300 || empty($data['access_token'])) {
            $msg = !empty($data['error_description']) ? $data['error_description'] : 'Token Google tidak diterima.';
            self::redirect_notice('Google OAuth gagal: ' . sanitize_text_field($msg), 'error');
        }
        update_option('jai_pro_gsc_access_token', sanitize_text_field($data['access_token']), false);
        if (!empty($data['refresh_token'])) update_option('jai_pro_gsc_refresh_token', sanitize_text_field($data['refresh_token']), false);
        update_option('jai_pro_google_shared_v1', 1, false);
        update_option('jai_pro_gsc_token_expires', time() + max(60, (int) ($data['expires_in'] ?? 3600) - 60), false);
        self::redirect_notice('Google Search Console berhasil terhubung.', 'success');
    }

    private static function redirect_notice($message, $type = 'success') {
        wp_safe_redirect(add_query_arg([
            'page' => 'japur-auto-index-pro',
            'jai_gsc_msg' => rawurlencode($message),
            'jai_gsc_type' => $type,
        ], admin_url('admin.php')));
        exit;
    }

    private static function refresh_access_token() {
        $refresh = self::client_secret() && get_option('jai_pro_gsc_refresh_token', '');
        if (!$refresh) return false;
        $r = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 20,
            'body' => [
                'client_id' => self::client_id(),
                'client_secret' => self::client_secret(),
                'refresh_token' => get_option('jai_pro_gsc_refresh_token', ''),
                'grant_type' => 'refresh_token',
            ],
        ]);
        if (is_wp_error($r)) return false;
        $code = (int) wp_remote_retrieve_response_code($r);
        $data = json_decode(wp_remote_retrieve_body($r), true);
        if ($code < 200 || $code >= 300 || empty($data['access_token'])) return false;
        update_option('jai_pro_gsc_access_token', sanitize_text_field($data['access_token']), false);
        update_option('jai_pro_gsc_token_expires', time() + max(60, (int) ($data['expires_in'] ?? 3600) - 60), false);
        return true;
    }

    private static function token() {
        if (!self::access_token()) return '';
        if ((int) get_option('jai_pro_gsc_token_expires', 0) <= time()) {
            if (!self::refresh_access_token()) return '';
        }
        return self::access_token();
    }

    private static function request($method, $url, $body = null) {
        $token = self::token();
        if (!$token) return new WP_Error('jai_gsc_auth', 'Google Search Console belum terhubung atau token tidak valid.');
        $args = [
            'method' => strtoupper($method),
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ];
        if ($body !== null) {
            $json_body = wp_json_encode($body);
            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $args['headers']['Content-Length'] = (string) strlen($json_body);
            $args['body'] = $json_body;
        } else {
            // Google dapat menolak request tanpa body dengan HTTP 411.
            // Tetap kirim Content-Length: 0 untuk PUT/POST tanpa body.
            $args['headers']['Content-Length'] = '0';
            $args['body'] = '';
        }
        $r = wp_remote_request($url, $args);
        if (is_wp_error($r)) return $r;
        $code = (int) wp_remote_retrieve_response_code($r);
        $raw = wp_remote_retrieve_body($r);
        $data = json_decode($raw, true);
        if ($code === 401 && self::refresh_access_token()) return self::request($method, $url, $body);
        if ($code < 200 || $code >= 300) {
            $message = !empty($data['error']['message']) ? $data['error']['message'] : ($raw ?: wp_remote_retrieve_response_message($r));
            return new WP_Error('jai_gsc_http_' . $code, sanitize_text_field($message), ['status' => $code, 'body' => $raw]);
        }
        return ['code' => $code, 'data' => is_array($data) ? $data : [], 'raw' => $raw];
    }

    public static function sites() {
        $r = self::request('GET', self::API_BASE . '/sites');
        if (is_wp_error($r)) return $r;
        return $r['data']['siteEntry'] ?? [];
    }

    public static function sitemaps($site) {
        if (!$site) return [];
        $url = self::API_BASE . '/sites/' . rawurlencode($site) . '/sitemaps';
        $r = self::request('GET', $url);
        if (is_wp_error($r)) return $r;
        return $r['data']['sitemap'] ?? [];
    }

    public static function submit_sitemap($site, $sitemap) {
        if (!$site || !$sitemap) return new WP_Error('jai_gsc_missing', 'Property dan sitemap wajib diisi.');
        $url = self::API_BASE . '/sites/' . rawurlencode($site) . '/sitemaps/' . rawurlencode($sitemap);
        return self::request('PUT', $url);
    }

    public static function inspect($site, $url) {
        if (!$site || !$url) return new WP_Error('jai_gsc_missing', 'Property dan URL wajib diisi.');
        return self::request('POST', self::INSPECT_BASE, [
            'inspectionUrl' => esc_url_raw($url),
            'siteUrl' => $site,
            'languageCode' => 'id-ID',
        ]);
    }

    public static function analytics($site, $days = 28) {
        if (!$site) return new WP_Error('jai_gsc_missing', 'Property belum dipilih.');
        $days = max(1, min(90, (int) $days));
        $end = current_time('Y-m-d', true);
        $start = gmdate('Y-m-d', time() - (($days - 1) * DAY_IN_SECONDS));
        return self::request('POST', self::API_BASE . '/sites/' . rawurlencode($site) . '/searchAnalytics/query', [
            'startDate' => $start,
            'endDate' => $end,
            'rowLimit' => 1,
        ]);
    }

    public static function discover_default_sitemap() {
        $candidates = [JAI_Pro_Site::home_url() . 'wp-sitemap.xml', JAI_Pro_Site::home_url() . 'sitemap_index.xml'];
        foreach ($candidates as $url) {
            $r = wp_remote_head($url, ['timeout' => 10, 'redirection' => 3]);
            if (!is_wp_error($r) && (int) wp_remote_retrieve_response_code($r) >= 200 && (int) wp_remote_retrieve_response_code($r) < 400) return $url;
        }
        return JAI_Pro_Site::home_url() . 'wp-sitemap.xml';
    }
}
