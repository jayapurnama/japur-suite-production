<?php
/**
 * Module: JaPur Web Source Bridge Loader
 * Description: Isolated loader for the Unified Web Source bridge.
 * Module Version: 0.1.0
 * Author: Japur Ganteng
 */
if (!defined('ABSPATH')) exit;

$file = __DIR__ . '/class-web-source-bridge.php';
if (is_readable($file)) require_once $file;
