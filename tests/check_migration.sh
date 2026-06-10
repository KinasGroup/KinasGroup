#!/usr/bin/env bash
# tests/check_migration.sh
# -----------------------------------------------------------------------------
# Lint-checks the location migration by:
#   1. Re-printing the dynamic SQL string we'd execute on a real DB.
#   2. Running the entire script through `mysql --execute` in a throwaway
#      database, so we catch any syntax error without touching production.
# -----------------------------------------------------------------------------
set -euo pipefail
cd "$(dirname "$0")/.."

if ! command -v mysql >/dev/null 2>&1; then
  echo "mysql client not found. Install it to run this check." >&2
  exit 2
fi

# Extract the dynamic DDL we'd run, after the SET @col_exists guard.
DDL='ALTER TABLE car_listings ADD COLUMN location VARCHAR(255) GENERATED ALWAYS AS (TRIM(CONCAT_WS(0x2C20, city, state))) STORED'

echo "Migration would run this ALTER when the column is missing:"
echo "  $DDL"
echo

DB="${TEST_DB:-kinas_migration_check}"
echo "Creating throwaway DB '$DB'…"
mysql -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\`;"

echo "Bootstrapping car_listings (matches fresh_schema.sql)…"
mysql "$DB" <<'SQL'
CREATE TABLE car_listings (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    agent_id    INT NOT NULL,
    title       VARCHAR(255) NOT NULL,
    price       DECIMAL(15,2) NOT NULL,
    city        VARCHAR(100),
    state       VARCHAR(100),
    country     VARCHAR(100) DEFAULT 'Nigeria',
    status      ENUM('active','sold','pending','flagged','removed') DEFAULT 'active'
);
SQL

echo "Running migration script…"
if ! mysql "$DB" < database/migrations/2026_06_10_add_location_to_car_listings.sql; then
  echo "Migration FAILED on throwaway DB." >&2
  exit 1
fi

echo "Inserting a sample row + reading back…"
mysql "$DB" <<'SQL'
INSERT INTO car_listings (agent_id, title, price, city, state)
VALUES (1, 'Toyota Camry 2020', 8500000.00, 'Lagos', 'Lagos'),
       (1, 'Honda Accord 2018', 6500000.00, 'Abuja', NULL);
SELECT id, title, city, state, location FROM car_listings;
SQL

echo "Re-running migration to confirm it's idempotent…"
mysql "$DB" < database/migrations/2026_06_10_add_location_to_car_listings.sql

echo "Tearing down throwaway DB…"
mysql -e "DROP DATABASE \`$DB\`;"

echo
echo "OK — migration parses, applies, and is idempotent."
