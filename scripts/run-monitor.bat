@echo off
REM Run website checks (schedule every 1 minute in Task Scheduler)
"C:\xampp\php\php.exe" "%~dp0..\cli\monitor.php"
