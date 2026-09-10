<?php
/**
 * JaPur Category Source Engine loader.
 * Safe isolated loader; does not modify Source Sync v1.2.3.
 */
if (!defined('ABSPATH')) exit;
$engine = __DIR__ . '/source-category-engine.php';
if (is_readable($engine)) require_once $engine;
