<?php
/**
 * Module: JaPur Source Discovery
 * Description: Isolated entrypoint for Unified Web Sumber discovery.
 * Module Version: 0.1.1
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

$loader = __DIR__ . '/web-source-bridge-loader.php';
if (is_readable($loader)) {
    require_once $loader;
}

$ui = __DIR__ . '/web-source-ui.php';
if (is_readable($ui)) {
    require_once $ui;
}
