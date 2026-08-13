-- Adds hardware partitioning columns to solar_listings so each hardware
-- type stores its capacity in the correct unit for the Solar Calculator.
--   solar_panel   -> panel_watts  (W)
--   inverter      -> inverter_kva (kW/kVA)
--   battery       -> battery_kwh  (kWh)
--   power_station -> inverter_kva + battery_kwh
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solar_listings' AND COLUMN_NAME='hardware_type');
SET @stmt := IF(@col=0,'ALTER TABLE solar_listings ADD COLUMN hardware_type VARCHAR(32) NOT NULL DEFAULT ''solar_panel'' AFTER service_type','SELECT 1');
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
