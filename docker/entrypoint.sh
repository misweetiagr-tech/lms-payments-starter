#!/bin/sh
# Prepares the app, then runs whatever command the container was given.
# SKIP_SETUP=1 lets a second container (the scheduler) share the database without racing the first.
# DB_DATABASE=:memory: (used for test runs) skips database setup so no real database is touched.
set -e

if [ -z "$SKIP_SETUP" ]; then
  [ -f .env ] || cp .env.example .env
  grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

  DB="${DB_DATABASE:-database/database.sqlite}"

  if [ "$DB" != ":memory:" ]; then
    FRESH=""
    if [ ! -f "$DB" ]; then
      mkdir -p "$(dirname "$DB")"
      touch "$DB"
      FRESH=1
    fi

    php artisan migrate --force

    # Demo data only on the very first start, so restarts never create duplicates.
    if [ -n "$FRESH" ]; then
      php artisan db:seed --force
    fi
  fi
fi

exec "$@"
