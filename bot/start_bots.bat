@echo off
TITLE Bot Army Engine - Professional Launcher
COLOR 0A

:loop
cls
echo ======================================================
echo          🛡️ BOT ARMY ENGINE - STARTING CYCLE
echo ======================================================
echo Current Time: %date% %time%
echo Status: RUNNING...
echo ------------------------------------------------------

"C:\xampp\php\php.exe" bot_engine.php

echo ------------------------------------------------------
echo Cycle Complete. Waiting 50 seconds for next wave...
timeout /t 30 /nobreak > nul
goto loop
