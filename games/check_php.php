<?php
/**
 * PHP Maintenance Tool - Tag Balance Checker
 * Scans all .php files in the current directory for unbalanced <?php and ?> tags.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

function check_php_tags($filepath) {
    $content = file_get_contents($filepath);
    if ($content === false) {
        return ["ERROR: Could not read file."];
    }

    $issues = [];
    
    // 1. Total count check
    preg_match_all('/<\?php|<\?=/i', $content, $opens);
    preg_match_all('/\?>/', $content, $closes);
    
    $openCount = count($opens[0]);
    $closeCount = count($closes[0]);
    
    if ($openCount !== $closeCount) {
        $issues[] = "Mismatched total tags: Opens($openCount) vs Closes($closeCount)";
    }

    // 2. Sequential balance check (line by line)
    $lines = explode("\n", $content);
    $balance = 0;
    foreach ($lines as $index => $line) {
        $lineNum = $index + 1;
        
        preg_match_all('/<\?php|<\?=/i', $line, $lineOpens);
        preg_match_all('/\?>/', $line, $lineCloses);
        
        $o = count($lineOpens[0]);
        $c = count($lineCloses[0]);
        
        $balance += $o - $c;
        
        if ($balance < 0) {
            $issues[] = "Line $lineNum: Found closing tag '?>' without matching open tag. Content: " . trim($line);
            $balance = 0;
        }
    }
    
    // 3. Check for session_start() position
    if (strpos($content, 'session_start()') !== false) {
        $pos = strpos($content, 'session_start()');
        $preceding = substr($content, 0, $pos);
        
        if (strpos($preceding, '?>') !== false) {
            $lines_before = explode("\n", $preceding);
            $lineNum = count($lines_before);
            $issues[] = "Line $lineNum: 'session_start()' called after a PHP block was closed. This may cause 'headers already sent' errors.";
        }
    }

    return $issues;
}

// Main scan
$dir = __DIR__;
$files = scandir($dir);
$phpFiles = [];
foreach ($files as $f) {
    if (pathinfo($f, PATHINFO_EXTENSION) === 'php' && $f !== basename(__FILE__)) {
        $phpFiles[] = $f;
    }
}

echo "--- Scanning directory: $dir ---\n";
$totalIssues = 0;
$filesWithIssues = 0;

foreach ($phpFiles as $file) {
    $problems = check_php_tags($dir . DIRECTORY_SEPARATOR . $file);
    if (!empty($problems)) {
        $filesWithIssues++;
        echo "\n[!] ISSUE FOUND IN: $file\n";
        foreach ($problems as $p) {
            echo "    - $p\n";
            $totalIssues++;
        }
    }
}

echo "\n" . str_repeat("=", 40) . "\n";
if ($totalIssues === 0) {
    echo "SUCCESS: All " . count($phpFiles) . " PHP files passed the balance check.\n";
} else {
    echo "SUMMARY: Found $totalIssues issues across $filesWithIssues/" . count($phpFiles) . " files.\n";
}
echo str_repeat("=", 40) . "\n";
