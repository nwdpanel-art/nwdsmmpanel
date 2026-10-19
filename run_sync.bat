@echo off
"C:\xampp\php\php.exe" "C:\xampp\htdocs\SMM_PRO_Legitimated_Panel\admin\auto_sync.php" > "C:\xampp\htdocs\SMM_PRO_Legitimated_Panel\sync_log.txt" 2>&1
exit /b %ERRORLEVEL%