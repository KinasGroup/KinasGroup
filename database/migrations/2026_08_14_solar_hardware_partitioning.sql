-- =====================================================================
-- 2026_08_14_solar_hardware_partitioning.sql
-- KINAS VOLT hardware partitioning: store capacity in the correct unit
-- per hardware type so the Solar Calculator can read real specs.
--   hardware_type : solar_panel|inverter|battery|power_station|charge_controller|mounting_structure
--   panel_watts   : W      (solar_panel)
--   inverter_kva  : kW/kVA (inverter, power_station)
--   battery_kwh   : kWh    (battery, power_station)
-- capacity_kw retained as a legacy normalized-kW column for backward
-- compatibility with the old single-field rows and old calculator code.
-- Safe to re-run (INFORMATION_SCHEMA guards; MySQL has no IF NOT EXISTS).
-- =====================================================================

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solar_listings' AND COLUMN_NAME='hardware_type');
SET @stmt := IF(@col=0,'ALTER TABLE solar_listings ADD COLUMN hardware_type VARCHAR(32) NULL AFTER service_type','SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solar_listings' AND COLUMN_NAME='panel_watts');
SET @stmt := IF(@col=0,'ALTER TABLE solar_listings ADD COLUMN panel_watts DECIMAL(10,2) NULL AFTER hardware_type','SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solar_listings' AND COLUMN_NAME='inverter_kva');
SET @stmt := IF(@col=0,'ALTER TABLE solar_listings ADD COLUMN inverter_kva DECIMAL(10,2) NULL AFTER panel_watts','SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solar_listings' AND COLUMN_NAME='battery_kwh');
SET @stmt := IF(@col=0,'ALTER TABLE solar_listings ADD COLUMN battery_kwh DECIMAL(10,2) NULL AFTER inverter_kva','SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill: older rows stored the hardware type in service_type. Copy it
-- into hardware_type so existing inventory is readable by the calculator.
UPDATE solar_listings
SET hardware_type = service_type
WHERE (hardware_type IS NULL OR hardware_type = '')
  AND service_type IN ('solar_panel','inverter','battery','power_station','charge_controller','mounting_structure');
