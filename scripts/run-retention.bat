@echo off
REM Purge monitor logs older than retention period (schedule daily)
"C:\xampp\php\php.exe" "%~dp0..\cli\retention.php"
