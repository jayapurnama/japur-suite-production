<?php
/**
 * Module: Japur OpenAI Cost
 * Description: Pencatat token dan estimasi biaya OpenAI per request dan per artikel.
 * Module Version: 1.3.1
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Japur_OpenAI_Cost')) {
class Japur_OpenAI_Cost {
    const OPT = 'japur_openai_cost_settings';
    const TABLE_SUFFIX = 'japur_openai_cost_log';
    const DB_VERSION = '3';

    public static function init() {
        add_action('japur_openai_cost_event', [__CLASS__, 'record'], 10, 1);
        add_action('admin_init', [__CLASS__, 'maybe_install']);
    }

    public static function maybe_install() {
        if (!current_user_can('manage_options')) return;
        if (get_option(self::OPT . '_db_version') !== self::DB_VERSION) self::install();
        self::maybe_refresh_exchange_rate();
    }

    private static function default_prices() {
        // OpenAI standard API pricing, USD per 1M tokens.
        // Keep these as safe defaults; users can still override them if their
        // account/processing mode uses a different rate.
        return [
            'gpt-5.6-luna' => [
                'input' => 0.20,
                'cached' => 0.02,
                'output' => 1.20,
            ],
            'gpt-image-2' => [
                // GPT Image 2: text input is billed separately from image output.
                // The extractor currently reports aggregate input/output usage,
                // so standard input/output rates are used for the estimator.
                'input' => 5.00,
                'cached' => 1.25,
                'output' => 30.00,
            ],
        ];
    }

    private static function install() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            kind varchar(20) NOT NULL DEFAULT 'article',
            model varchar(120) NOT NULL DEFAULT '',
            article_title text NULL,
            article_words bigint(20) unsigned NOT NULL DEFAULT 0,
            response_id varchar(120) NOT NULL DEFAULT '',
            input_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
            cached_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
            output_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
            total_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
            cost_usd decimal(20,10) NOT NULL DEFAULT 0,
            cost_idr decimal(20,2) NOT NULL DEFAULT 0,
            meta longtext NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY article_title (article_title(100)),
            KEY model (model)
        ) {$charset};";
        dbDelta($sql);

        $s = get_option(self::OPT, []);
        if (!is_array($s)) $s = [];
        $had_cost_mode = array_key_exists('cost_mode', $s);
        $defaults = [
            'usd_idr' => 0,
            'usd_idr_source' => '',
            'usd_idr_updated' => 0,
            'prices' => self::default_prices(),
            'cost_saver_enabled' => 1,
            'cost_mode' => '',
            'custom_output_tokens' => 4000,
            'custom_word_min' => 400,
            'custom_word_max' => 700,
            'custom_image_quality' => 'medium',
                        'openai_billing_last_sync' => 0,
            'openai_billing_month_usd' => 0,
            'openai_billing_30d_usd' => 0,
            'openai_billing_status' => '',
        ];
        $s = wp_parse_args($s, $defaults);
        // Migrate the previous checkbox without changing its effective behavior.
        if (!$had_cost_mode) $s['cost_mode'] = !empty($s['cost_saver_enabled']) ? 'hemat' : 'standard';
        if (!in_array($s['cost_mode'], ['standard','hemat','super_hemat','custom'], true)) $s['cost_mode'] = 'hemat';
        $s['custom_output_tokens'] = max(256, min(7000, absint($s['custom_output_tokens'])));
        $s['custom_word_min'] = max(100, min(3000, absint($s['custom_word_min'] ?? 400)));
        $s['custom_word_max'] = max($s['custom_word_min'], min(3000, absint($s['custom_word_max'] ?? 700)));
        if (!in_array($s['custom_image_quality'], ['low','medium','high'], true)) $s['custom_image_quality'] = 'medium';
        if (empty($s['prices']) || !is_array($s['prices'])) $s['prices'] = [];
        if (!isset($s['cost_saver_enabled'])) $s['cost_saver_enabled'] = 1;
        foreach (self::default_prices() as $model => $price) {
            if (empty($s['prices'][$model]) || !is_array($s['prices'][$model])) {
                $s['prices'][$model] = $price;
            } else {
                foreach ($price as $key => $value) {
                    if (!isset($s['prices'][$model][$key]) || (float)$s['prices'][$model][$key] <= 0) {
                        $s['prices'][$model][$key] = $value;
                    }
                }
            }
        }
        update_option(self::OPT, $s, false);
        update_option(self::OPT . '_db_version', self::DB_VERSION, false);

        // Existing 1.3.148 logs were recorded before default prices existed.
        // Recalculate them once so the dashboard does not keep showing Rp0.
        self::reprice_existing_logs();
    }

    private static function settings() {
        $s = get_option(self::OPT, []);
        if (!is_array($s)) $s = [];
        $s = wp_parse_args($s, [
            'usd_idr' => 0,
            'usd_idr_source' => '',
            'usd_idr_updated' => 0,
            'prices' => [],
            'cost_saver_enabled' => 1,
            'cost_mode' => '',
            'custom_output_tokens' => 4000,
            'custom_word_min' => 400,
            'custom_word_max' => 700,
            'custom_image_quality' => 'medium',
                        'openai_billing_last_sync' => 0,
            'openai_billing_month_usd' => 0,
            'openai_billing_30d_usd' => 0,
            'openai_billing_status' => '',
        ]);
        if (!in_array($s['cost_mode'], ['standard','hemat','super_hemat','custom'], true)) $s['cost_mode'] = !empty($s['cost_saver_enabled']) ? 'hemat' : 'standard';
        $s['custom_output_tokens'] = max(256, min(7000, absint($s['custom_output_tokens'])));
        $s['custom_word_min'] = max(100, min(3000, absint($s['custom_word_min'] ?? 400)));
        $s['custom_word_max'] = max($s['custom_word_min'], min(3000, absint($s['custom_word_max'] ?? 700)));
        if (!in_array($s['custom_image_quality'], ['low','medium','high'], true)) $s['custom_image_quality'] = 'medium';
        if (!is_array($s['prices'])) $s['prices'] = [];
        foreach (self::default_prices() as $model => $price) {
            if (empty($s['prices'][$model]) || !is_array($s['prices'][$model])) $s['prices'][$model] = $price;
        }
        return $s;
    }

    private static function price($model) {
        $s = self::settings();
        $p = isset($s['prices'][$model]) && is_array($s['prices'][$model]) ? $s['prices'][$model] : [];
        return [
            'input' => isset($p['input']) ? (float)$p['input'] : 0,
            'cached' => isset($p['cached']) ? (float)$p['cached'] : 0,
            'output' => isset($p['output']) ? (float)$p['output'] : 0,
        ];
    }

    private static function exchange_rate_sources() {
        return [
            [
                'url' => 'https://api.frankfurter.app/latest?from=USD&to=IDR',
                'source' => 'Frankfurter',
                'parser' => 'frankfurter',
            ],
            [
                'url' => 'https://open.er-api.com/v6/latest/USD',
                'source' => 'ExchangeRate-API',
                'parser' => 'erapi',
            ],
        ];
    }

    private static function maybe_refresh_exchange_rate($force = false) {
        $s = self::settings();
        $last = (int)($s['usd_idr_updated'] ?? 0);
        // Refresh at most once every 12 hours unless explicitly forced.
        if (!$force && $last > 0 && (time() - $last) < 12 * HOUR_IN_SECONDS && (float)$s['usd_idr'] > 0) return;

        foreach (self::exchange_rate_sources() as $src) {
            $response = wp_remote_get($src['url'], [
                'timeout' => 6,
                'redirection' => 2,
                'headers' => ['Accept' => 'application/json'],
                'user-agent' => 'Japur-Suite/' . (defined('JAPUR_SUITE_VERSION') ? JAPUR_SUITE_VERSION : '1.0'),
            ]);
            if (is_wp_error($response)) continue;
            $code = (int)wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) continue;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $rate = 0;
            if ($src['parser'] === 'frankfurter') $rate = (float)($body['rates']['IDR'] ?? 0);
            if ($src['parser'] === 'erapi') $rate = (float)($body['rates']['IDR'] ?? 0);
            if ($rate <= 0) continue;

            $s['usd_idr'] = $rate;
            $s['usd_idr_source'] = $src['source'];
            $s['usd_idr_updated'] = time();
            update_option(self::OPT, $s, false);
            // Reprice existing records using the newest exchange rate.
            self::reprice_existing_logs();
            return $rate;
        }
        return (float)($s['usd_idr'] ?? 0);
    }

    private static function sync_openai_billing($admin_key) {
        $admin_key = trim((string)$admin_key);
        if ($admin_key === '') return ['ok'=>false, 'message'=>'Admin API Key belum diisi.'];
        $now = time();
        $month_start = strtotime(date('Y-m-01 00:00:00', current_time('timestamp')));
        $start_30d = $now - (30 * DAY_IN_SECONDS);
        $ranges = [
            'month' => [$month_start, $now],
            '30d' => [$start_30d, $now],
        ];
        $totals = ['month'=>0.0,'30d'=>0.0];
        foreach ($ranges as $name=>$range) {
            $url = add_query_arg([
                'start_time' => (int)$range[0],
                'end_time' => (int)$range[1],
                'bucket_width' => '1d',
                'limit' => 180,
            ], 'https://api.openai.com/v1/organization/costs');
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'redirection' => 2,
                'headers' => [
                    'Authorization' => 'Bearer ' . $admin_key,
                    'Accept' => 'application/json',
                ],
                'user-agent' => 'Japur-Suite/' . (defined('JAPUR_SUITE_VERSION') ? JAPUR_SUITE_VERSION : '1.0'),
            ]);
            if (is_wp_error($response)) return ['ok'=>false,'message'=>'Gagal menghubungi OpenAI Billing API: '.$response->get_error_message()];
            $code = (int)wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code < 200 || $code >= 300) {
                $msg = is_array($body) ? (string)($body['error']['message'] ?? '') : '';
                if ($msg === '') $msg = 'HTTP ' . $code;
                return ['ok'=>false,'message'=>'OpenAI Billing API menolak permintaan: '.$msg];
            }
            $sum = 0.0;
            foreach ((array)($body['data'] ?? []) as $bucket) {
                foreach ((array)($bucket['results'] ?? $bucket['result'] ?? []) as $row) {
                    $currency = strtolower((string)($row['amount']['currency'] ?? 'usd'));
                    if ($currency === 'usd') $sum += (float)($row['amount']['value'] ?? 0);
                }
            }
            $totals[$name] = $sum;
        }
        $s = self::settings();
        $s['openai_billing_last_sync'] = time();
        $s['openai_billing_month_usd'] = $totals['month'];
        $s['openai_billing_30d_usd'] = $totals['30d'];
        $s['openai_billing_status'] = 'success';
        update_option(self::OPT, $s, false);
        return ['ok'=>true,'month_usd'=>$totals['month'],'30d_usd'=>$totals['30d']];
    }

    private static function calculate_usd($model, $input, $cached, $output) {
        $price = self::price($model);
        $normal_input = max(0, $input - min($input, $cached));
        return (($normal_input * $price['input']) + ($cached * $price['cached']) + ($output * $price['output'])) / 1000000;
    }

    private static function reprice_existing_logs() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) return;
        $rows = $wpdb->get_results("SELECT id, model, input_tokens, cached_tokens, output_tokens, meta FROM {$table} ORDER BY id ASC LIMIT 5000", ARRAY_A);
        if (!$rows) return;
        $s = self::settings();
        $rate = max(0, (float)($s['usd_idr'] ?? 0));
        if ($rate <= 0) return;
        foreach ($rows as $row) {
            $usd = self::calculate_usd($row['model'], (int)$row['input_tokens'], (int)$row['cached_tokens'], (int)$row['output_tokens']);
            $idr = $usd * $rate;
            $wpdb->update($table, ['cost_usd'=>$usd, 'cost_idr'=>$idr], ['id'=>(int)$row['id']], ['%f','%f'], ['%d']);
        }
    }

    private static function usage_numbers($usage) {
        if (!is_array($usage)) $usage = [];
        $input = (int)($usage['input_tokens'] ?? 0);
        $output = (int)($usage['output_tokens'] ?? 0);
        $total = (int)($usage['total_tokens'] ?? ($input + $output));
        $cached = (int)($usage['input_tokens_details']['cached_tokens'] ?? $usage['prompt_tokens_details']['cached_tokens'] ?? 0);
        return [$input, $cached, $output, $total];
    }

    private static function count_words($text) {
        $text = wp_strip_all_tags((string)$text);
        if ($text === '') return 0;
        $count = preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);
        return $count === false ? 0 : (int)$count;
    }

    public static function record($event) {
        if (!is_array($event)) return;
        global $wpdb;
        self::maybe_install();
        $usage = isset($event['usage']) && is_array($event['usage']) ? $event['usage'] : [];
        list($input,$cached,$output,$total) = self::usage_numbers($usage);
        $model = sanitize_text_field($event['model'] ?? '');
        $article_words = sanitize_key($event['kind'] ?? '') === 'article' ? self::count_words($event['article_content'] ?? '') : 0;
        $usd = self::calculate_usd($model, $input, $cached, $output);
        $s = self::settings();
        $idr = $usd * max(0, (float)$s['usd_idr']);
        $meta = [
            'target' => sanitize_key($event['target'] ?? ''),
            'usage_raw' => $usage,
        ];
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->insert($table, [
            'created_at' => current_time('mysql'),
            'user_id' => absint($event['user_id'] ?? get_current_user_id()),
            'kind' => sanitize_key($event['kind'] ?? 'article'),
            'model' => $model,
            'article_title' => sanitize_text_field($event['article_title'] ?? ''),
            'article_words' => $article_words,
            'response_id' => sanitize_text_field($event['response_id'] ?? ''),
            'input_tokens' => $input,
            'cached_tokens' => $cached,
            'output_tokens' => $output,
            'total_tokens' => $total,
            'cost_usd' => $usd,
            'cost_idr' => $idr,
            'meta' => wp_json_encode($meta),
        ], ['%s','%d','%s','%s','%s','%d','%s','%d','%d','%d','%d','%f','%f','%s']);
    }

    private static function money_idr($v) { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }
    private static function money_usd($v) { return '$' . number_format((float)$v, 6, '.', ','); }

    public static function mode_config($settings = null) {
        $s = is_array($settings) ? $settings : self::settings();
        $mode = in_array($s['cost_mode'] ?? '', ['standard','hemat','super_hemat','custom'], true) ? $s['cost_mode'] : (!empty($s['cost_saver_enabled']) ? 'hemat' : 'standard');
        $custom_min = max(100, min(3000, absint($s['custom_word_min'] ?? 400)));
        $custom_max = max($custom_min, min(3000, absint($s['custom_word_max'] ?? 700)));
        $presets = [
            'standard' => ['tokens'=>7000, 'quality'=>'auto', 'word_min'=>500, 'word_max'=>700],
            'hemat' => ['tokens'=>3000, 'quality'=>'low', 'word_min'=>300, 'word_max'=>400],
            'super_hemat' => ['tokens'=>2000, 'quality'=>'low', 'word_min'=>200, 'word_max'=>300],
            'custom' => ['tokens'=>max(256,min(7000,absint($s['custom_output_tokens'] ?? 4000))), 'quality'=>in_array(($s['custom_image_quality'] ?? ''),['low','medium','high'],true) ? $s['custom_image_quality'] : 'medium', 'word_min'=>$custom_min, 'word_max'=>$custom_max],
        ];
        $cfg = $presets[$mode];
        return ['mode'=>$mode,'max_output_tokens'=>$cfg['tokens'],'image_quality'=>$cfg['quality'],'word_min'=>$cfg['word_min'],'word_max'=>$cfg['word_max']];
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;
        self::maybe_install();
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        if (isset($_POST['joc_sync_billing']) && check_admin_referer('joc_settings')) {
            $s0 = self::settings();
            $admin_key = class_exists('JapurSuite_Core') ? JapurSuite_Core::openai_admin_key() : '';
            $billing = self::sync_openai_billing($admin_key);
            if (!empty($billing['ok'])) {
                echo '<div class="notice notice-success is-dismissible"><p>Biaya OpenAI berhasil disinkronkan dari Billing API.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($billing['message'] ?? 'Gagal sinkronisasi Billing API.') . '</p></div>';
            }
        }
        if (isset($_POST['joc_refresh_rate']) && check_admin_referer('joc_settings')) {
            self::maybe_refresh_exchange_rate(true);
            echo '<div class="notice notice-success is-dismissible"><p>Kurs USD → IDR berhasil diperbarui otomatis jika sumber kurs tersedia.</p></div>';
        }
        if (isset($_POST['joc_delete_logs']) && check_admin_referer('joc_settings')) {
            $deleted = (int)$wpdb->query("DELETE FROM {$table}");
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($deleted) . ' log OpenAI berhasil dihapus.</p></div>';
        }
        if (isset($_POST['joc_save']) && check_admin_referer('joc_settings')) {
            $s = self::settings();
            $posted_mode = sanitize_key($_POST['cost_mode'] ?? '');
            if (!in_array($posted_mode, ['standard','hemat','super_hemat','custom'], true)) $posted_mode = $s['cost_mode'];
            $s['cost_mode'] = $posted_mode;
            $s['custom_output_tokens'] = max(256, min(7000, absint($_POST['custom_output_tokens'] ?? 4000)));
            $s['custom_word_min'] = max(100, min(3000, absint($_POST['custom_word_min'] ?? 400)));
            $s['custom_word_max'] = max($s['custom_word_min'], min(3000, absint($_POST['custom_word_max'] ?? 700)));
            $posted_quality = sanitize_key($_POST['custom_image_quality'] ?? 'medium');
            $s['custom_image_quality'] = in_array($posted_quality, ['low','medium','high'], true) ? $posted_quality : 'medium';
            // Keep the legacy flag synchronized for older code/settings.
            $s['cost_saver_enabled'] = $posted_mode !== 'standard' ? 1 : 0;
            // Admin Billing key is managed centrally by API Center.
            if (isset($s['openai_admin_key'])) { unset($s['openai_admin_key']); }
            $posted_rate = sanitize_text_field(wp_unslash($_POST['usd_idr'] ?? ''));
            if ($posted_rate !== '') $s['usd_idr'] = max(0, (float)str_replace(',', '.', $posted_rate));
            $models = isset($_POST['model']) && is_array($_POST['model']) ? $_POST['model'] : [];
            $prices = is_array($s['prices'] ?? null) ? $s['prices'] : [];
            foreach ($models as $model=>$p) {
                $model = sanitize_text_field($model);
                if ($model === '') continue;
                $prices[$model] = [
                    'input'=>max(0,(float)str_replace(',','.',sanitize_text_field($p['input']??0))),
                    'cached'=>max(0,(float)str_replace(',','.',sanitize_text_field($p['cached']??0))),
                    'output'=>max(0,(float)str_replace(',','.',sanitize_text_field($p['output']??0))),
                ];
            }
            $s['prices']=$prices;
            update_option(self::OPT,$s,false);
            self::reprice_existing_logs();
            echo '<div class="notice notice-success is-dismissible"><p>Pengaturan OpenAI Cost disimpan.</p></div>';
        }
        $s=self::settings();
        $today=current_time('Y-m-d');
        $month=current_time('Y-m');
        $today_row=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) requests, COALESCE(SUM(input_tokens),0) input_tokens, COALESCE(SUM(cached_tokens),0) cached_tokens, COALESCE(SUM(output_tokens),0) output_tokens, COALESCE(SUM(total_tokens),0) total_tokens, COALESCE(SUM(cost_idr),0) cost_idr FROM {$table} WHERE DATE(created_at)=%s",$today),ARRAY_A);
        $month_row=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) requests, COALESCE(SUM(total_tokens),0) total_tokens, COALESCE(SUM(cost_idr),0) cost_idr FROM {$table} WHERE DATE_FORMAT(created_at,'%%Y-%%m')=%s",$month),ARRAY_A);
        $article_per_page = 5;
        $article_page = max(1, absint($_GET['joc_article_page'] ?? 1));
        $article_total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM (SELECT article_title FROM {$table} WHERE DATE(created_at)=%s AND article_title<>'' GROUP BY article_title) AS article_groups",$today));
        $article_pages = max(1, (int)ceil($article_total / $article_per_page));
        if ($article_page > $article_pages) $article_page = $article_pages;
        $article_offset = ($article_page - 1) * $article_per_page;
        $articles=$wpdb->get_results($wpdb->prepare("SELECT article_title, COUNT(*) requests, SUM(article_words) words, SUM(total_tokens) tokens, SUM(cost_idr) cost_idr FROM {$table} WHERE DATE(created_at)=%s AND article_title<>'' GROUP BY article_title ORDER BY cost_idr DESC LIMIT %d OFFSET %d",$today,$article_per_page,$article_offset),ARRAY_A);
        $log_per_page = 10;
        $log_page = max(1, absint($_GET['joc_log_page'] ?? 1));
        $log_total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $log_pages = max(1, (int)ceil($log_total / $log_per_page));
        if ($log_page > $log_pages) $log_page = $log_pages;
        $log_offset = ($log_page - 1) * $log_per_page;
        $logs=$wpdb->get_results($wpdb->prepare("SELECT created_at,kind,model,article_title,article_words,total_tokens,cost_idr FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",$log_per_page,$log_offset),ARRAY_A);
        $known=['gpt-5.6-luna','gpt-image-2'];
        foreach($logs as $r) if($r['model'] && !in_array($r['model'],$known,true)) $known[]=$r['model'];
        ?>
        <div class="wrap joc-wrap">
        <h1>OpenAI Cost</h1>
        <p class="description">Pemakaian token, estimasi jumlah kata, dan estimasi biaya OpenAI dari modul Japur Suite. API Key tetap dikelola oleh API Center.</p>
        <div class="joc-grid">
          <div class="joc-card"><b>Hari ini</b><strong><?php echo esc_html(self::money_idr($today_row['cost_idr'])); ?></strong><span><?php echo number_format_i18n($today_row['requests']); ?> request · <?php echo number_format_i18n($today_row['total_tokens']); ?> token</span></div>
          <div class="joc-card"><b>Bulan ini</b><strong><?php echo esc_html(self::money_idr($month_row['cost_idr'])); ?></strong><span><?php echo number_format_i18n($month_row['requests']); ?> request · <?php echo number_format_i18n($month_row['total_tokens']); ?> token</span></div>
          <div class="joc-card"><b>Artikel hari ini</b><strong><?php echo number_format_i18n($article_total); ?></strong><span>Artikel yang sudah tercatat hari ini</span></div>
          <div class="joc-card"><b>Rata-rata / artikel</b><strong><?php $avg=$article_total?((float)$today_row['cost_idr']/$article_total):0; echo esc_html(self::money_idr($avg)); ?></strong><span>Estimasi berdasarkan log hari ini</span></div>
        </div>
        <div class="joc-card joc-wide joc-billing-card">
          <div class="joc-log-head"><div><h2>Billing OpenAI</h2><p class="description">Sinkronisasi biaya organisasi melalui OpenAI Costs API. Ini membutuhkan <b>Admin API Key</b>; API key biasa untuk membuat artikel tidak memiliki akses ke endpoint organisasi.</p></div><span class="joc-mode-badge">API Billing</span></div>
          <div class="joc-grid joc-billing-grid">
            <div class="joc-card"><b>Biaya bulan ini — OpenAI</b><strong><?php echo esc_html(self::money_usd((float)($s['openai_billing_month_usd'] ?? 0))); ?></strong><span><?php echo !empty($s['openai_billing_last_sync']) ? 'Sinkron '.human_time_diff((int)$s['openai_billing_last_sync'], time()).' lalu' : 'Belum disinkronkan'; ?></span></div>
            <div class="joc-card"><b>Biaya 30 hari — OpenAI</b><strong><?php echo esc_html(self::money_usd((float)($s['openai_billing_30d_usd'] ?? 0))); ?></strong><span>Data dari endpoint Costs organisasi</span></div>
          </div>
          <div class="joc-billing-form"><div><b>Admin API Key</b><span class="description">Dikelola terpusat di <b>API Center → OpenAI API</b>. Cost AI tidak lagi menyimpan key billing secara terpisah.</span></div><div class="joc-billing-actions"><form method="post"><?php wp_nonce_field('joc_settings'); ?><button class="button button-primary" name="joc_sync_billing" value="1">↻ Sinkronkan Billing OpenAI</button></form></div></div>
          <div class="joc-billing-note"><b>Catatan penting:</b> OpenAI saat ini menyediakan API resmi untuk <b>usage</b> dan <b>costs</b> organisasi, tetapi saldo kredit prepaid aktual tidak tersedia sebagai endpoint saldo numerik publik yang terdokumentasi. Jadi Japur tidak akan menampilkan angka saldo palsu. Saldo kredit tetap harus dilihat di Billing OpenAI. <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener noreferrer">Buka Billing OpenAI ↗</a></div>
        </div>
        <div class="joc-card joc-wide">
          <h2>Biaya per Artikel — <?php echo esc_html($today); ?></h2>
          <?php if($articles): ?><div class="joc-table-scroll joc-article-scroll"><table class="widefat striped joc-article-table"><thead><tr><th>Artikel</th><th>Request</th><th>Estimasi Kata</th><th>Total Token</th><th>Biaya</th></tr></thead><tbody>
          <?php foreach($articles as $r): ?><tr><td><?php echo esc_html($r['article_title']); ?></td><td><?php echo number_format_i18n($r['requests']); ?></td><td><?php echo number_format_i18n((int)$r['words']); ?></td><td><?php echo number_format_i18n($r['tokens']); ?></td><td><b><?php echo esc_html(self::money_idr($r['cost_idr'])); ?></b></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <?php if($article_total > $article_per_page): ?>
          <div class="joc-pagination" aria-label="Pagination biaya per artikel">
            <?php if($article_page > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_article_page'=>$article_page-1], admin_url('admin.php'))); ?>">‹ Sebelumnya</a><?php endif; ?>
            <?php
            $start_article_page = max(1, $article_page - 3);
            $end_article_page = min($article_pages, $article_page + 3);
            if ($start_article_page > 1) {
                echo '<a class="button" href="' . esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_article_page'=>1], admin_url('admin.php'))) . '">1</a>';
                if ($start_article_page > 2) echo '<span class="joc-page-gap">…</span>';
            }
            for ($p=$start_article_page; $p<=$end_article_page; $p++) {
                $cls = $p === $article_page ? 'button button-primary' : 'button';
                echo '<a class="' . esc_attr($cls) . '" href="' . esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_article_page'=>$p], admin_url('admin.php'))) . '">' . $p . '</a>';
            }
            if ($end_article_page < $article_pages) {
                if ($end_article_page < $article_pages - 1) echo '<span class="joc-page-gap">…</span>';
                echo '<a class="button" href="' . esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_article_page'=>$article_pages], admin_url('admin.php'))) . '">' . $article_pages . '</a>';
            }
            ?>
            <?php if($article_page < $article_pages): ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_article_page'=>$article_page+1], admin_url('admin.php'))); ?>">Berikutnya ›</a><?php endif; ?>
            <span class="joc-page-info">Halaman <?php echo esc_html($article_page); ?> dari <?php echo esc_html($article_pages); ?> · <?php echo number_format_i18n($article_total); ?> artikel</span>
          </div>
          <?php endif; ?>
          <?php else: ?><p>Belum ada pemakaian artikel yang tercatat hari ini.</p><?php endif; ?>
        </div>
        <div class="joc-card joc-wide">
          <h2>Pengaturan Harga</h2>
          <p>Harga default mengikuti tarif standar OpenAI yang diketahui modul. Kurs USD → IDR diperbarui otomatis dan kurs terakhir tetap dipakai jika sumber sedang gagal.</p>
          <form method="post"><?php wp_nonce_field('joc_settings'); ?>
          <div class="joc-mode-box">
            <div class="joc-mode-head"><div><h3>Mode Biaya & Kualitas</h3><p>Pilih keseimbangan kualitas dan biaya. Materi serta aturan prompt tetap dikirim utuh.</p></div><span class="joc-mode-badge">Aktif: <?php echo esc_html(ucwords(str_replace('_',' ',$s['cost_mode']))); ?></span></div>
            <div class="joc-mode-grid">
              <label class="joc-mode-option"><input type="radio" name="cost_mode" value="standard" <?php checked($s['cost_mode'],'standard'); ?>><span><b>Standard</b><small>7.000 token · 500–700 kata · gambar Auto</small><em>Kualitas penuh dengan batas token maksimum.</em></span></label>
              <label class="joc-mode-option"><input type="radio" name="cost_mode" value="hemat" <?php checked($s['cost_mode'],'hemat'); ?>><span><b>Hemat</b><small>3.000 token · 300–400 kata · gambar Low</small><em>Mode hemat yang mempertahankan perilaku sebelumnya.</em></span></label>
              <label class="joc-mode-option"><input type="radio" name="cost_mode" value="super_hemat" <?php checked($s['cost_mode'],'super_hemat'); ?>><span><b>Super Hemat</b><small>2.000 token · 200–300 kata · gambar Low</small><em>Tekan biaya lebih jauh dengan output lebih ringkas.</em></span></label>
              <label class="joc-mode-option"><input type="radio" name="cost_mode" value="custom" <?php checked($s['cost_mode'],'custom'); ?>><span><b>Custom</b><small>Tentukan sendiri token, kata & kualitas gambar</small><em>Atur batas output artikel dan kualitas thumbnail.</em></span></label>
            </div>
            <div class="joc-custom-settings" <?php echo $s['cost_mode']==='custom' ? '' : 'hidden'; ?>>
              <div><label for="joc-custom-tokens"><b>Batas Token</b></label><input id="joc-custom-tokens" type="number" min="256" max="7000" step="1" name="custom_output_tokens" value="<?php echo esc_attr($s['custom_output_tokens']); ?>"><span class="description">256–7.000 output token per artikel.</span></div>
              <div><label for="joc-custom-word-min"><b>Target Kata Minimum</b></label><input id="joc-custom-word-min" type="number" min="100" max="3000" step="1" name="custom_word_min" value="<?php echo esc_attr($s['custom_word_min']); ?>"><span class="description">Panjang minimum yang diminta dari AI.</span></div>
              <div><label for="joc-custom-word-max"><b>Target Kata Maksimum</b></label><input id="joc-custom-word-max" type="number" min="100" max="3000" step="1" name="custom_word_max" value="<?php echo esc_attr($s['custom_word_max']); ?>"><span class="description">Panjang maksimum yang diminta dari AI.</span></div>
              <div><label for="joc-custom-quality"><b>Kualitas Gambar</b></label><select id="joc-custom-quality" name="custom_image_quality"><option value="low" <?php selected($s['custom_image_quality'],'low'); ?>>Low</option><option value="medium" <?php selected($s['custom_image_quality'],'medium'); ?>>Middle</option><option value="high" <?php selected($s['custom_image_quality'],'high'); ?>>High</option></select><span class="description">Diterapkan pada pembuatan thumbnail.</span></div>
              <p class="description joc-custom-note">Target kata mengatur panjang artikel. Batas token tetap menjadi batas maksimum output AI.</p>
            </div>
          </div>
          <div class="joc-table-scroll joc-price-scroll"><table class="widefat striped joc-price-table"><thead><tr><th>Model</th><th>Input / 1M USD</th><th>Cached Input / 1M USD</th><th>Output / 1M USD</th></tr></thead><tbody>
          <tr><td><b>Kurs USD → IDR</b></td><td colspan="3"><input type="number" step="0.01" name="usd_idr" value="<?php echo esc_attr($s['usd_idr']); ?>" style="width:180px" readonly> <span class="description"><?php echo esc_html($s['usd_idr_source'] ? 'Sumber: '.$s['usd_idr_source'].' · diperbarui '.human_time_diff((int)$s['usd_idr_updated'], time()).' lalu' : 'Belum berhasil mengambil kurs otomatis.'); ?></span> <button class="button" name="joc_refresh_rate" value="1">↻ Perbarui Kurs Otomatis</button></td></tr>
          <?php foreach($known as $model): $p=self::price($model); ?><tr><td><b><?php echo esc_html($model); ?></b></td><td><input type="number" step="0.000001" name="model[<?php echo esc_attr($model); ?>][input]" value="<?php echo esc_attr($p['input']); ?>"></td><td><input type="number" step="0.000001" name="model[<?php echo esc_attr($model); ?>][cached]" value="<?php echo esc_attr($p['cached']); ?>"></td><td><input type="number" step="0.000001" name="model[<?php echo esc_attr($model); ?>][output]" value="<?php echo esc_attr($p['output']); ?>"></td></tr><?php endforeach; ?>
          </tbody></table></div><p class="description">Khusus GPT-Image-2, biaya aktual bergantung pada token teks/gambar yang dilaporkan API; modul menghitung berdasarkan usage yang diterima.</p><p><button class="button button-primary" name="joc_save" value="1">Simpan Pengaturan</button></p></form>
        </div>
        <div class="joc-card joc-wide">
          <div class="joc-log-head"><div><h2>Log Terbaru</h2><p class="description">Menampilkan 10 log per halaman. Estimasi kata dihitung dari teks artikel yang dikembalikan AI; log gambar menampilkan tanda —. Geser tabel ke samping untuk melihat kolom yang lebih lebar.</p></div><form method="post" onsubmit="return confirm('Hapus semua log OpenAI? Tindakan ini tidak dapat dibatalkan.');"><?php wp_nonce_field('joc_settings'); ?><button type="submit" class="button button-secondary" name="joc_delete_logs" value="1">Hapus Semua Log</button></form></div>
          <div class="joc-table-scroll"><table class="widefat striped joc-log-table"><thead><tr><th>Waktu</th><th>Jenis</th><th>Model</th><th>Artikel</th><th>Estimasi Kata</th><th>Token</th><th>Biaya</th></tr></thead><tbody>
          <?php if($logs): foreach($logs as $r): ?><tr><td><?php echo esc_html($r['created_at']); ?></td><td><?php echo esc_html($r['kind']); ?></td><td><?php echo esc_html($r['model']); ?></td><td><?php echo esc_html($r['article_title']); ?></td><td><?php echo $r['article_words'] ? number_format_i18n($r['article_words']) : '—'; ?></td><td><?php echo number_format_i18n($r['total_tokens']); ?></td><td><?php echo esc_html(self::money_idr($r['cost_idr'])); ?></td></tr><?php endforeach; else: ?><tr><td colspan="7">Belum ada log OpenAI.</td></tr><?php endif; ?>
          </tbody></table></div>
          <?php if($log_total > $log_per_page): ?>
          <div class="joc-pagination" aria-label="Pagination log OpenAI">
            <?php if($log_page > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_log_page'=>$log_page-1], admin_url('admin.php'))); ?>">‹ Sebelumnya</a><?php endif; ?>
            <?php
            $start_page = max(1, $log_page - 3);
            $end_page = min($log_pages, $log_page + 3);
            if ($start_page > 1) {
                echo '<a class="button" href="' . esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_log_page'=>1], admin_url('admin.php'))) . '">1</a>';
                if ($start_page > 2) echo '<span class="joc-page-gap">…</span>';
            }
            for ($p=$start_page; $p<=$end_page; $p++) {
                $cls = $p === $log_page ? 'button button-primary' : 'button';
                echo '<a class="' . esc_attr($cls) . '" href="' . esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_log_page'=>$p], admin_url('admin.php'))) . '">' . $p . '</a>';
            }
            if ($end_page < $log_pages) {
                if ($end_page < $log_pages - 1) echo '<span class="joc-page-gap">…</span>';
                echo '<a class="button" href="' . esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_log_page'=>$log_pages], admin_url('admin.php'))) . '">' . $log_pages . '</a>';
            }
            ?>
            <?php if($log_page < $log_pages): ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'japur-openai-cost','joc_log_page'=>$log_page+1], admin_url('admin.php'))); ?>">Berikutnya ›</a><?php endif; ?>
          </div>
          <p class="description">Halaman <?php echo number_format_i18n($log_page); ?> dari <?php echo number_format_i18n($log_pages); ?> · Total <?php echo number_format_i18n($log_total); ?> log</p>
          <?php else: ?><p class="description">Total <?php echo number_format_i18n($log_total); ?> log.</p><?php endif; ?>
        </div>
        <script>
        jQuery(function($){
          function syncCustom(){
            $('.joc-custom-settings').prop('hidden', $('input[name=cost_mode]:checked').val() !== 'custom');
            $('.joc-mode-option').removeClass('is-selected');
            $('input[name=cost_mode]:checked').closest('.joc-mode-option').addClass('is-selected');
          }
          $(document).on('change','input[name=cost_mode]',syncCustom); syncCustom();
        });
        </script>
        <style>
        .joc-wrap{max-width:1200px}.joc-billing-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:end}.joc-billing-form input{width:100%;max-width:520px;box-sizing:border-box}.joc-billing-actions{display:flex;align-items:flex-end}.joc-billing-note{margin-top:14px;padding:12px 14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:10px;color:#50575e;line-height:1.55}.joc-billing-grid{margin:14px 0 0;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.joc-billing-grid .joc-card{margin:0;padding:14px}.joc-billing-grid strong{font-size:22px}.joc-mode-box{padding:16px;margin:12px 0;background:#f6f7f7;border:1px solid #dcdcde;border-radius:12px}.joc-mode-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px}.joc-mode-head h3{margin:0 0 4px;font-size:16px}.joc-mode-head p{margin:0;color:#646970}.joc-mode-badge{background:#e7f3f8;color:#2271b1;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:600;white-space:nowrap}.joc-mode-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.joc-mode-option{display:flex;gap:9px;align-items:flex-start;padding:12px;background:#fff;border:1px solid #dcdcde;border-radius:10px;cursor:pointer;box-sizing:border-box}.joc-mode-option input{margin-top:3px;flex:0 0 auto}.joc-mode-option span{display:flex;flex-direction:column;gap:3px}.joc-mode-option b{font-size:14px}.joc-mode-option small{color:#2271b1;font-weight:600}.joc-mode-option em{font-style:normal;color:#646970;font-size:12px;line-height:1.4}.joc-mode-option.is-selected{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1 inset}.joc-custom-settings{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:12px;padding:14px;background:#fff;border:1px dashed #c3c4c7;border-radius:10px}.joc-custom-settings>div{display:flex;flex-direction:column;gap:5px}.joc-custom-settings input,.joc-custom-settings select{width:100%;max-width:260px;box-sizing:border-box}.joc-custom-settings .description{color:#646970}.joc-wrap .joc-saver-box{display:none}.joc-saver-box{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;margin:12px 0;background:#f6f7f7;border:1px solid #dcdcde;border-radius:10px}.joc-saver-box label{flex:0 0 auto}.joc-saver-box span{color:#646970}.joc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:18px 0}.joc-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:20px;margin:0 0 18px;box-sizing:border-box}.joc-grid .joc-card{display:flex;flex-direction:column;gap:7px}.joc-grid strong{font-size:25px}.joc-grid span{color:#646970}.joc-wide{overflow:visible}.joc-card h2{margin-top:0}.joc-article-scroll{margin-top:10px}.joc-article-table{min-width:720px;border:0}.joc-article-table th,.joc-article-table td{white-space:nowrap;vertical-align:top}.joc-article-table th:first-child,.joc-article-table td:first-child{white-space:normal;min-width:320px;max-width:520px}.joc-log-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:12px}.joc-log-head h2{margin-bottom:4px}.joc-table-scroll{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #dcdcde}.joc-price-scroll{margin-top:12px}.joc-price-table{min-width:760px;border:0}.joc-price-table th,.joc-price-table td{vertical-align:middle;white-space:nowrap}.joc-price-table th:first-child,.joc-price-table td:first-child{min-width:180px}.joc-price-table input[type="number"]{width:150px;max-width:none;box-sizing:border-box}.joc-log-table{min-width:780px;border:0}.joc-log-table th,.joc-log-table td{white-space:nowrap;vertical-align:top}.joc-log-table th:nth-child(4),.joc-log-table td:nth-child(4){white-space:normal;min-width:280px;max-width:420px}.joc-pagination{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:14px}.joc-page-info{color:#646970;margin-left:4px}.joc-page-gap{padding:0 4px;color:#646970}.joc-log-head form{margin:0;flex:0 0 auto}@media(max-width:600px){.joc-billing-form{grid-template-columns:1fr}.joc-billing-actions{align-items:stretch}.joc-billing-actions .button{width:100%;justify-content:center}.joc-billing-grid{grid-template-columns:1fr}.joc-mode-grid{grid-template-columns:1fr}.joc-custom-settings{grid-template-columns:1fr}.joc-mode-head{flex-direction:column}.joc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.joc-card{padding:15px}.joc-wrap input{max-width:100%}.joc-price-table input[type="number"]{max-width:none}.joc-log-head{align-items:stretch;flex-direction:column}.joc-log-head form{align-self:flex-start}.joc-pagination .button{min-height:34px}}
        </style>
        </div>
        <?php
    }
}
Japur_OpenAI_Cost::init();
}
