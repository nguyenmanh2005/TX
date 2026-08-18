<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (function_exists('opcache_reset')) {
    opcache_reset();
}
try {
    $_GET['action'] = 'info';
    require 'api_tower_gods.php';
    echo "SUCCESS_NO_SYNTAX_ERROR";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
