require_once 'db_connect.php';

$games = [
    ['Minesweeper Premium', 'games/mines.php'],
    ['Limbo Rocket', 'games/limbo.php'],
    ['Tower Climb', 'games/tower.php'],
    ['Scratch Card', 'games/scratch.php'],
    ['Plinko Royale', 'games/plinko.php'],
    ['Crash Flight', 'games/crash.php']
];

foreach($games as $g) {
    $s = $conn->prepare("INSERT INTO games (Name, Link) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM games WHERE Link = ?)");
    $s->bind_param("sss", $g[0], $g[1], $g[1]);
    $s->execute();
    if ($s->affected_rows > 0) echo "Registered: " . $g[0] . "\n";
    else echo "Skipped (exists): " . $g[0] . "\n";
    $s->close();
}
?>
