#!/bin/bash

set -e

DEV_BRANCH="dev"
PROD_BRANCH="production"

echo "========================================"
echo " Publication Git Septentrional"
echo "========================================"
echo

# Vérification de la branche
CURRENT_BRANCH=$(git branch --show-current)

if [ "$CURRENT_BRANCH" != "$DEV_BRANCH" ]; then
    echo "❌ Tu dois être sur la branche '$DEV_BRANCH'."
    echo "Branche actuelle : $CURRENT_BRANCH"
    exit 1
fi

# Vérifie qu'il y a des modifications
if git diff --quiet && git diff --cached --quiet; then
    echo "⚠️  Aucune modification détectée."
    exit 0
fi

echo "Ajout des fichiers..."
git add .

echo
read -rp "Message du commit : " MESSAGE

if [ -z "$MESSAGE" ]; then
    echo "Message vide."
    exit 1
fi

echo
echo "Commit sur DEV..."
git commit -m "$MESSAGE"

echo
echo "Push DEV..."
git push origin "$DEV_BRANCH"

echo
echo "Passage sur PRODUCTION..."
git checkout "$PROD_BRANCH"

git pull origin "$PROD_BRANCH"

echo
echo "Merge DEV -> PRODUCTION..."
git merge "$DEV_BRANCH" --no-ff

echo
echo "Push PRODUCTION..."
git push origin "$PROD_BRANCH"

echo
echo "Retour sur DEV..."
git checkout "$DEV_BRANCH"

echo
echo "========================================"
echo " Déploiement Git terminé"
echo "========================================"

echo "Branche DEV        : OK"
echo "Push DEV           : OK"
echo "Merge Production   : OK"
echo "Push Production    : OK"
echo "Retour sur DEV     : OK"