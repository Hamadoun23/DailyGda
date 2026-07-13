#!/bin/bash
# Déploiement DailyGDA — à lancer depuis ~/public_html/daily sur le serveur (cPanel Terminal).
# Usage : bash deploy.sh
set -euo pipefail

PHP=/opt/alt/php83/usr/bin/php
LOG="deploy-$(date +%Y%m%d-%H%M%S).log"

echo "== Déploiement DailyGDA — $(date) ==" | tee -a "$LOG"

# Toujours remettre le site en ligne à la fin, même si une étape échoue en cours de route.
trap '"$PHP" artisan up >> "$LOG" 2>&1 || true; echo "== Site remis en ligne (artisan up) — vérifie le log si une étape a échoué : $LOG =="' EXIT

echo "-- 1. Maintenance --" | tee -a "$LOG"
"$PHP" artisan down --render="errors::503" >> "$LOG" 2>&1 || "$PHP" artisan down >> "$LOG" 2>&1

echo "-- 2. Backup .env --" | tee -a "$LOG"
cp .env ".env.backup.$(date +%Y%m%d-%H%M%S)"

echo "-- 3. État git --" | tee -a "$LOG"
git status --porcelain | tee -a "$LOG"
STASHED=0
if [ -n "$(git status --porcelain)" ]; then
    echo "-- Modifications locales détectées, mise de côté (stash) --" | tee -a "$LOG"
    git stash push -m "deploy-auto-$(date +%Y%m%d-%H%M%S)" | tee -a "$LOG"
    STASHED=1
fi

echo "-- 4. git pull origin main --" | tee -a "$LOG"
git pull origin main 2>&1 | tee -a "$LOG"

if [ "$STASHED" -eq 1 ]; then
    echo "-- 5. Restauration des modifs locales (stash pop) --" | tee -a "$LOG"
    if ! git stash pop 2>&1 | tee -a "$LOG"; then
        echo "!! CONFLIT au stash pop — résous-le manuellement (git status / git diff), puis relance artisan up toi-même si besoin. !!" | tee -a "$LOG"
        exit 1
    fi
fi

echo "-- 6. composer install --" | tee -a "$LOG"
"$PHP" composer.phar install --no-dev --optimize-autoloader 2>&1 | tee -a "$LOG"

echo "-- 7. Migrations --" | tee -a "$LOG"
"$PHP" artisan migrate --force 2>&1 | tee -a "$LOG"

echo "-- 8. Nettoyage cache --" | tee -a "$LOG"
"$PHP" artisan config:clear >> "$LOG" 2>&1
"$PHP" artisan cache:clear >> "$LOG" 2>&1
"$PHP" artisan route:clear >> "$LOG" 2>&1
"$PHP" artisan view:clear >> "$LOG" 2>&1
"$PHP" artisan config:cache >> "$LOG" 2>&1

echo "== Terminé avec succès — le site va être remis en ligne (trap EXIT) ==" | tee -a "$LOG"
