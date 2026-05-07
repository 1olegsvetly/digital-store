<?php
/**
 * Main entry point - Homepage
 */

require_once __DIR__ . '/includes/functions.php';

$config = getSiteConfig();
$content = file_get_contents(__DIR__ . '/templates/home.php');

// Capture template output
ob_start();
include __DIR__ . '/templates/home.php';
$content = ob_get_clean();

$pageTitle = $config['site_name'] . ' — Магазин цифровых товаров';
$pageDescription = $config['description'];

include __DIR__ . '/templates/layout.php';
