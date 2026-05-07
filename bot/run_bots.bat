@echo off
title Bot Army Control Center
color 0A
cls

:loop
echo ======================================================
echo   [%time%] KICH HOAT BOT ARMY - DANG QUYET DINH...
echo ======================================================
echo.

:: Chạy engine bot
"C:\xampp\php\php.exe" "c:\xampp\htdocs\1\bot\bot_engine.php"

echo.
echo ======================================================
echo   [%time%] HOAN THANH CHU KY. 
echo   HE THONG SE NGHI 60 GIAY DE TRANH LAG MAY...
echo   (Nhan Ctrl+C hoac dong cua so nay de dung Bot)
echo ======================================================
echo.

timeout /t 60 /nobreak > nul
goto loop
