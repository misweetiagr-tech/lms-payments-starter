#!/bin/sh
# Prepares the app, then runs whatever command the container was given.
#   SKIP_SETUP=1        skip migrate/seed (the scheduler shares the database the app container prepared)
#   DB_DATABASE=:memory: (used for test runs) also skips migrate/seed so no real database is touched
set -e

[ -f .env ] || cp .env.example .env

# `php artisan serve` starts the web server with a filtered environment, so container variables never
# reach it. Writing them into .env makes the web process, the scheduler and artisan all agree.
persist() {
  val=$(printenv "$1" || true)
  [ -n "$val" ] || return 0
  if grep -q "^$1=" .env; then
    sed -i "s|^$1=.*|$1=$val|" .env
  else
    echo "$1=$val" >> .env
  fi
}
for name in DB_DATABASE NODE_JWT_SECRET PAYMENTS_WEBHOOK_SECRET; do
  persist "$name"
done

grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

DB="${DB_DATABASE:-database/database.sqlite}"

if [ -z "$SKIP_SETUP" ] && [ "$DB" != ":memory:" ]; then
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

exec "$@"
