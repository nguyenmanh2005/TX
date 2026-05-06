<?php
require 'db_connect.php';
$sql = "SELECT Name, ImageURL FROM users ORDER BY Money DESC LIMIT 5";
$result = $conn->query($sql);
echo "Avatar Debug:\n";
while($row = $result->fetch_assoc()) {
    echo "User: " . $row['Name'] . " | Path: " . $row['ImageURL'] . "\n";
    $path = $row['ImageURL'];
    if (strpos($path, 'game/') === 0) $path = substr($path, 5);
    echo "  Processed: " . $path . " | Exists: " . (file_exists($path) ? "YES" : "NO") . "\n";
}
?>
