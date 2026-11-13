@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

REM ============================================================================
REM   Gestion Our Stock - Migration COMPLETE des donnees legacy
REM
REM   Ce que fait ce script :
REM     1. sauvegarde la base unifiee actuelle
REM     2. VIDE completement la base unifiee (install --fresh = DROP + recreate)
REM     3. reimporte les 3 anciennes bases (Stock + Credits + Cheques)
REM     4. (optionnel) importe l'ancien POS gestionmystock
REM     5. reconstruit le grand livre des mouvements
REM     6. lance le diagnostic
REM
REM   Les anciennes bases (suivi_stock / gestion_credits / gestion_cheques)
REM   ne sont JAMAIS modifiees : le script ne fait que les LIRE.
REM ============================================================================

REM ---- Configuration : change ici si tes bases ont d'autres noms --------------
set LEGACY_STOCK_DB=suivi_stock
set LEGACY_CREDITS_DB=gestion_credits
set LEGACY_CHEQUES_DB=gestion_cheques

REM ---- Ancien POS gestionmystock : mets 1 pour l'importer, 0 pour l'ignorer ---
REM ATTENTION : les VENTES / PAIEMENTS / CLIENTS reels viennent de ce POS.
REM Mets 0 uniquement si tu ne veux PAS de l'historique des ventes.
set IMPORT_POS=1
set POS_DB=gestionmystock

echo ============================================================
echo   Gestion Our Stock - Migration complete des donnees
echo ============================================================
echo.
echo   Base cible (sera VIDEE puis remplie) : gestion_our_stock
echo.
echo   Source Stock   : %LEGACY_STOCK_DB%
echo   Source Credits : %LEGACY_CREDITS_DB%
echo   Source Cheques : %LEGACY_CHEQUES_DB%
if "%IMPORT_POS%"=="1" echo   Source POS     : %POS_DB%
echo.
echo   ATTENTION : la base unifiee sera entierement videe avant l'import.
echo   Les anciennes bases ne sont jamais modifiees.
echo.
set /p CONFIRM="Tape OUI puis Entree pour continuer : "
if /I not "%CONFIRM%"=="OUI" (
    echo.
    echo Operation annulee.
    pause
    exit /b 1
)

echo.
echo [1/6] Sauvegarde de la base actuelle...
php database\backup.php
if errorlevel 1 goto :err

echo.
echo [2/6] Recreation d'une base unifiee VIDE (install --fresh)...
php database\install.php --fresh
if errorlevel 1 goto :err

echo.
echo [3/6] Migration des 3 anciennes bases (migrate_legacy --force)...
php database\migrate_legacy.php --force
if errorlevel 1 goto :err

if "%IMPORT_POS%"=="1" (
    echo.
    echo [3b] Import de l'ancien POS %POS_DB%...
    php database\import_gestionmystock.php --db=%POS_DB%
    if errorlevel 1 goto :err
)

echo.
echo [4/6] Reconstruction du grand livre des mouvements...
php database\backfill_stock_movements.php
if errorlevel 1 goto :err

echo.
echo [5/6] Diagnostic final...
php database\doctor.php

echo.
echo [6/6] TERMINE !
echo.
echo   Compte admin : admin / Admin@2026
echo   URL locale   : http://127.0.0.1:8099
echo.
pause
exit /b 0

:err
echo.
echo *** ERREUR a l'etape precedente - arret du script. ***
echo Verifie le message ci-dessus puis relance.
echo.
pause
exit /b 1
