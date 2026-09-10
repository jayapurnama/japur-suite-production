<?php
/**
 * Module: JaPur Source Discovery
 * Description: Isolated entrypoint for Unified Web Sumber discovery.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

$loader = __DIR__ . '/web-source-bridge-loader.php';
if (is_readable($loader)) {
    require_once $loader;
}
