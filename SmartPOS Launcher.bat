@echo off
setlocal

set "XAMPP=C:\xampp"

REM ========================================
REM Start Apache directly
REM ========================================

echo Starting Apache...

start "" /B "%XAMPP%\apache\bin\httpd.exe"

REM ========================================
REM Start MySQL directly
REM ========================================

echo Starting MySQL...

start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini" --standalone

REM ========================================
REM Wait for Apache
REM ========================================

:WAIT_SERVER

powershell -NoProfile -Command ^
"$tcp = New-Object System.Net.Sockets.TcpClient; try { $tcp.Connect('127.0.0.1',80); $tcp.Close(); exit 0 } catch { exit 1 }"

if errorlevel 1 (
    timeout /t 2 /nobreak >nul
    goto WAIT_SERVER
)

REM ========================================
REM Open SmartPOS
REM ========================================

start "" "http://localhost/smart-pos/"

exit /b