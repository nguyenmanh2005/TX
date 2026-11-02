@echo off
chcp 65001 > nul
title ⚡ GTLM Realtime WebSocket Gateway (Port 8088)
color 0A
echo ===================================================================
echo   GTLM GAMING PLATFORM - REALTIME WEBSOCKET GATEWAY V1.0
echo   Cong nghe truyen tin thoi gian thuc 0ms (RFC 6455)
echo   Ho tro: Chat The Gioi, PvP Co Ca-ro, LiveStream 61 Ban
echo ===================================================================
echo.
echo Dang khoi dong may chu WebSocket tai cong 8088...
C:\xampp\php\php.exe realtime\websocket_server.php
pause
