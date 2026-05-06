<?php
/**
 * Website Tracking System
 * Captures visitor data and stores it in site_analytics table
 */

function get_browser_name($user_agent)
{
    if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/'))
        return 'Opera';
    elseif (strpos($user_agent, 'Edge') || strpos($user_agent, 'Edg/'))
        return 'Edge';
    elseif (strpos($user_agent, 'Chrome'))
        return 'Chrome';
    elseif (strpos($user_agent, 'Safari'))
        return 'Safari';
    elseif (strpos($user_agent, 'Firefox'))
        return 'Firefox';
    elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7'))
        return 'Internet Explorer';
    return 'Other';
}

function get_os_name($user_agent)
{
    $os_platform = "Unknown OS";
    $os_array = array(
        '/windows nt 10/i' => 'Windows 10',
        '/windows nt 6.3/i' => 'Windows 8.1',
        '/windows nt 6.2/i' => 'Windows 8',
        '/windows nt 6.1/i' => 'Windows 7',
        '/windows nt 6.0/i' => 'Windows Vista',
        '/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
        '/windows nt 5.1/i' => 'Windows XP',
        '/windows xp/i' => 'Windows XP',
        '/windows nt 5.0/i' => 'Windows 2000',
        '/windows me/i' => 'Windows ME',
        '/win98/i' => 'Windows 98',
        '/win95/i' => 'Windows 95',
        '/win16/i' => 'Windows 3.11',
        '/macintosh|mac os x/i' => 'Mac OS X',
        '/mac_powerpc/i' => 'Mac OS 9',
        '/linux/i' => 'Linux',
        '/ubuntu/i' => 'Ubuntu',
        '/iphone/i' => 'iPhone',
        '/ipod/i' => 'iPod',
        '/ipad/i' => 'iPad',
        '/android/i' => 'Android',
        '/blackberry/i' => 'BlackBerry',
        '/webos/i' => 'Mobile'
    );

    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform = $value;
            break;
        }
    }
    return $os_platform;
}

function get_device_type($user_agent)
{
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobi))/i', $user_agent)) {
        return 'Tablet';
    }
    if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $user_agent)) {
        return 'Mobile';
    }
    return 'Desktop';
}

function get_geo_info($ip)
{
    // Basic caching to avoid hitting the API too much if possible, 
    // but for now, we'll just fetch. ip-api.com is free for < 45 req/min.
    if ($ip == '127.0.0.1' || $ip == '::1')
        return ['country' => 'Local', 'city' => 'Local'];

    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $res = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city", false, $ctx);
    if ($res) {
        $data = json_decode($res, true);
        if ($data && $data['status'] == 'success') {
            return ['country' => $data['country'], 'city' => $data['city']];
        }
    }
    return ['country' => 'Unknown', 'city' => 'Unknown'];
}

function track_visit($conn)
{
    // Avoid tracking some common files or admin actions if needed
    $page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // Ignore some files
    $ignored_patterns = ['/assets/', '/img/', '/css/', '/js/'];
    foreach ($ignored_patterns as $pattern) {
        if (strpos($page_url, $pattern) !== false)
            return;
    }

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_id = $_SESSION['Iduser'] ?? 0;

    // 1. Exclude Admin Role (Role 1)
    if (isset($_SESSION['Role']) && $_SESSION['Role'] == 1) {
        return;
    }

    // 2. Exclude specific IP (Optional, commented out for local bot testing)
    $excluded_ips = ['14.248.83.163'];
    // if (in_array($ip_address, $excluded_ips)) return;

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
    $http_version = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $browser = get_browser_name($user_agent);
    $os = get_os_name($user_agent);
    $device = get_device_type($user_agent);
    $geo = get_geo_info($ip_address);

    // Determine source
    $source = 'Direct';
    if ($referrer !== 'Direct') {
        $ref_host = parse_url($referrer, PHP_URL_HOST);
        if ($ref_host) {
            if (strpos($ref_host, 'google') !== false)
                $source = 'Google';
            elseif (strpos($ref_host, 'facebook') !== false)
                $source = 'Facebook';
            elseif (strpos($ref_host, 'youtube') !== false)
                $source = 'YouTube';
            elseif (strpos($ref_host, 'twitter') !== false || strpos($ref_host, 't.co') !== false)
                $source = 'Twitter';
            elseif (strpos($ref_host, $_SERVER['HTTP_HOST']) !== false)
                $source = 'Internal';
            else
                $source = $ref_host;
        }
    }

    $stmt = $conn->prepare("INSERT INTO site_analytics (user_id, ip_address, user_agent, browser, os, device, country, city, http_version, request_method, page_url, referrer, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issssssssssss", $user_id, $ip_address, $user_agent, $browser, $os, $device, $geo['country'], $geo['city'], $http_version, $request_method, $page_url, $referrer, $source);
        $stmt->execute();
        $stmt->close();
    }
}

// Global execution if included
if (isset($conn)) {
    track_visit($conn);
}
?>