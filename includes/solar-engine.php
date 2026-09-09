-- Add max_pv_input_w columns
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solar_listings' AND COLUMN_NAME='max_pv_input_w');
SET @stmt := IF(@col=0,'ALTER TABLE solar_listings ADD COLUMN max_pv_input_w INT NULL COMMENT ''Max solar panel input in Watts'' AFTER battery_kwh','SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solar_calculator_products' AND COLUMN_NAME='max_pv_input_w');
SET @stmt := IF(@col=0,'ALTER TABLE solar_calculator_products ADD COLUMN max_pv_input_w INT NULL COMMENT ''Max solar panel input in Watts'' AFTER usable_battery_kwh','SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- Load REAL label specs (Strict Mode requires max_pv_input_w > 0)
UPDATE solar_listings SET inverter_kva=0.30, battery_kwh=0.659, max_pv_input_w=300 WHERE title LIKE '%KINAS VOLT G3W (659Wh)%';
UPDATE solar_listings SET inverter_kva=0.50, battery_kwh=1.004, max_pv_input_w=300 WHERE title LIKE '%KINAS VOLT G5W (1kWh)%';
UPDATE solar_listings SET inverter_kva=1.00, battery_kwh=2.009, max_pv_input_w=550 WHERE title LIKE '%KINAS VOLT POWER STATION G10W (2kWh)%';
UPDATE solar_listings SET inverter_kva=3.60, battery_kwh=4.249, max_pv_input_w=5000 WHERE title LIKE '%KINAS VOLT POWER STATION NLB-3.6%';

-- Mirror into calculator products (usable = nominal x 0.90 DoD x 0.95 eff)
UPDATE solar_calculator_products p JOIN solar_listings l ON l.id=p.listing_id
SET p.product_type='generator', p.continuous_kw=0.30, p.battery_capacity_kwh=0.659, p.usable_battery_kwh=0.56, p.max_pv_input_w=300, p.active=1 WHERE l.title LIKE '%KINAS VOLT G3W (659Wh)%';
UPDATE solar_calculator_products p JOIN solar_listings l ON l.id=p.listing_id
SET p.product_type='generator', p.continuous_kw=0.50, p.battery_capacity_kwh=1.004, p.usable_battery_kwh=0.86, p.max_pv_input_w=300, p.active=1 WHERE l.title LIKE '%KINAS VOLT G5W (1kWh)%';
UPDATE solar_calculator_products p JOIN solar_listings l ON l.id=p.listing_id
SET p.product_type='generator', p.continuous_kw=1.00, p.battery_capacity_kwh=2.009, p.usable_battery_kwh=1.72, p.max_pv_input_w=550, p.active=1 WHERE l.title LIKE '%KINAS VOLT POWER STATION G10W (2kWh)%';
UPDATE solar_calculator_products p JOIN solar_listings l ON l.id=p.listing_id
SET p.product_type='generator', p.continuous_kw=3.60, p.battery_capacity_kwh=4.249, p.usable_battery_kwh=3.63, p.max_pv_input_w=5000, p.active=1 WHERE l.title LIKE '%KINAS VOLT POWER STATION NLB-3.6%';
