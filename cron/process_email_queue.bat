@echo off
REM GoPlanner - Process pending email queue (run daily via Windows Task Scheduler)
REM Usage: point Task Scheduler to this file (run whether user is logged on or not)
set PHP=C:\xampp\php\php.exe
set APP=C:\xampp\htdocs\goplanner 1

"%PHP%" "%APP%\api\mail\process_queue.php" >> "%APP%\storage\logs\email_queue.log" 2>&1
