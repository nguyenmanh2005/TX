<?php
/**
 * 📺 Sảnh LiveStream v4.0 - Trận Địa Live 24/7 Stream Hub
 * Giao diện Esports Hiện Đại, Rõ Ràng, Dễ Nhìn, Đầy Đủ 61 Bàn Live Song Song
 * Đồng bộ thời gian thực time() % 30s | Đồng bộ Theme & Tương thích Desktop/Mobile
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = (int)$_SESSION['Iduser'];

// Lấy thông tin user
$stmtUser = $conn->prepare("SELECT Name, Money, ImageURL FROM users WHERE Iduser = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userName = $userData['Name'] ?? 'Đạo Hữu';
$userMoney = (float)($userData['Money'] ?? 0);
$userAvatar = !empty($userData['ImageURL']) ? $userData['ImageURL'] : '../img/avatar_default.png';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sảnh Trận Địa Live 24/7 | GTLM Esports Stream Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Cursor Styling */
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('img/chuot.png'), default;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .stream-card, .cat-chip,
        a *, button *, [onclick] *, .stream-card *, .cat-chip *, .swal2-popup button, .swal2-popup [onclick] {
            cursor: url('img/tay.png'), pointer !important;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
            opacity: 0.7;
        }

        :root {
            --bg-body: #070a13;
            --bg-surface: rgba(15, 23, 42, 0.78);
            --bg-surface-elevated: rgba(30, 41, 59, 0.85);
            --bg-glass: rgba(18, 26, 47, 0.65);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(139, 92, 246, 0.35);

            --accent-primary: #6366f1;
            --accent-violet: #8b5cf6;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --accent-amber: #f59e0b;
            --accent-cyan: #06b6d4;

            --text-heading: #ffffff;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --text-dim: #64748b;

            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 28px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-body);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.5;
            <?= isset($bgGradientCSS) ? "background-image: $bgGradientCSS; background-attachment: fixed;" : "" ?>
        }

        h1, h2, h3, h4, h5, h6, .brand-font {
            font-family: 'Outfit', sans-serif;
            color: var(--text-heading);
            letter-spacing: -0.02em;
        }

        /* ── Modern Scrollbars ── */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ── Top Header Bar ── */
        .hub-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(7, 10, 19, 0.92);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-glass);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .hub-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .hub-brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #4f46e5, #9333ea);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #fff;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hub-brand-title {
            display: flex;
            flex-direction: column;
        }

        .hub-brand-name {
            font-size: 1.15rem;
            font-weight: 900;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hub-live-badge {
            background: linear-gradient(135deg, var(--accent-rose), #e11d48);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 900;
            padding: 2px 8px;
            border-radius: 6px;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 0 10px rgba(244, 63, 94, 0.5);
            animation: pulse-live 2s infinite;
        }

        @keyframes pulse-live {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.85; transform: scale(0.97); }
        }

        .hub-brand-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Search input */
        .hub-search-wrapper {
            flex: 1;
            max-width: 480px;
            position: relative;
        }

        .hub-search-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            border-radius: 14px;
            padding: 10px 18px 10px 42px;
            font-size: 0.88rem;
            color: #fff;
            outline: none;
            transition: all 0.25s ease;
            font-family: inherit;
        }

        .hub-search-input:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-violet);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }

        .hub-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
        }

        .hub-search-count {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.72rem;
            color: var(--accent-cyan);
            font-weight: 700;
            background: rgba(6, 182, 212, 0.12);
            padding: 2px 8px;
            border-radius: 8px;
            display: none;
        }

        /* Header Navigation Actions */
        .hub-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-tournament-shortcut {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(234, 88, 12, 0.2));
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: var(--accent-amber);
            font-weight: 800;
            font-size: 0.82rem;
            padding: 8px 14px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s;
        }

        .btn-tournament-shortcut:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.3), rgba(234, 88, 12, 0.35));
            border-color: var(--accent-amber);
            transform: translateY(-1px);
            color: #fff;
        }

        .hub-user-wallet {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 7px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            color: var(--accent-emerald);
            font-size: 0.88rem;
            white-space: nowrap;
        }

        .hub-user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            border-radius: 14px;
            padding: 4px 10px 4px 6px;
            text-decoration: none;
            color: #fff;
            transition: all 0.2s;
        }

        .hub-user-profile:hover {
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .hub-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hub-user-name {
            font-size: 0.85rem;
            font-weight: 700;
            max-width: 100px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-back-home {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-glass);
            color: var(--text-muted);
            padding: 8px 12px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-back-home:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        /* ── Realtime Global Engine Status Banner ── */
        .hub-status-ticker {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-glass);
            padding: 8px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            gap: 15px;
            overflow-x: auto;
        }

        .status-indicators {
            display: flex;
            align-items: center;
            gap: 16px;
            white-space: nowrap;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
        }

        .status-item b {
            color: #fff;
        }

        .status-dot-active {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-emerald);
            box-shadow: 0 0 8px var(--accent-emerald);
            animation: pulse-dot 1.5s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.85); }
        }

        .cycle-progress-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .cycle-progress-bar {
            width: 140px;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .cycle-progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--accent-emerald), var(--accent-cyan));
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        /* ── Main Layout Container ── */
        .hub-container {
            max-width: 1680px;
            margin: 0 auto;
            padding: 20px 24px 60px 24px;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
        }

        .hub-main-col {
            display: flex;
            flex-direction: column;
            gap: 22px;
            min-width: 0;
        }

        /* ── Spotlight Hero Showcase ── */
        .spotlight-hero {
            background: linear-gradient(135deg, rgba(24, 18, 43, 0.9), rgba(11, 15, 28, 0.95));
            border: 1px solid var(--border-highlight);
            border-radius: var(--radius-xl);
            padding: 24px;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.45);
        }

        .spotlight-hero::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.25) 0%, transparent 70%);
            filter: blur(40px);
            pointer-events: none;
        }

        .spotlight-hero::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.2) 0%, transparent 70%);
            filter: blur(40px);
            pointer-events: none;
        }

        .spotlight-left {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }

        .spotlight-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge-live-featured {
            background: linear-gradient(135deg, var(--accent-rose), #be123c);
            color: #fff;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 0 12px rgba(244, 63, 94, 0.4);
        }

        .badge-viewers-featured {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-glass);
            color: #e2e8f0;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-phase-pill {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: var(--accent-amber);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .spotlight-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .spotlight-streamer-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            border: 2px solid var(--accent-violet);
            box-shadow: 0 0 12px rgba(139, 92, 246, 0.4);
            object-fit: cover;
        }

        .spotlight-title-group h2 {
            font-size: 1.65rem;
            font-weight: 900;
            line-height: 1.2;
            color: #fff;
            margin-bottom: 2px;
        }

        .spotlight-streamer-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .spotlight-streamer-info b {
            color: #fff;
        }

        .spotlight-verified-icon {
            color: var(--accent-cyan);
            font-size: 0.8rem;
        }

        .spotlight-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-watch-spotlight {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            padding: 12px 26px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.45);
            transition: all 0.25s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-watch-spotlight:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.6);
            color: #fff;
        }

        .btn-preview-switch {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-glass);
            color: var(--text-body);
            padding: 12px 18px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-preview-switch:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }

        /* Spotlight Right: Quick Table Carousel / Top Picks */
        .spotlight-right {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .spotlight-right-header {
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .spotlight-mini-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .spotlight-mini-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-glass);
            border-radius: 14px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .spotlight-mini-card:hover, .spotlight-mini-card.active-preview {
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateX(4px);
        }

        .mini-card-icon {
            font-size: 1.5rem;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mini-card-info {
            flex: 1;
            min-width: 0;
        }

        .mini-card-name {
            font-weight: 800;
            font-size: 0.88rem;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mini-card-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mini-card-viewers {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--accent-rose);
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        /* ── Categories Bar & Filter Toolbar ── */
        .hub-toolbar {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .category-scroll-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none;
        }

        .category-scroll-bar::-webkit-scrollbar {
            display: none;
        }

        .cat-chip {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .cat-chip:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .cat-chip.active {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-violet));
            color: #fff;
            border-color: transparent;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .cat-badge-count {
            background: rgba(0, 0, 0, 0.25);
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .hub-controls-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .stream-counter-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        .stream-counter-text b {
            color: #fff;
        }

        .sort-select-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sort-select {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid var(--border-glass);
            color: var(--text-body);
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        .sort-select:focus {
            border-color: var(--accent-violet);
        }

        /* ── Stream Cards Grid (The Core Showcase) ── */
        .stream-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 18px;
        }

        .stream-card {
            background: var(--bg-surface);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .stream-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.45);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(139, 92, 246, 0.15);
        }

        /* Card Top Thumbnail Visual */
        .card-visual {
            aspect-ratio: 16/9;
            background: radial-gradient(circle at center, #1e1b4b 0%, #0d111c 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-bottom: 1px solid var(--border-glass);
        }

        .card-bg-glow {
            position: absolute;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            filter: blur(35px);
            opacity: 0.4;
            pointer-events: none;
        }

        .card-visual-icon {
            font-size: 3.2rem;
            position: relative;
            z-index: 2;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.6));
        }

        .stream-card:hover .card-visual-icon {
            transform: scale(1.12);
        }

        .card-badge-live {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--accent-rose);
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 4px;
            z-index: 3;
            box-shadow: 0 2px 8px rgba(244, 63, 94, 0.5);
        }

        .card-badge-table-id {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(8px);
            color: #cbd5e1;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 800;
            border: 1px solid var(--border-glass);
            z-index: 3;
        }

        .card-badge-viewers {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 3;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Card Content Body */
        .card-body {
            padding: 14px 16px 16px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .card-info-header {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .card-streamer-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
        }

        .card-title-group {
            flex: 1;
            min-width: 0;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-streamer-name {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-glass);
            padding: 6px 10px;
            border-radius: 10px;
            font-size: 0.75rem;
        }

        .card-phase-tag {
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .phase-betting { color: var(--accent-emerald); }
        .phase-shaking { color: var(--accent-amber); }
        .phase-result  { color: var(--accent-cyan); }

        .card-cta-btn {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-violet));
            color: #fff;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .stream-card:hover .card-cta-btn {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
        }

        /* ── Right Dock: Sidebar (Live Chat & Top Channels) ── */
        .hub-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: var(--bg-surface);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        /* Tab Switcher in Sidebar */
        .sidebar-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-glass);
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-tab-btn {
            flex: 1;
            padding: 12px 10px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .sidebar-tab-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.03);
        }

        .sidebar-tab-btn.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.06);
            border-bottom: 2px solid var(--accent-violet);
        }

        /* Tab 1: Live Chat Widget */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 440px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 0.82rem;
        }

        .chat-bubble {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 8px 10px;
            line-height: 1.4;
        }

        .chat-author {
            font-weight: 800;
            margin-right: 4px;
        }

        .chat-author.author-user { color: var(--accent-cyan); }
        .chat-author.author-bot  { color: var(--accent-violet); }
        .chat-author.author-vip  { color: var(--accent-amber); }
        .chat-author.author-system { color: var(--accent-rose); }

        .chat-text {
            color: var(--text-body);
            word-break: break-word;
        }

        .chat-reactions-bar {
            display: flex;
            gap: 6px;
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.25);
            border-top: 1px solid var(--border-glass);
            overflow-x: auto;
        }

        .reaction-chip {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            padding: 3px 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: transform 0.15s;
        }

        .reaction-chip:hover {
            transform: scale(1.2);
            background: rgba(255, 255, 255, 0.15);
        }

        .chat-input-bar {
            padding: 10px 12px;
            border-top: 1px solid var(--border-glass);
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            gap: 8px;
        }

        .chat-input-field {
            flex: 1;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-glass);
            border-radius: 10px;
            padding: 8px 12px;
            color: #fff;
            font-size: 0.82rem;
            outline: none;
            font-family: inherit;
        }

        .chat-input-field:focus {
            border-color: var(--accent-violet);
        }

        .btn-send-chat {
            background: var(--accent-primary);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-send-chat:hover {
            background: var(--accent-violet);
            transform: scale(1.05);
        }

        /* Tab 2: Top Live Channels */
        .top-channels-container {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 440px;
            overflow-y: auto;
        }

        .top-channel-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid transparent;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }

        .top-channel-row:hover {
            background: rgba(139, 92, 246, 0.12);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateX(3px);
        }

        .top-channel-rank {
            font-size: 0.78rem;
            font-weight: 900;
            color: var(--text-dim);
            width: 18px;
            text-align: center;
        }

        .top-channel-row:nth-child(1) .top-channel-rank { color: var(--accent-amber); }
        .top-channel-row:nth-child(2) .top-channel-rank { color: #cbd5e1; }
        .top-channel-row:nth-child(3) .top-channel-rank { color: #b45309; }

        .top-channel-icon {
            font-size: 1.3rem;
            width: 32px;
            text-align: center;
        }

        .top-channel-name {
            flex: 1;
            min-width: 0;
            font-size: 0.82rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #fff;
        }

        .top-channel-viewers {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--accent-rose);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Responsive Media Queries ── */
        @media (max-width: 1200px) {
            .hub-container {
                grid-template-columns: 1fr;
            }
            .hub-sidebar {
                order: 2;
            }
            .chat-container {
                height: 360px;
            }
        }

        @media (max-width: 860px) {
            .spotlight-hero {
                grid-template-columns: 1fr;
                padding: 18px;
            }
            .spotlight-right {
                display: none;
            }
            .hub-search-wrapper {
                max-width: 260px;
            }
        }

        @media (max-width: 640px) {
            .hub-header {
                padding: 10px 14px;
                flex-wrap: wrap;
            }
            .hub-search-wrapper {
                order: 3;
                max-width: 100%;
                width: 100%;
            }
            .hub-container {
                padding: 12px 14px 40px 14px;
            }
            .stream-grid {
                grid-template-columns: 1fr;
            }
            .hub-user-name, .btn-back-home span {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- ── Header ── -->
    <header class="hub-header">
        <a href="spectator.php" class="hub-brand">
            <div class="hub-brand-logo">
                <i class="fa fa-play"></i>
            </div>
            <div class="hub-brand-title">
                <div class="hub-brand-name">
                    GTLM STREAM HUB
                    <span class="hub-live-badge"><i class="fa fa-circle" style="font-size: 0.4rem;"></i> LIVE 24/7</span>
                </div>
                <div class="hub-brand-sub">Sảnh Trận Địa Trực Tiếp Đa Bàn</div>
            </div>
        </a>

        <!-- Global Search -->
        <div class="hub-search-wrapper">
            <i class="fa fa-search hub-search-icon"></i>
            <input type="text" class="hub-search-input" id="globalSearchInput" placeholder="Tìm kiếm bàn live, game, streamer..." oninput="handleSearch()">
            <span class="hub-search-count" id="searchMatchCount">61 BÀN</span>
        </div>

        <!-- Navigation Actions -->
        <div class="hub-header-actions">
            <a href="watch.php?id=61" class="btn-tournament-shortcut" title="Giải Đấu AI 30P">
                <i class="fa fa-trophy"></i>
                <span>GIẢI ĐẤU AI</span>
            </a>

            <div class="hub-user-wallet" title="Số dư tài khoản">
                <i class="fa fa-wallet" style="color: var(--accent-emerald);"></i>
                <span id="userMoneyDisplay"><?= number_format($userMoney) ?> GTLM</span>
            </div>

            <div class="hub-user-profile">
                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="hub-user-avatar" onerror="this.src='../img/avatar_default.png'">
                <span class="hub-user-name"><?= htmlspecialchars($userName) ?></span>
            </div>

            <a href="/1/index.php" class="btn-back-home" title="Trở về Sảnh Chính">
                <i class="fa fa-arrow-left"></i>
                <span>Sảnh Chính</span>
            </a>
        </div>
    </header>

    <!-- ── Realtime Global Engine Status Bar ── -->
    <div class="hub-status-ticker">
        <div class="status-indicators">
            <div class="status-item">
                <span class="status-dot-active"></span>
                <span>Hệ Thống Live: <b>61 Bàn Trực Tuyến</b></span>
            </div>
            <div class="status-item">
                <i class="fa fa-users" style="color: var(--accent-cyan);"></i>
                <span>Tổng Người Xem: <b id="totalViewersCounter">14,280+</b></span>
            </div>
            <div class="status-item">
                <i class="fa fa-bolt" style="color: var(--accent-amber);"></i>
                <span>Đồng Bộ Realtime: <b>Engine 30s Sync</b></span>
            </div>
        </div>

        <div class="cycle-progress-wrap">
            <span id="cyclePhaseLabel" style="font-weight: 800; color: var(--accent-emerald);">⏳ RA CHIÊU: 20s</span>
            <div class="cycle-progress-bar">
                <div class="cycle-progress-fill" id="cycleProgressBar"></div>
            </div>
        </div>
    </div>

    <!-- ── Main Layout ── -->
    <div class="hub-container">

        <!-- ── Left/Center Main Column ── -->
        <main class="hub-main-col">

            <!-- ── Spotlight Hero Showcase ── -->
            <section class="spotlight-hero" id="spotlightHeroSection">
                <div class="spotlight-left">
                    <div class="spotlight-badges">
                        <span class="badge-live-featured">
                            <i class="fa fa-circle" style="font-size: 0.45rem;"></i> ĐANG TRỰC TIẾP
                        </span>
                        <span class="badge-viewers-featured" id="spotlightViewerBadge">
                            <i class="fa fa-user"></i> 1,420 đang xem
                        </span>
                        <span class="badge-phase-pill" id="spotlightPhaseBadge">
                            ⏳ RA CHIÊU: 18s
                        </span>
                    </div>

                    <div class="spotlight-meta">
                        <img src="../img/avatar_default.png" alt="Streamer" class="spotlight-streamer-avatar" id="spotlightAvatar">
                        <div class="spotlight-title-group">
                            <h2 id="spotlightTitle">Thế Giới Linh Thú</h2>
                            <div class="spotlight-streamer-info">
                                <span id="spotlightStreamerName">Thánh Nữ Tiên Tri</span>
                                <i class="fa fa-check-circle spotlight-verified-icon" title="Streamer Xác Thực"></i>
                                <span>• Bàn ID: <b id="spotlightTableId">#01</b></span>
                            </div>
                        </div>
                    </div>

                    <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.4;" id="spotlightDesc">
                        Tứ Linh Hội Tụ - Đấu trường Linh Thú 3D kịch tính với bot auto-play 24/7 và tỷ lệ nhân thưởng cực khủng.
                    </p>

                    <div class="spotlight-actions">
                        <a href="watch.php?id=1" class="btn-watch-spotlight" id="btnWatchSpotlight">
                            <i class="fa fa-play"></i> VÀO BÀN XEM TRỰC TIẾP
                        </a>
                        <button class="btn-preview-switch" onclick="switchSpotlightNext()">
                            <i class="fa fa-random"></i> Đổi Kênh Khác
                        </button>
                    </div>
                </div>

                <!-- Spotlight Right: Quick Top Live Channels -->
                <div class="spotlight-right">
                    <div class="spotlight-right-header">
                        <span>🔥 KÊNH ĐỀ XUẤT</span>
                        <span style="color: var(--accent-amber);">CLICK ĐỂ XEM NHANH</span>
                    </div>

                    <div class="spotlight-mini-list" id="spotlightMiniList">
                        <!-- Populated via JavaScript -->
                    </div>
                </div>
            </section>

            <!-- ── Category Filter Bar & Toolbar ── -->
            <section class="hub-toolbar">
                <div class="category-scroll-bar" id="categoryBar">
                    <button class="cat-chip active" onclick="setCategoryFilter('all', this)">
                        <span>🔥 Tất Cả</span>
                        <span class="cat-badge-count" id="countAll">61</span>
                    </button>
                    <button class="cat-chip" onclick="setCategoryFilter('dice_xocdia', this)">
                        <span>🎲 Xóc Đĩa & Xí Ngầu</span>
                        <span class="cat-badge-count" id="countDice">7</span>
                    </button>
                    <button class="cat-chip" onclick="setCategoryFilter('cards_casino', this)">
                        <span>🃏 Game Bài & Casino</span>
                        <span class="cat-badge-count" id="countCards">21</span>
                    </button>
                    <button class="cat-chip" onclick="setCategoryFilter('chess_board', this)">
                        <span>♟️ Cờ & Trí Tuệ AI</span>
                        <span class="cat-badge-count" id="countChess">3</span>
                    </button>
                    <button class="cat-chip" onclick="setCategoryFilter('racing_beasts', this)">
                        <span>🐾 Linh Thú & Đua Xe</span>
                        <span class="cat-badge-count" id="countRacing">6</span>
                    </button>
                    <button class="cat-chip" onclick="setCategoryFilter('mini_arcade', this)">
                        <span>🚀 Mini Games & Nổ Hũ</span>
                        <span class="cat-badge-count" id="countMini">24</span>
                    </button>
                </div>

                <div class="hub-controls-bar">
                    <div class="stream-counter-text">
                        Đang hiển thị: <b id="displayedStreamCount">61</b> bàn phát sóng song song
                    </div>

                    <div class="sort-select-wrap">
                        <label for="sortSelect" style="font-size: 0.8rem; color: var(--text-muted);">Sắp xếp:</label>
                        <select id="sortSelect" class="sort-select" onchange="renderGrid()">
                            <option value="viewers_desc">👥 Nhiều người xem nhất</option>
                            <option value="id_asc">🔢 Số bàn (1 → 61)</option>
                            <option value="name_asc">🔤 Tên A → Z</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- ── Stream Cards Grid ── -->
            <section class="stream-grid" id="streamGridContainer">
                <!-- Rendered dynamically via JavaScript -->
            </section>

        </main>

        <!-- ── Right Sidebar Dock (Live Chat & Top Channels) ── -->
        <aside class="hub-sidebar">

            <!-- Sidebar Card with Tab Navigation -->
            <div class="sidebar-card">
                <div class="sidebar-tabs">
                    <button class="sidebar-tab-btn active" onclick="switchSidebarTab('chat', this)">
                        <i class="fa fa-comments"></i> CHAT TRẬN ĐỊA
                    </button>
                    <button class="sidebar-tab-btn" onclick="switchSidebarTab('top', this)">
                        <i class="fa fa-chart-line"></i> TOP BÀN LIVE
                    </button>
                </div>

                <!-- Tab 1: Live Chat -->
                <div id="tabContentChat" class="chat-container">
                    <div class="chat-messages" id="chatMessagesBox">
                        <div class="chat-bubble">
                            <span class="chat-author author-system"><i class="fa fa-bullhorn"></i> Hệ Thống:</span>
                            <span class="chat-text">Chào mừng bạn đến với Sảnh Trận Địa Live 24/7! 61 Bàn Live đồng bộ 30s đã sẵn sàng.</span>
                        </div>
                        <div class="chat-bubble">
                            <span class="chat-author author-vip">Lão Tiên Tri:</span>
                            <span class="chat-text">Bàn #02 Trận Địa Trắng Đỏ cầu chẵn đang chạy rất mượt nha các đạo hữu!</span>
                        </div>
                        <div class="chat-bubble">
                            <span class="chat-author author-bot">AlphaX AI:</span>
                            <span class="chat-text">Bàn #61 Cờ Caro đang diễn ra trận thư hùng phân định cao thấp, vào cổ vũ nhé!</span>
                        </div>
                    </div>

                    <!-- Quick Reactions -->
                    <div class="chat-reactions-bar">
                        <span class="reaction-chip" onclick="quickSendReaction('🔥')">🔥</span>
                        <span class="reaction-chip" onclick="quickSendReaction('🚀')">🚀</span>
                        <span class="reaction-chip" onclick="quickSendReaction('🍀')">🍀</span>
                        <span class="reaction-chip" onclick="quickSendReaction('💎')">💎</span>
                        <span class="reaction-chip" onclick="quickSendReaction('❤️')">❤️</span>
                        <span class="reaction-chip" onclick="quickSendReaction('👏')">👏</span>
                    </div>

                    <!-- Chat Input -->
                    <form class="chat-input-bar" onsubmit="handleSendChat(event)">
                        <input type="text" class="chat-input-field" id="chatInputField" placeholder="Nhập bình luận của bạn..." autocomplete="off">
                        <button type="submit" class="btn-send-chat" title="Gửi tin nhắn">
                            <i class="fa fa-paper-plane"></i>
                        </button>
                    </form>
                </div>

                <!-- Tab 2: Top Live Channels -->
                <div id="tabContentTop" class="top-channels-container" style="display: none;">
                    <!-- Rendered via JavaScript -->
                </div>

            </div>

        </aside>

    </div>

    <!-- ── ThreeJS Background Canvas ── -->
    <canvas id="threejs-background"></canvas>

    <!-- ── Scripts ── -->
    <script>
        // Global State
        let allTables = [];
        let currentCycleState = { phase: 'betting', time_left: 20, cycle_sec: 0 };
        let activeCategory = 'all';
        let currentSpotlightId = 1;
        let searchQuery = '';

        // Category Game Mappings for 61 Tables
        const categoryMap = {
            dice_xocdia: ['xocdia', 'sicbo', 'dice', 'craps', 'fantan', 'yahtzee', 'coinflip'],
            cards_casino: [
                'baccarat', 'blackjack', 'poker', 'holdem', 'dragontiger', 'roulette',
                'samloc', 'tusac', 'threecard', 'paigow', 'videopoker', 'war',
                'reddog', 'pontoon', 'letitride', 'caribbean', 'bj', 'bjo',
                'blackjack_multi', 'banharc', 'hilo'
            ],
            chess_board: ['pvp_caro', 'mahjong', 'battleroyale'],
            racing_beasts: ['baucua', 'daga', 'cyber_racing', 'duangua', 'horserace', 'horserace_pvp'],
            mini_arcade: [
                'crash', 'plinko', 'plinko_v2', 'plinko_v3', 'slot', 'mines', 'minesweeper',
                'tower', 'tower_of_gods', 'gacha_cards', 'lottery', 'keno', 'vietlott',
                'bingo', 'megaspin', 'limbo', 'greedy_cave', 'farm', 'hopmu',
                'jojo_battle', 'rps', 'ruttham', 'scratch', 'number'
            ]
        };

        // Determine Category of a game
        function getGameCategory(gameCode) {
            const code = (gameCode || '').toLowerCase();
            for (const [catKey, list] of Object.entries(categoryMap)) {
                if (list.includes(code)) return catKey;
            }
            return 'mini_arcade';
        }

        // Fetch Live Tables from API
        function fetchLiveTables() {
            $.get('api_spectator.php?action=get_tables', function(res) {
                if (res && res.success && Array.isArray(res.tables)) {
                    allTables = res.tables;
                    if (res.state) {
                        currentCycleState = res.state;
                        updateCycleProgress(res.state);
                    }
                    updateCategoryCounts();
                    renderSpotlight();
                    renderSpotlightMini();
                    renderTopChannels();
                    renderGrid();
                }
            });
        }

        // Update Global 30s Cycle Progress
        function updateCycleProgress(state) {
            const sec = state.cycle_sec || 0;
            const percent = Math.min(100, Math.round((sec / 30) * 100));
            $('#cycleProgressBar').css('width', percent + '%');

            let phaseText = '';
            let phaseColor = '#10b981';

            if (state.phase === 'betting') {
                phaseText = `⏳ RA CHIÊU CƯỢC: ${state.time_left}s`;
                phaseColor = '#10b981';
            } else if (state.phase === 'shaking') {
                phaseText = `🎲 NÍN THỞ LẮC 3D: ${state.time_left}s`;
                phaseColor = '#f59e0b';
            } else {
                phaseText = `🏆 TRẢ THƯỞNG: ${state.time_left}s`;
                phaseColor = '#06b6d4';
            }

            $('#cyclePhaseLabel').text(phaseText).css('color', phaseColor);
            $('#spotlightPhaseBadge').text(phaseText);
        }

        // Update Category Count Badges
        function updateCategoryCounts() {
            $('#countAll').text(allTables.length);

            let counts = { dice_xocdia: 0, cards_casino: 0, chess_board: 0, racing_beasts: 0, mini_arcade: 0 };
            allTables.forEach(t => {
                const cat = getGameCategory(t.game_code);
                if (counts[cat] !== undefined) counts[cat]++;
            });

            $('#countDice').text(counts.dice_xocdia);
            $('#countCards').text(counts.cards_casino);
            $('#countChess').text(counts.chess_board);
            $('#countRacing').text(counts.racing_beasts);
            $('#countMini').text(counts.mini_arcade);

            // Calculate total viewers
            const totalViewers = allTables.reduce((acc, t) => acc + (t.viewers || 0), 0);
            $('#totalViewersCounter').text(totalViewers.toLocaleString() + '+');
        }

        // Render Featured Spotlight
        function renderSpotlight() {
            const table = allTables.find(t => t.id === currentSpotlightId) || allTables[0];
            if (!table) return;

            $('#spotlightTitle').text(table.name);
            $('#spotlightStreamerName').text(table.streamer_name);
            $('#spotlightTableId').text('#' + (table.id < 10 ? '0' : '') + table.id);
            $('#spotlightViewerBadge').html(`<i class="fa fa-user"></i> ${(table.viewers || 250).toLocaleString()} đang xem`);
            $('#spotlightDesc').text((table.icon || '🔥') + ' ' + (table.desc || 'Trận Địa Trực Tiếp 24/7') + ' — Hệ thống phát sóng realtime đồng bộ.');
            $('#btnWatchSpotlight').attr('href', 'watch.php?id=' + table.id);

            if (table.streamer_avatar) {
                $('#spotlightAvatar').attr('src', table.streamer_avatar);
            }
        }

        // Render Spotlight Mini Picks (Right column of hero)
        function renderSpotlightMini() {
            const sortedByViewers = [...allTables].sort((a, b) => (b.viewers || 0) - (a.viewers || 0)).slice(0, 3);
            const html = sortedByViewers.map(t => `
                <div class="spotlight-mini-card ${t.id === currentSpotlightId ? 'active-preview' : ''}" onclick="selectSpotlight(${t.id})">
                    <div class="mini-card-icon">${t.icon || '🎮'}</div>
                    <div class="mini-card-info">
                        <div class="mini-card-name">${t.name}</div>
                        <div class="mini-card-sub">${t.streamer_name}</div>
                    </div>
                    <div class="mini-card-viewers">
                        <i class="fa fa-fire"></i> ${(t.viewers || 0)}
                    </div>
                </div>
            `).join('');
            $('#spotlightMiniList').html(html);
        }

        function selectSpotlight(id) {
            currentSpotlightId = id;
            renderSpotlight();
            renderSpotlightMini();
        }

        function switchSpotlightNext() {
            if (!allTables.length) return;
            const currentIdx = allTables.findIndex(t => t.id === currentSpotlightId);
            const nextIdx = (currentIdx + 1) % allTables.length;
            selectSpotlight(allTables[nextIdx].id);
        }

        // Render Stream Cards Grid
        function renderGrid() {
            let filtered = allTables.filter(t => {
                // Category Filter
                if (activeCategory !== 'all') {
                    const cat = getGameCategory(t.game_code);
                    if (cat !== activeCategory) return false;
                }
                // Search Filter
                if (searchQuery) {
                    const q = searchQuery.toLowerCase();
                    const nameMatch = (t.name || '').toLowerCase().includes(q);
                    const streamerMatch = (t.streamer_name || '').toLowerCase().includes(q);
                    const descMatch = (t.desc || '').toLowerCase().includes(q);
                    if (!nameMatch && !streamerMatch && !descMatch) return false;
                }
                return true;
            });

            // Sorting
            const sortMode = $('#sortSelect').val();
            if (sortMode === 'viewers_desc') {
                filtered.sort((a, b) => (b.viewers || 0) - (a.viewers || 0));
            } else if (sortMode === 'id_asc') {
                filtered.sort((a, b) => a.id - b.id);
            } else if (sortMode === 'name_asc') {
                filtered.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            }

            $('#displayedStreamCount').text(filtered.length);

            if (filtered.length === 0) {
                $('#streamGridContainer').html(`
                    <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <div style="font-size: 3rem; margin-bottom: 12px;">🔍</div>
                        <h3 style="color: #fff; margin-bottom: 6px;">Không tìm thấy bàn live phù hợp</h3>
                        <p style="font-size: 0.88rem;">Vui lòng thử từ khóa tìm kiếm hoặc danh mục khác.</p>
                    </div>
                `);
                return;
            }

            const phaseClass = currentCycleState.phase === 'betting' ? 'phase-betting' :
                               (currentCycleState.phase === 'shaking' ? 'phase-shaking' : 'phase-result');
            const phaseLabel = currentCycleState.phase === 'betting' ? '⏳ Ra Chiêu' :
                               (currentCycleState.phase === 'shaking' ? '🎲 Đang Lắc' : '🏆 Kết Quả');

            const html = filtered.map(t => {
                const tableIdStr = '#' + (t.id < 10 ? '0' : '') + t.id;
                const cardColor = t.color || '#6366f1';

                return `
                    <a href="watch.php?id=${t.id}" class="stream-card">
                        <div class="card-visual">
                            <div class="card-bg-glow" style="background: ${cardColor};"></div>
                            <div class="card-badge-live">
                                <i class="fa fa-circle" style="font-size: 0.4rem;"></i> LIVE
                            </div>
                            <div class="card-badge-table-id">${tableIdStr}</div>
                            <div class="card-visual-icon">${t.icon || '🎮'}</div>
                            <div class="card-badge-viewers">
                                <i class="fa fa-user"></i> ${(t.viewers || 180)}
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="card-info-header">
                                <img src="${t.streamer_avatar || '../img/avatar_default.png'}" alt="${t.streamer_name}" class="card-streamer-avatar" onerror="this.src='../img/avatar_default.png'">
                                <div class="card-title-group">
                                    <div class="card-title" title="${t.name}">${t.name}</div>
                                    <div class="card-streamer-name">Streamer: <b>${t.streamer_name}</b></div>
                                </div>
                            </div>

                            <div class="card-status-bar">
                                <span class="card-phase-tag ${phaseClass}">
                                    <i class="fa fa-bolt"></i> ${phaseLabel}
                                </span>
                                <span class="card-cta-btn">
                                    VÀO XEM <i class="fa fa-arrow-right"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                `;
            }).join('');

            $('#streamGridContainer').html(html);
        }

        // Render Top Channels in Sidebar
        function renderTopChannels() {
            const sorted = [...allTables].sort((a, b) => (b.viewers || 0) - (a.viewers || 0)).slice(0, 15);
            const html = sorted.map((t, idx) => `
                <a href="watch.php?id=${t.id}" class="top-channel-row">
                    <span class="top-channel-rank">#${idx + 1}</span>
                    <span class="top-channel-icon">${t.icon || '🎮'}</span>
                    <span class="top-channel-name">${t.name}</span>
                    <span class="top-channel-viewers"><i class="fa fa-user"></i> ${t.viewers || 0}</span>
                </a>
            `).join('');
            $('#tabContentTop').html(html);
        }

        // Handle Search Input
        function handleSearch() {
            searchQuery = $('#globalSearchInput').val().trim();
            if (searchQuery) {
                $('#searchMatchCount').show().text('ĐANG LỌC...');
            } else {
                $('#searchMatchCount').hide();
            }
            renderGrid();
        }

        // Handle Category Filter
        function setCategoryFilter(cat, btn) {
            activeCategory = cat;
            $('.cat-chip').removeClass('active');
            $(btn).addClass('active');
            renderGrid();
        }

        // Handle Sidebar Tab Switching
        function switchSidebarTab(tabName, btn) {
            $('.sidebar-tab-btn').removeClass('active');
            $(btn).addClass('active');

            $('#tabContentChat, #tabContentTop').hide();
            if (tabName === 'chat') {
                $('#tabContentChat').show();
            } else if (tabName === 'top') {
                $('#tabContentTop').show();
            }
        }

        // Chat Feature
        function handleSendChat(e) {
            e.preventDefault();
            const input = $('#chatInputField');
            const msg = input.val().trim();
            if (!msg) return;

            appendChatMessage('<?= htmlspecialchars($userName) ?>', msg, 'author-user');
            input.val('');

            // Send to server
            $.post('api_spectator.php', {
                action: 'send_chat',
                table_id: currentSpotlightId,
                message: msg
            });
        }

        function quickSendReaction(emoji) {
            appendChatMessage('<?= htmlspecialchars($userName) ?>', 'Đã thả cảm xúc ' + emoji, 'author-vip');
            $.post('api_spectator.php', {
                action: 'send_reaction',
                table_id: currentSpotlightId,
                emoji: emoji
            });
        }

        function appendChatMessage(author, text, authorClass = 'author-user') {
            const bubble = `
                <div class="chat-bubble">
                    <span class="chat-author ${authorClass}">${author}:</span>
                    <span class="chat-text">${text}</span>
                </div>
            `;
            const box = $('#chatMessagesBox');
            box.append(bubble);
            box.scrollTop(box[0].scrollHeight);
        }

        // Dynamic 3D particle background settings
        (function () {
            window.themeConfig = {
                particleCount: 280,
                particleSize: 0.04,
                particleColor: "#8b5cf6",
                particleOpacity: 0.35,
                shapeCount: 12,
                shapeColors: ["#6366f1", "#8b5cf6", "#06b6d4"],
                shapeOpacity: 0.12,
                bgGradient: ["#070a13", "#0e1322", "#131a30"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();

        // Polling & Initialization
        $(document).ready(() => {
            fetchLiveTables();
            // Polling every 1 second to sync timer and table viewers
            setInterval(fetchLiveTables, 1000);
        });
    </script>
</body>
</html>
