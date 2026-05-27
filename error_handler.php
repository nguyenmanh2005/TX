<?php
// error_handler.php - Include this at the top of your pages to report errors to the Centralized Logging System

require_once __DIR__ . '/SystemLogger.php';

function global_error_handler($errno, $errstr, $errfile, $errline) {
    // Skip logging for notices and deprecated warnings unless strict debug is on
    $isCritical = in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]);
    $isWarning = in_array($errno, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR]);
    
    $level = 'INFO';
    if ($isCritical) {
        $level = 'CRITICAL';
    } elseif ($isWarning) {
        $level = 'WARNING';
    } else {
        $level = 'DEBUG'; // Notices, Deprecated, etc.
    }

    $errorMsg = "[$errno] $errstr in $errfile on line $errline";
    
    // Capture backtrace details
    $details = [
        'errno' => $errno,
        'errstr' => $errstr,
        'errfile' => $errfile,
        'errline' => $errline,
        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
    ];

    SystemLogger::log($level, 'PHP_ERROR', $errorMsg, $details);

    return false; // Let normal PHP error handling continue
}

function global_exception_handler($exception) {
    $errorMsg = "Uncaught Exception: " . $exception->getMessage();
    
    $details = [
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ];

    // Uncaught exceptions crash the request execution, therefore marked as CRITICAL
    SystemLogger::critical('PHP_EXCEPTION', $errorMsg, $details);
}

set_error_handler("global_error_handler");
set_exception_handler("global_exception_handler");
?>

