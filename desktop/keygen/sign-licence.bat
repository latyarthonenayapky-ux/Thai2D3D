@echo off
rem Vendor licence signer for Winning Yields Always (WYA).
rem Uses the bundled PHP runtime (which already enables sodium) so nothing has
rem to be installed. Falls back to a system php if the runtime is not there.

setlocal
set "PHP=%~dp0..\runtime\php-win\php.exe"
if not exist "%PHP%" set "PHP=php"
"%PHP%" "%~dp0sign-licence.php" %*
endlocal
