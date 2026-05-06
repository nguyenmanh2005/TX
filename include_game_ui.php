<?php
/**
 * Helper file để include UI/UX enhanced cho games
 * Sử dụng: require_once 'include_game_ui.php';
 */

// Include các file CSS cho game UI enhanced
function getGameUICSSIncludes()
{
    return '
    <!-- Game UI Enhanced CSS -->
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <link rel="stylesheet" href="assets/css/game-effects.css">
    ';
}

// Include các file JS cho game UI enhanced
function getGameUIJSIncludes()
{
    return '
    <!-- Game UI Enhanced JavaScript -->
    <script src="assets/js/game-ui-enhanced.js"></script>
    <script src="assets/js/game-confetti.js"></script>
    ';
}

// Echo CSS includes
function echoGameUICSS()
{
    echo getGameUICSSIncludes();
}

// Echo JS includes
function echoGameUIJS()
{
    echo getGameUIJSIncludes();
}

// Helper để format số gtlm
function formatMoney($amount)
{
    return number_format($amount, 0, ',', '.') . ' gtlm';
}

// Helper để tạo game button với class enhanced
function createGameButton($text, $type = 'primary', $attributes = '')
{
    $class = 'game-btn-enhanced game-btn-' . $type . '-enhanced';
    return '<button class="' . $class . '" ' . $attributes . '>' . htmlspecialchars($text) . '</button>';
}

// Helper để tạo game result box
function createGameResultBox($type, $message, $emojis = [])
{
    $class = 'game-result-enhanced game-result-' . $type . '-enhanced';
    $emojiHTML = '';
    if (!empty($emojis)) {
        foreach ($emojis as $emoji) {
            $emojiHTML .= '<span class="result-emoji-enhanced">' . htmlspecialchars($emoji) . '</span>';
        }
    }
    $messageClass = 'result-message-enhanced result-message-' . $type . '-enhanced';

    return '
    <div class="' . $class . '">
        <div class="game-result-content">
            ' . ($emojiHTML ? '<div class="result-emojis">' . $emojiHTML . '</div>' : '') . '
            <div class="' . $messageClass . '">' . htmlspecialchars($message) . '</div>
        </div>
    </div>';
}

// Helper để tạo game balance display
function createGameBalance($balance)
{
    return '
    <div class="game-balance-enhanced">
        <span class="balance-icon">💰</span>
        <span class="balance-value" id="userBalance" data-balance="' . $balance . '">' . formatMoney($balance) . '</span>
    </div>';
}

// Helper để tạo game header
function createGameHeader($title, $balance)
{
    return '
    <div class="game-header-enhanced">
        <h1 class="game-title-enhanced">' . htmlspecialchars($title) . '</h1>
        ' . createGameBalance($balance) . '
    </div>';
}

// Helper để tạo game controls box
function createGameControls($content)
{
    return '<div class="game-controls-enhanced">' . $content . '</div>';
}

// Helper để tạo control group
function createControlGroup($label, $input, $quickAmounts = [])
{
    $quickHTML = '';
    if (!empty($quickAmounts)) {
        $quickHTML = '<div class="bet-quick-amounts-enhanced">';
        foreach ($quickAmounts as $amount) {
            $quickHTML .= '<button type="button" class="bet-quick-btn-enhanced" data-amount="' . $amount . '">' . formatMoney($amount) . '</button>';
        }
        $quickHTML .= '</div>';
    }

    return '
    <div class="control-group-enhanced">
        <label class="control-label-enhanced">' . htmlspecialchars($label) . '</label>
        ' . $input . '
        ' . $quickHTML . '
    </div>';
}

// Helper để trigger confetti
function getConfettiScript($type = 'win')
{
    if ($type === 'big-win') {
        return '<script>if(window.gameConfetti) { setTimeout(() => window.gameConfetti.createBigWinConfetti(), 300); }</script>';
    } else {
        return '<script>if(window.gameConfetti) { setTimeout(() => window.gameConfetti.createWinConfetti(), 300); }</script>';
    }
}

?>