<?php
/**
 * Cron Auction Settlement
 * Run this every 1-5 minutes
 */
include 'db_connect.php';
// Reuse settleAuction logic from api_auction.php or implement here
// Since we can't easily include api functions without side effects, 
// I'll implement the settlement logic here as well.

$sql = "SELECT id FROM auctions WHERE status = 'active' AND ends_at <= NOW()";
$res = $conn->query($sql);

if ($res->num_rows > 0) {
    echo "Processing " . $res->num_rows . " auctions...\n";
    while ($row = $res->fetch_assoc()) {
        settleAuctionInternal($row['id'], $conn);
    }
} else {
    echo "No expired auctions found.\n";
}

function settleAuctionInternal($auctionId, $conn) {
    $auctionRes = $conn->query("SELECT * FROM auctions WHERE id = $auctionId");
    $auction = $auctionRes->fetch_assoc();
    
    if (!$auction || $auction['status'] !== 'active') return;

    $conn->begin_transaction();
    try {
        if ($auction['winner_id'] === null) {
            // No winner
            $conn->query("UPDATE auctions SET status = 'ended' WHERE id = $auctionId");
        } else {
            // 1. Pay Seller (95%)
            $payout = $auction['current_price'] * 0.95;
            $conn->query("UPDATE users SET Money = Money + $payout WHERE Iduser = {$auction['seller_id']}");

            // 2. Transfer Ownership
            $type = $auction['item_type'];
            $itemId = $auction['item_id'];
            $table = "";
            $idCol = "";

            switch ($type) {
                case 'avatar_frame': $table = "user_avatar_frames"; $idCol = "avatar_frame_id"; break;
                case 'theme': $table = "user_themes"; $idCol = "theme_id"; break;
                case 'cursor': $table = "user_cursors"; $idCol = "cursor_id"; break;
                case 'chat_frame': $table = "user_chat_frames"; $idCol = "chat_frame_id"; break;
                case 'title': $table = "user_achievements"; $idCol = "achievement_id"; break;
            }

            if ($table) {
                $conn->query("DELETE FROM $table WHERE user_id = {$auction['seller_id']} AND $idCol = $itemId");
                $check = $conn->query("SELECT id FROM $table WHERE user_id = {$auction['winner_id']} AND $idCol = $itemId");
                if ($check->num_rows === 0) {
                    $conn->query("INSERT INTO $table (user_id, $idCol) VALUES ({$auction['winner_id']}, $itemId)");
                }
            }

            // 3. Set Status
            $conn->query("UPDATE auctions SET status = 'ended' WHERE id = $auctionId");

            // 4. Notifications
            $msgWinner = "Chúc mừng! Bạn đã thắng đấu giá vật phẩm {$auction['item_name']}.";
            $msgSeller = "Vật phẩm {$auction['item_name']} của bạn đã được bán với giá " . number_format($auction['current_price']) . " gtlm.";
            $conn->query("INSERT INTO notifications (user_id, message) VALUES ({$auction['winner_id']}, '$msgWinner'), ({$auction['seller_id']}, '$msgSeller')");
        }
        $conn->commit();
        echo "Settled auction ID: $auctionId\n";
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error settling auction ID $auctionId: " . $e->getMessage() . "\n";
    }
}
?>
