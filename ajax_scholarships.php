<?php
define('SEARCH_AJAX', true);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/includes/search_data.php';
require __DIR__ . '/includes/search_results.php';
