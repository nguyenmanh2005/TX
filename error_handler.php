<?php
// error_handler.php - Include this at the top of your pages to report errors to chat2.php

function global_error_handler($errno, $errstr, $errfile, $errline) {
    $errorMsg = "[$errno] $errstr in $errfile on line $errline";
    $errorStack = ""; // Could be debug_backtrace()
    report_error_to_chat($errorMsg, $errorStack);
    return false; // Let normal error handling continue
}

function global_exception_handler($exception) {
    $errorMsg = "Uncaught Exception: " . $exception->getMessage();
    $errorStack = $exception->getTraceAsString();
    report_error_to_chat($errorMsg, $errorStack);
}

function report_error_to_chat($msg, $stack) {
    $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    // Adjust base path if needed
    if (strpos($_SERVER['REQUEST_URI'], '/1/') !== false) {
        $apiUrl .= "/1";
    }
    $apiUrl .= "/chat2.php";

    $pageUrl = $_SERVER['REQUEST_URI'];
    $fullMessage = "⚠️ [AUTO ERROR]\nPage: $pageUrl\nError: $msg\nStack: $stack";
    
    // Use cURL to post to chat2.php
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'message' => $fullMessage
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    $ch = null;
}

set_error_handler("global_error_handler");
set_exception_handler("global_exception_handler");
?>
