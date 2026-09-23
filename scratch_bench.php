<?php
session_start();
$_SESSION['admin_logged'] = true;
$_SESSION['admin_user'] = 'admin';
$_SESSION['admin_role'] = 1;

function test_url($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: gtlmanh.id.vn']);
    curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $start = microtime(true);
    $resp = curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000, 2);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    echo "RESP BODY: " . var_export($resp, true) . "\n";
    curl_close($ch);
    return ['time' => $time, 'code' => $code, 'len' => strlen($resp), 'err' => $err];
}

echo "=== BENCHMARK KINH TẾ & ANALYTICS ===\n";

$t1 = test_url('http://127.0.0.1/1/admin_dashboard.php?page=economy&sub=analytics');
echo "Phân Tích Truy Cập (Lần 1): {$t1['time']}ms | Code: {$t1['code']} | Size: {$t1['len']} bytes | Err: {$t1['err']}\n";
var_dump($t1);

$t2 = test_url('http://127.0.0.1/1/admin_dashboard.php?page=economy&sub=analytics');
echo "Phân Tích Truy Cập (Lần 2 - Cached): {$t2['time']}ms | Code: {$t2['code']} | Size: {$t2['len']} bytes\n";

$r1 = test_url('http://127.0.0.1/1/admin_dashboard.php?page=economy&sub=revenue');
echo "Báo Cáo Doanh Thu (Lần 1): {$r1['time']}ms | Code: {$r1['code']} | Size: {$r1['len']} bytes | Err: {$r1['err']}\n";

$r2 = test_url('http://127.0.0.1/1/admin_dashboard.php?page=economy&sub=revenue');
echo "Báo Cáo Doanh Thu (Lần 2 - Cached): {$r2['time']}ms | Code: {$r2['code']} | Size: {$r2['len']} bytes\n";
