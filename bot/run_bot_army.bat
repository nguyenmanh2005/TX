@echo off
title 🤖 Bot Army Controller - gtlmanh.id.vn
color 0a

:loop
cls
echo ======================================================
echo           🤖 QUÂN ĐOÀN BOT ĐANG XUẤT KÍCH 🤖
echo ======================================================
echo  Thoi gian: %time%
echo  Trang thai: Dang hoat dong (1-10 bots moi dot)
echo ======================================================
echo.
echo  [SYSTEM]: Dang goi Bot Engine tai localhost...

:: Dung curl de goi file PHP (Windows 10 tro len da co san curl)
curl -s http://localhost/1/bot/bot_engine.php > nul

echo.
echo  [DONE]: Chu ky hoan tat. 
echo  [WAIT]: Dang cho 30 giay de tiep tuc...
echo.
echo  Nhan Ctrl+C de dung Bot.

:: Cho 30 giay
timeout /t 60 > nul

goto loop
