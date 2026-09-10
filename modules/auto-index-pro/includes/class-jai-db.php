<?php
if (!defined('ABSPATH')) exit;
class JAI_Pro_DB {
    public static function table() { global $wpdb; return $wpdb->prefix . 'japurai_queue'; }
    public static function logs_table() { global $wpdb; return $wpdb->prefix . 'japurai_logs'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql1 = "CREATE TABLE " . self::table() . " (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, url text NOT NULL, post_id bigint(20) unsigned NOT NULL DEFAULT 0, status varchar(20) NOT NULL DEFAULT 'pending', attempts tinyint(3) unsigned NOT NULL DEFAULT 0, next_attempt datetime NULL, last_error text NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY(id), KEY status(status), KEY post_id(post_id)) $charset;";
        $sql2 = "CREATE TABLE " . self::logs_table() . " (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, url text NOT NULL, post_id bigint(20) unsigned NOT NULL DEFAULT 0, provider varchar(40) NOT NULL, status varchar(20) NOT NULL, response_code int NOT NULL DEFAULT 0, message text NULL, created_at datetime NOT NULL, PRIMARY KEY(id), KEY provider(provider), KEY status(status)) $charset;";
        dbDelta($sql1); dbDelta($sql2);
        update_option('jai_pro_db_version', JAI_PRO_DB_VERSION);
    }
    public static function enqueue($url, $post_id=0) {
        global $wpdb; $now=current_time('mysql');
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".self::table()." WHERE url=%s AND status IN ('pending','processing') LIMIT 1", $url));
        if ($exists) return false;
        return (bool)$wpdb->insert(self::table(), ['url'=>$url,'post_id'=>(int)$post_id,'status'=>'pending','attempts'=>0,'next_attempt'=>$now,'created_at'=>$now,'updated_at'=>$now], ['%s','%d','%s','%d','%s','%s','%s']);
    }
    public static function pending($limit=10) { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM ".self::table()." WHERE status='pending' AND (next_attempt IS NULL OR next_attempt <= %s) ORDER BY id ASC LIMIT %d", current_time('mysql'), $limit)); }
    public static function mark($id,$status,$attempts=0,$error='') { global $wpdb; $wpdb->update(self::table(), ['status'=>$status,'attempts'=>$attempts,'last_error'=>$error,'updated_at'=>current_time('mysql')], ['id'=>(int)$id], ['%s','%d','%s','%s'], ['%d']); }
    public static function retry($id,$attempts,$error) { global $wpdb; $delay=min(86400, max(300, (int)pow(2, $attempts)*60)); $next=gmdate('Y-m-d H:i:s', time()+$delay); $wpdb->update(self::table(), ['status'=>'pending','attempts'=>$attempts,'last_error'=>$error,'next_attempt'=>$next,'updated_at'=>current_time('mysql')], ['id'=>(int)$id], ['%s','%d','%s','%s','%s'], ['%d']); }
    public static function log($url,$post_id,$provider,$status,$code,$message='') { global $wpdb; $wpdb->insert(self::logs_table(), ['url'=>$url,'post_id'=>(int)$post_id,'provider'=>$provider,'status'=>$status,'response_code'=>(int)$code,'message'=>wp_strip_all_tags($message),'created_at'=>current_time('mysql')]); }
    public static function recent_logs($limit=5, $offset=0, $provider='') {
        global $wpdb;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        if ($provider !== '') {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM ".self::logs_table()." WHERE provider=%s ORDER BY id DESC LIMIT %d OFFSET %d", $provider, $limit, $offset));
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM ".self::logs_table()." ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset));
    }
    public static function logs_total($provider='') {
        global $wpdb;
        if ($provider !== '') {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".self::logs_table()." WHERE provider=%s", $provider));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM ".self::logs_table());
    }
    public static function update_latest_gsc_inspection_log($url, $post_id, $status, $code, $message) {
        global $wpdb;
        $table = self::logs_table();
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE provider=%s AND url=%s AND post_id=%d AND message LIKE %s ORDER BY id DESC LIMIT 1",
            'Google Search Console', $url, (int)$post_id, 'Auto URL Inspection:%'
        ));
        $data = [
            'status' => sanitize_key($status),
            'response_code' => (int)$code,
            'message' => wp_strip_all_tags($message),
        ];
        if ($id) {
            return (bool)$wpdb->update($table, $data, ['id'=>(int)$id], ['%s','%d','%s'], ['%d']);
        }
        return (bool)$wpdb->insert($table, [
            'url'=>$url, 'post_id'=>(int)$post_id, 'provider'=>'Google Search Console',
            'status'=>sanitize_key($status), 'response_code'=>(int)$code,
            'message'=>wp_strip_all_tags($message), 'created_at'=>current_time('mysql')
        ], ['%s','%d','%s','%s','%d','%s','%s']);
    }
    public static function clear_logs() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE ".self::logs_table());
    }
    public static function counts() { global $wpdb; $rows=$wpdb->get_results("SELECT status,COUNT(*) c FROM ".self::table()." GROUP BY status", ARRAY_A); $out=['pending'=>0,'processing'=>0,'success'=>0,'failed'=>0]; foreach($rows as $r) { if(isset($out[$r['status']])) $out[$r['status']] = (int)$r['c']; } return $out; }
}
