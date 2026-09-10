<?php
/**
 * Module: JaPur Unified Discovery Engine
 * Description: Passive coordinator for Sitemap, Category REST, and RSS/Atom discovery.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 *
 * IMPORTANT: This coordinator is additive and passive. It performs no queue/database write,
 * registers no UI/AJAX hooks, and does not alter Source Sync v1.2.3.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('JaPur_Unified_Discovery_Engine')) {
final class JaPur_Unified_Discovery_Engine {
    const VER = '0.1.0';
    const LIMIT = 10;

    /**
     * Discover newest article candidates from the available source engines.
     * Engines are isolated; one failure does not stop the others.
     */
    public static function discover($site, $category = '', $sitemap = 'sitemap.xml', $limit = self::LIMIT) {
        $limit = max(1, min(200, absint($limit)));
        self::load_engines();

        $groups = [];
        $methods = [];

        if (class_exists('JaPur_Sitemap_Discovery_Engine')) {
            $r = JaPur_Sitemap_Discovery_Engine::discover($site, $sitemap, $limit);
            if (is_array($r) && !empty($r['items'])) {
                $groups[] = $r['items'];
                $methods[] = 'Sitemap';
            }
        }

        if (class_exists('JaPur_Category_Discovery_Engine')) {
            $r = JaPur_Category_Discovery_Engine::discover($site, $category);
            if (is_array($r) && !empty($r['items'])) {
                $groups[] = $r['items'];
                $methods[] = 'Category/REST';
            }
        }

        if (class_exists('JaPur_Feed_Discovery_Engine')) {
            $items = JaPur_Feed_Discovery_Engine::discover($site, $category, $limit);
            if (is_array($items) && !empty($items)) {
                $groups[] = $items;
                $methods[] = 'RSS/Atom';
            }
        }

        $items = class_exists('JaPur_Discovery_Adapter')
            ? JaPur_Discovery_Adapter::merge($groups, $limit)
            : self::fallback_merge($groups, $limit);

        return [
            'items' => $items,
            'methods' => array_values(array_unique($methods)),
            'method' => implode(' + ', array_values(array_unique($methods))),
            'version' => self::VER,
        ];
    }

    private static function load_engines() {
        $files = [
            'class-discovery-adapter.php',
            'class-sitemap-engine.php',
            'class-category-engine.php',
            'class-feed-engine.php',
        ];
        foreach ($files as $file) {
            $path = __DIR__ . '/' . $file;
            if (is_readable($path)) require_once $path;
        }
    }

    private static function fallback_merge($groups, $limit) {
        $out = [];
        foreach ((array) $groups as $group) {
            foreach ((array) $group as $item) {
                if (!is_array($item) || empty($item['url'])) continue;
                $out[strtolower((string) $item['url'])] = $item;
            }
        }
        $out = array_values($out);
        usort($out, function($a, $b) {
            $ta = strtotime((string) ($a['date'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['date'] ?? '')) ?: 0;
            return $tb <=> $ta;
        });
        return array_slice($out, 0, $limit);
    }
}
}
