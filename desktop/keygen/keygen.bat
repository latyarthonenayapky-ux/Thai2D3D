@echo off
setlocal
set "PHP=%~dp0..\runtime\php-win\php.exe"
if exist "%PHP%" goto :run
set "PHP=php"
where "%PHP%" >nul 2>nul
if errorlevel 1 (
    echo PHP runtime not found. Run this tool from the Thai2D3D project folder
    echo ^(expected desktop\runtime\php-win\php.exe^), or install PHP on PATH.
    pause
    exit /b 1
)
:run
"%PHP%" "%~dp0keygen.php" %*
if "%~1"=="" pause
endlocal
