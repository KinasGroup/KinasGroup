-- =====================================================================
-- 2026_08_14_solar_hardware_partitioning.sql
-- Adds hardware partitioning to solar_listings so each hardware type
-- stores its capacity in the CORRECT unit (required by the Solar
-- Calculator):
--   solar_panel   -> panel_watts  (W)
--   inverter      -> inverter_kva (kW/kVA)
--   battery       -> battery_kwh  (kWh)
--   power_station -> inverter_kva + battery_kwh
-- Legacy capacity_kw is kept (create/update keep it in sync) so older
-- readers/calculator fallbacks keep working.
-- Safe to re-run: every ALTER is guarded by an INFORMATION_SCHEMA check.
-- =====================================================================

-- hardware_type -------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'solar_listings' AND COLUMN_NAME = 'hardware_type');
SET @stmt := IF(@col = 0,
  'ALTER TABLE solar_listings ADD COLUMN hardware_type ENUM(''solar_panel'',''inverter'',''battery'',''power_station'') NOT NULL DEFAULT ''solar_panel'' AFTER service_type',
  'SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- panel_watts (W) -----------------------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'solar_listings' AND COLUMN_NAME = 'panel_watts');
SET @stmt := IF(@col = 0,
  'ALTER TABLE solar_listings ADD COLUMN panel_watts INT NULL COMMENT ''Solar panel output in Watts'' AFTER hardware_type',
  'SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- inverter_kva (kW/kVA) ----------------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'solar_listings' AND COLUMN_NAME = 'inverter_kva');
SET @stmt := IF(@col = 0,
  'ALTER TABLE solar_listings ADD COLUMN inverter_kva DECIMAL(10,2) NULL COMMENT ''Inverter rating in kW/kVA'' AFTER panel_watts',
  'SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- battery_kwh (kWh) ---------------------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'solar_listings' AND COLUMN_NAME = 'battery_kwh');
SET @stmt := IF(@col = 0,
  'ALTER TABLE solar_listings ADD COLUMN battery_kwh DECIMAL(10,2) NULL COMMENT ''Battery capacity in kWh'' AFTER inverter_kva',
  'SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
