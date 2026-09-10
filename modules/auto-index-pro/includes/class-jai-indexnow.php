<?php
if (!defined('ABSPATH')) exit;
class JAI_Pro_IndexNow {
    public static function key() {
        $key = trim((string) get_option('jai_pro_indexnow_key', ''));
        if (!$key) {
            $key = '0ac8ea0a346b48afaa9c2afc5e59fc89';
            update_option('jai_pro_indexnow_key', $key, false);
        }
        return $key;
    }
    public static function key_location() { return JAI_Pro_Site::home_url() . 'japurai-indexnow-key.txt'; }
    public static function sync_key_file() {
        $path = trailingslashit(ABSPATH) . 'japurai-indexnow-key.txt';
        $key = self::key();
        if (@file_put_contents($path, $key . "\n", LOCK_EX) !== false) {
            @chmod($path, 0644);
            return true;
        }
        return false;
    }
    public static function verify_key_location() {
        $url = self::key_location();
        $r = wp_remote_get(add_query_arg('_jai_verify', time(), $url), ['timeout'=>15, 'redirection'=>3, 'headers'=>['Accept'=>'text/plain','Cache-Control'=>'no-cache']]);
        if (is_wp_error($r)) return ['ok'=>false,'code'=>0,'message'=>$r->get_error_message(),'url'=>$url];
        $code = (int) wp_remote_retrieve_response_code($r);
        $body = trim(wp_remote_retrieve_body($r));
        $ok = ($code === 200 && hash_equals(self::key(), $body));
        $message = $ok ? 'Key Location valid dan isinya sesuai dengan key aktif.' : ($code !== 200 ? 'Key Location mengembalikan HTTP '.$code.'.' : 'Isi Key Location tidak sama dengan key aktif.');
        return ['ok'=>$ok,'code'=>$code,'message'=>$message,'url'=>$url];
    }
    public static function submit($url) {
        $host=JAI_Pro_Site::host(); if(!$host) return ['ok'=>false,'code'=>0,'message'=>'Domain website tidak ditemukan.'];
        $endpoint='https://api.indexnow.org/indexnow';
        $body=['host'=>$host,'key'=>self::key(),'keyLocation'=>self::key_location(),'urlList'=>[$url]];
        $r=wp_remote_post($endpoint,['timeout'=>15,'headers'=>['Content-Type'=>'application/json; charset=utf-8'],'body'=>wp_json_encode($body),'data_format'=>'body']);
        if(is_wp_error($r)) return ['ok'=>false,'code'=>0,'message'=>$r->get_error_message()];
        $code=(int)wp_remote_retrieve_response_code($r); $msg=wp_remote_retrieve_body($r);
        return ['ok'=>($code>=200 && $code<300),'code'=>$code,'message'=>$msg ?: wp_remote_retrieve_response_message($r)];
    }
}
