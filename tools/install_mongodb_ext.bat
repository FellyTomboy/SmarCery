@echo off
REM ============================================================
REM install_mongodb_ext.bat
REM Otomatis install php_mongodb.dll untuk XAMPP
REM Usage:  install_mongodb_ext.bat
REM ============================================================

setlocal enabledelayedexpansion

REM Locate PHP from XAMPP
set PHP_EXE=C:\xampp\php\php.exe
set PHP_DIR=C:\xampp\php
set EXT_DIR=C:\xampp\php\ext
set PHP_INI=C:\xampp\php\php.ini

if not exist "%PHP_EXE%" (
    echo [ERROR] PHP tidak ditemukan di %PHP_EXE%
    echo Pastikan XAMPP terinstall di C:\xampp
    pause
    exit /b 1
)

REM Detect PHP version (e.g. 8.1.10)
echo [1/5] Mendeteksi versi PHP...
for /f "tokens=2 delims==" %%v in ('"%PHP_EXE%" -r "echo PHP_VERSION;"') do set PHP_VERSION=%%v
for /f "tokens=1,2 delims=." %%a in ("%PHP_VERSION%") do (
    set PHP_MAJOR=%%a
    set PHP_MINOR=%%b
)
echo       PHP %PHP_VERSION% (major=%PHP_MAJOR%, minor=%PHP_MINOR%)

REM Detect architecture (x86 vs x64)
echo [2/5] Mendeteksi bitness...
"%PHP_EXE%" -i > "%TEMP%\phpinfo_tmp.txt"
findstr /C:"x86" /C:"x64" "%TEMP%\phpinfo_tmp.txt" | findstr /C:"Architecture" > "%TEMP%\arch.txt"
for /f "tokens=*" %%a in ('type "%TEMP%\arch.txt"') do set ARCH_LINE=%%a
echo %ARCH_LINE% | findstr /C:"x64" >nul && (set ARCH=x64) || (set ARCH=x86)
echo       Arsitektur=%ARCH%

REM Detect Thread Safety
findstr /C:"Thread Safety" "%TEMP%\phpinfo_tmp.txt" > "%TEMP%\ts.txt"
for /f "tokens=*" %%a in ('type "%TEMP%\ts.txt"') do set TS_LINE=%%a
echo %TS_LINE% | findstr /C:"enabled" >nul && (set TS=ts) || (set TS=nts)
echo       Thread Safety=%TS%

REM Map PHP version to ext-mongodb version
REM   PHP 8.4 -> 1.21+
REM   PHP 8.3 -> 1.21+
REM   PHP 8.2 -> 1.20+
REM   PHP 8.1 -> 1.16 to 1.21
REM   PHP 8.0 -> 1.15 to 1.21
if "%PHP_MAJOR%%PHP_MINOR%"=="84" set MDB_VER=1.21.0
if "%PHP_MAJOR%%PHP_MINOR%"=="83" set MDB_VER=1.21.0
if "%PHP_MAJOR%%PHP_MINOR%"=="82" set MDB_VER=1.20.1
if "%PHP_MAJOR%%PHP_MINOR%"=="81" set MDB_VER=1.16.2
if "%PHP_MAJOR%%PHP_MINOR%"=="80" set MDB_VER=1.15.1
if "%PHP_MAJOR%%PHP_MINOR%"=="74" set MDB_VER=1.16.2

if not defined MDB_VER (
    echo [ERROR] PHP %PHP_VERSION% tidak disupport otomatis.
    echo Cek manual di https://pecl.php.net/package/mongodb
    pause
    exit /b 1
)
echo [3/5] ext-mongodb %MDB_VER% akan diinstall...

REM Detect Visual C++ runtime version
findstr /C:"PHP Extension Build" "%TEMP%\phpinfo_tmp.txt" > "%TEMP%\vc.txt"
for /f "tokens=*" %%a in ('type "%TEMP%\vc.txt"') do set VC_LINE=%%a
echo %VC_LINE% | findstr /C:"VC15" >nul && set VC=vc15
echo %VC_LINE% | findstr /C:"VC16" >nul && set VC=vc16
echo %VC_LINE% | findstr /C:"VS17" >nul && set VC=vs17
if not defined VC set VC=vc15
echo       VC runtime=%VC%

REM Construct DLL name
set DLL_NAME=php_mongodb-%MDB_VER%-%PHP_MAJOR%.%PHP_MINOR%-%TS%-%VC%-%ARCH%.zip
set DLL_URL=https://windows.php.net/downloads/pecl/releases/mongodb/%MDB_VER%/%DLL_NAME%
set ZIP_PATH=%TEMP%\%DLL_NAME%

echo [4/5] Downloading %DLL_NAME%...
echo       URL: %DLL_URL%
curl -L -o "%ZIP_PATH%" "%DLL_URL%" >nul 2>&1
if not exist "%ZIP_PATH%" (
    echo [ERROR] Gagal download. Cek koneksi internet atau download manual di:
    echo   %DLL_URL%
    pause
    exit /b 1
)

REM Extract DLL
echo [5/5] Extracting dan menginstall DLL...
powershell -NoProfile -Command "Expand-Archive -Path '%ZIP_PATH%' -DestinationPath '%TEMP%\mongodb_extract' -Force" >nul 2>&1

REM Find the DLL inside extracted folder (could be in subfolder)
for /r "%TEMP%\mongodb_extract" %%f in (php_mongodb.dll) do (
    copy /Y "%%f" "%EXT_DIR%\php_mongodb.dll" >nul
    echo       DLL copied ke: %EXT_DIR%\php_mongodb.dll
    goto :dll_installed
)

:dll_installed

REM Enable extension in php.ini
echo       Updating php.ini...
findstr /C:"extension=php_mongodb" "%PHP_INI%" >nul 2>&1
if errorlevel 1 (
    REM Add the extension line at the end of the file
    echo. >> "%PHP_INI%"
    echo ; Added by SmarCery install_mongodb_ext.bat >> "%PHP_INI%"
    echo extension=php_mongodb.dll >> "%PHP_INI%"
    echo       Extension line ditambahkan.
) else (
    echo       Extension sudah aktif.
)

REM Cleanup temp files
del /Q "%TEMP%\phpinfo_tmp.txt" "%TEMP%\arch.txt" "%TEMP%\ts.txt" "%TEMP%\vc.txt" 2>nul
rmdir /S /Q "%TEMP%\mongodb_extract" 2>nul

echo.
echo ============================================================
echo SELESAI!
echo ============================================================
echo.
echo Langkah selanjutnya:
echo   1. Buka XAMPP Control Panel
echo   2. Klik STOP di Apache, lalu START lagi
echo   3. Jalankan:  C:\xampp\php\php.exe -m ^| findstr mongodb
echo      Harus muncul "mongodb"
echo.
echo Kalau ada masalah, cek manual di:
echo   https://windows.php.net/downloads/pecl/releases/mongodb/
echo ============================================================
echo.
pause
endlocal