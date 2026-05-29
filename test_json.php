<?php
session_start();
$_SESSION['Iduser'] = 1;
$_SESSION['Name'] = 'TestUser';
$_GET['action'] = 'load';

ob_start();
include 'chat.php';
$output = ob_get_clean();

// Check if output is valid JSON
$json = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "INVALID JSON! Error: " . json_last_error_msg() . "\n";
    echo "Raw output preview (first 500 chars):\n";
    echo substr($output, 0, 500) . "\n";
} else {
    echo "VALID JSON! Found " . count($json) . " messages.\n";
    if (count($json) > 0) {
        echo "First message key names:\n";
        print_r(array_keys($json[0]));
        echo "First message preview:\n";
        print_r($json[0]);
    }
}
