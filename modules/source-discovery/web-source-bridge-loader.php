<?php
/**
 * Module: JaPur Web Source Bridge Loader
 * Description: Isolated loader for the Unified Web Sumber bridge.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

$bridge = __DIR__ . '/class-web-source-bridge.php';
if (is_readable($bridge)) {
    require_once $bridge;
}
