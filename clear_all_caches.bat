@echo off
echo ============================================
echo CLEARING ALL PHP AND LARAVEL CACHES
echo ============================================
echo.

cd /d "%~dp0"

echo [1/5] Clearing Laravel caches...
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

echo.
echo [2/5] Stopping Apache (requires admin)...
net stop wampapache64

echo.
echo [3/5] Waiting 2 seconds...
timeout /t 2 /nobreak > nul

echo.
echo [4/5] Starting Apache...
net start wampapache64

echo.
echo [5/5] Done!
echo.
echo ============================================
echo All caches cleared and Apache restarted
echo Please refresh your browser now
echo ============================================
pause
