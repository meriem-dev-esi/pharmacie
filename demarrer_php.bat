@echo off
title Pharmacie DZ - Serveur PHP
echo ====================================================
echo  Pharmacie DZ - Demarrage du serveur PHP local
echo  Port : http://localhost:8000
echo ====================================================

if exist "C:\xampp\php\php.exe" (
    echo Utilisation du binaire PHP de XAMPP...
    start http://localhost:8000
    "C:\xampp\php\php.exe" -S localhost:8000
) else (
    echo PHP non trouve dans C:\xampp\php. Essai de la commande php standard...
    start http://localhost:8000
    php -S localhost:8000
)
pause
