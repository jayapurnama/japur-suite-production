<?php
if (!defined('ABSPATH')) exit;

class JAI_Pro_Site {
    public static function home_url() {
        return trailingslashit(home_url('/'));
    }

    public static function host() {
        return (string) wp_parse_url(self::home_url(), PHP_URL_HOST);
    }

    public static function origin() {
        $scheme = wp_parse_url(self::home_url(), PHP_URL_SCHEME);
        $host   = self::host();
        if (!$scheme || !$host) return self::home_url();
        $port = wp_parse_url(self::home_url(), PHP_URL_PORT);
        return $scheme . '://' . $host . ($port ? ':' . (int) $port : '');
    }

    public static function gsc_property_candidates() {
        $host = self::host();
        $candidates = [self::home_url()];
        if ($host) $candidates[] = 'sc-domain:' . $host;
        return array_values(array_unique($candidates));
    }
}
