#!/bin/bash

MYSQLD_BIN="${MYSQLD_BIN:-$(command -v mysqld || command -v mariadbd || true)}"
MYSQL_CLIENT_BIN="${MYSQL_CLIENT_BIN:-$(command -v mysql || command -v mariadb || true)}"
MYSQL_INSTALL_BIN="${MYSQL_INSTALL_BIN:-$(command -v mysql_install_db || command -v mariadb-install-db || true)}"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
MYSQL_SOCK=/home/runner/mysql-run/mysql.sock
MYSQL_DATA=/home/runner/mysql-data
MYSQL_RUN=/home/runner/mysql-run
MYSQL_LOG=/tmp/mysql.log

if [ -z "$MYSQLD_BIN" ] || [ -z "$MYSQL_CLIENT_BIN" ] || [ -z "$MYSQL_INSTALL_BIN" ]; then
  echo "[start] ERROR: MariaDB binaries are not available on PATH."
  echo "[start] Required: mysqld or mariadbd, mysql or mariadb, and mysql_install_db or mariadb-install-db."
  exit 1
fi

if [ -z "$PHP_BIN" ]; then
  echo "[start] ERROR: PHP is not available on PATH."
  exit 1
fi

mkdir -p "$MYSQL_RUN" "$MYSQL_DATA"

# ── Remove stale socket from previous run ────────────────────────────────────
rm -f "$MYSQL_SOCK"

# ── Init data dir if needed ───────────────────────────────────────────────────
if [ ! -f "$MYSQL_DATA/mysql/global_priv.MYI" ] && \
   [ ! -f "$MYSQL_DATA/mysql/user.MYI" ]; then
  echo "[start] Running mysql_install_db..."
  "$MYSQL_INSTALL_BIN" \
    --datadir="$MYSQL_DATA" \
    --user=runner \
    --auth-root-authentication-method=normal \
    2>>"$MYSQL_LOG" || true
fi

# ── Start MariaDB in background ───────────────────────────────────────────────
echo "[start] Starting MariaDB..."
"$MYSQLD_BIN" \
  --socket="$MYSQL_SOCK" \
  --datadir="$MYSQL_DATA" \
  --pid-file="$MYSQL_RUN/mysql.pid" \
  --skip-networking \
  --log-error="$MYSQL_LOG" \
  2>>"$MYSQL_LOG" &

# Wait for socket AND for connections to be accepted
for i in $(seq 1 30); do
  if "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root \
       -e "SELECT 1;" >/dev/null 2>&1; then
    echo "[start] MariaDB ready."
    break
  fi
  echo "[start] Waiting for MariaDB ($i/30)..."
  sleep 1
done

if ! "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root \
     -e "SELECT 1;" >/dev/null 2>&1; then
  echo "[start] ERROR: MariaDB not accepting connections. Last log:"
  tail -20 "$MYSQL_LOG" 2>/dev/null
  exit 1
fi

# ── Bootstrap DB & schema ─────────────────────────────────────────────────────
if ! "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root \
     -e "USE shifaa_dizad; SELECT 1 FROM users LIMIT 1;" 2>/dev/null; then
  echo "[start] Creating DB and applying schema..."
  "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root \
    -e "CREATE DATABASE IF NOT EXISTS shifaa_dizad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root shifaa_dizad \
    < backend/database/schema.sql 2>/dev/null || true
  "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root shifaa_dizad \
    < backend/database/add_medical_services.sql 2>/dev/null || true
  "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root shifaa_dizad \
    < backend/database/schema_extension.sql 2>/dev/null || true
  "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root shifaa_dizad \
    < backend/database/seed.sql 2>/dev/null || true
  "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root shifaa_dizad \
    < backend/database/seed_extra.sql 2>/dev/null || true
  echo "[start] Schema applied."
fi

# ── Seed inventory & expanded data if empty ──────────────────────────────────
INV_COUNT=$("$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root shifaa_dizad \
  -sN -e "SELECT COUNT(*) FROM inventory;" 2>/dev/null || echo "0")
if [ "$INV_COUNT" -lt 100 ] 2>/dev/null; then
  echo "[start] Seeding inventory and expanded data..."
  "$MYSQL_CLIENT_BIN" --socket="$MYSQL_SOCK" -u root shifaa_dizad \
    < backend/database/seed_big.sql 2>/dev/null || true
  echo "[start] Big seed applied."
fi

# ── Schema patches & full demo seed ──────────────────────────────────────────
echo "[start] Applying schema patches and demo seed..."
"$PHP_BIN" backend/scripts/patch_and_seed.php 2>&1 | tail -20 || true

# ── Start PHP server ──────────────────────────────────────────────────────────
echo "[start] PHP built-in server on 0.0.0.0:5000..."
exec "$PHP_BIN" -S 0.0.0.0:5000 -t frontend/ router.php
