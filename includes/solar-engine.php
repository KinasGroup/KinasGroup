<?php
/**
 * KINAS GROUP — Solar Calculation Engine
 *
 * Single source of truth for all solar calculator maths and product
 * matching. Used by:
 *   - api/solar/calculate.php
 *   - the calculator front-end (indirectly)
 *   - admin re-quote tools (future)
 *
 * Design rules (per the approved rebuild plan):
 *   1. Daily energy uses each appliance's REAL hours/day (no fixed 8h).
 *   2. PV size = design daily energy / (sun hours x performance ratio).
 *   3. Inverter size = peak connected load x safety factor.
 *   4. Battery size = (daily energy x backup fraction) / (DoD x efficiency).
 *   5. Prices ALWAYS come from live approved listings; reference values
 *      are used only as a clearly-labelled fallback and produce a warning.
 *   6. Everything rounds UP to the next available standard size.
 */

// ============================================================
// SCHEMA HELPERS
// ============================================================

if (!function_exists('kinas_solar_table_exists')) {
    function kinas_solar_table_exists($db, string $table): bool
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");
            $stmt->execute([$table]);
            $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}

// ============================================================
// SETTINGS
// ============================================================

if (!function_exists('kinas_solar_default_settings')) {
    function kinas_solar_default_settings(): array
    {
        return [
            'sun_hours_default'      => 5.0,      // peak sun hours (admin can set per-location later)
            'load_margin_pct'        => 10.0,     // design margin on daily energy
            'pv_performance_ratio'   => 0.80,     // combined PV losses (temp, dust, wiring, inverter)
            'battery_dod_pct'        => 90.0,     // LiFePO4 usable depth of discharge
            'battery_efficiency_pct' => 95.0,     // round-trip efficiency
            'inverter_safety_factor' => 1.25,     // peak load multiplier
            'default_panel_wattage'  => 550.0,    // reference panel size (W)
            'co2_kg_per_kwh'         => 0.85,     // grid CO2 intensity factor
            'default_panel_price'    => 450000.0, // fallback ONLY, labelled + warned
            'installation_cost'      => 1500000.0,
            'cabling_cost'           => 500000.0,
            'mounting_cost'          => 400000.0,
            'transport_cost'         => 200000.0,
            'electricity_tariff_ngn' => 225.0,    // admin-controlled tariff
        ];
    }
}

if (!function_exists('kinas_solar_get_settings')) {
    function kinas_solar_get_settings($db): array
    {
        $settings = kinas_solar_default_settings();

        if (!kinas_solar_table_exists($db, 'solar_calculator_settings')) {
            return $settings;
        }

        try {
            $rows = $db->query("SELECT setting_key, setting_value FROM solar_calculator_settings")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = (float)$row['setting_value'];
                }
            }
        } catch (Throwable $e) {
            // Keep defaults.
        }

        return $settings;
    }
}

// ============================================================
// PRODUCT CATALOGUE
// ============================================================

if (!function_exists('kinas_solar_get_products')) {
    /**
     * Returns calculator-enabled products with LIVE listing prices.
     * Empty array when the spec layer is not installed yet.
     */
    function kinas_solar_get_products($db): array
    {
        if (!kinas_solar_table_exists($db, 'solar_calculator_products')) {
            return [];
        }

        try {
            $stmt = $db->query("
                SELECT
                    p.id,
                    p.listing_id,
                    p.product_type,
                    p.panel_wattage_w,
                    p.inverter_capacity_kva,
                    p.continuous_kw,
                    p.battery_capacity_kwh,
                    p.usable_battery_kwh,
                    p.battery_voltage_v,
                    p.expandable,
                    p.priority,
                    l.title,
                    l.brand,
                    l.price
                FROM solar_calculator_products p
                JOIN solar_listings l ON l.id = p.listing_id
                WHERE p.active = 1
                  AND l.status = 'active'
                  AND l.price IS NOT NULL
                  AND l.price > 0
                ORDER BY p.priority ASC, l.price ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

// ============================================================
// CALCULATION
// ============================================================

if (!function_exists('kinas_solar_calculate')) {
    /**
     * @param array $input {
     *   appliances: [{name, quantity|qty, watts|watt, hours}],
     *   backup_hours: int
     * }
     * @return array full calculation result
     */
    function kinas_solar_calculate($db, array $input): array
    {
        $settings = kinas_solar_get_settings($db);
        $products = kinas_solar_get_products($db);

        // ----------------------------------------------------
        // 1. Normalize appliances
        // ----------------------------------------------------
        $appliances = [];
        $raw = $input['appliances'] ?? [];

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        foreach ($raw as $ap) {
            if (!is_array($ap)) {
                continue;
            }

            $name  = trim((string)($ap['name'] ?? ''));
            $qty   = (int)round((float)($ap['quantity'] ?? $ap['qty'] ?? 1));
            $watts = (float)($ap['watts'] ?? $ap['watt'] ?? 0);
            $hours = (float)($ap['hours'] ?? 0);

            if ($name === '' || $qty < 1 || $watts <= 0) {
                continue;
            }

            $hours = max(0, min(24, $hours));

            $appliances[] = [
                'name'     => $name,
                'quantity' => $qty,
                'watts'    => $watts,
                'hours'    => $hours,
            ];
        }

        if (empty($appliances)) {
            return ['success' => false, 'error' => 'Please add at least one appliance with a valid wattage.'];
        }

        $backupHours = (int)round((float)($input['backup_hours'] ?? 24));
        $backupHours = max(1, min(120, $backupHours));

        // ----------------------------------------------------
        // 2. Load & energy (real hours, not assumptions)
        // ----------------------------------------------------
        $totalLoadW = 0.0;
        $dailyWh    = 0.0;

        foreach ($appliances as $ap) {
            $totalLoadW += $ap['quantity'] * $ap['watts'];
            $dailyWh    += $ap['quantity'] * $ap['watts'] * $ap['hours'];
        }

        if ($totalLoadW <= 0 || $dailyWh <= 0) {
            return ['success' => false, 'error' => 'Total load calculation failed. Please check your appliance wattages and hours.'];
        }

        $dailyKwh       = $dailyWh / 1000;
        $designDailyKwh = $dailyKwh * (1 + ($settings['load_margin_pct'] / 100));

        // ----------------------------------------------------
        // 3. Technical sizing
        // ----------------------------------------------------
        $sunHours = max(1.0, (float)$settings['sun_hours_default']);
        $pr       = max(0.1, min(1.0, (float)$settings['pv_performance_ratio']));

        // PV array
        $requiredPvKw = $designDailyKwh / ($sunHours * $pr);

        // Inverter (peak-load driven)
        $requiredInverterKw = ($totalLoadW * max(0.1, (float)$settings['inverter_safety_factor'])) / 1000;

        // Battery (backup-hours driven)
        $usableKwh = $dailyKwh * ($backupHours / 24);
        $dod       = max(0.1, min(1.0, (float)$settings['battery_dod_pct'] / 100));
        $battEff   = max(0.1, min(1.0, (float)$settings['battery_efficiency_pct'] / 100));
        $requiredBatteryKwh = $usableKwh / ($dod * $battEff);

        $warnings = [];
        $items    = [];

        // ----------------------------------------------------
        // 4. Panel selection (live product or labelled fallback)
        // ----------------------------------------------------
        $panelProduct = null;
        foreach ($products as $p) {
            if ($p['product_type'] === 'panel' && (float)$p['panel_wattage_w'] > 0) {
                $panelProduct = $p;
                break;
            }
        }

        if ($panelProduct !== null) {
            $panelW         = (float)$panelProduct['panel_wattage_w'];
            $panelQty       = (int)max(1, ceil(($requiredPvKw * 1000) / $panelW));
            $panelUnitPrice = (float)$panelProduct['price'];
            $panelDesc      = (string)$panelProduct['title'];
            $panelListingId = (int)$panelProduct['listing_id'];
        } else {
            $panelW         = max(1.0, (float)$settings['default_panel_wattage']);
            $panelQty       = (int)max(1, ceil(($requiredPvKw * 1000) / $panelW));
            $panelUnitPrice = (float)$settings['default_panel_price'];
            $panelDesc      = ((int)$panelW) . 'W Monocrystalline Solar Panel (reference price — add a panel product in admin for live pricing)';
            $panelListingId = null;
            $warnings[]     = 'No solar panel product is configured in the catalogue. A reference panel price was used — configure a panel product for live approved pricing.';
        }

        $actualPvKw = ($panelQty * $panelW) / 1000;

        $items[] = [
            'type'        => 'panel',
            'listing_id'  => $panelListingId,
            'description' => $panelDesc,
            'qty'         => $panelQty,
            'unit_price'  => $panelUnitPrice,
            'line_total'  => $panelQty * $panelUnitPrice,
        ];

        // ----------------------------------------------------
        // 5. Power system: power station first, components second
        // ----------------------------------------------------
        $generators = array_values(array_filter($products, fn($p) => $p['product_type'] === 'generator'));
        $inverters  = array_values(array_filter($products, fn($p) => $p['product_type'] === 'inverter'));
        $batteries  = array_values(array_filter($products, fn($p) => $p['product_type'] === 'battery'));

        $selectedGenerator     = null;
        $selectedInverter      = null;
        $selectedBattery       = null;
        $batteryUnits          = 0;
        $recommendedInverterKw = 0.0;
        $recommendedBatteryKwh = 0.0;
        $powerSourceLabel      = '';

        // 5a. A power station that satisfies BOTH inverter and battery.
        foreach ($generators as $g) {
            if ((float)$g['continuous_kw'] >= $requiredInverterKw
                && (float)$g['usable_battery_kwh'] >= $requiredBatteryKwh) {
                $selectedGenerator = $g;
                break;
            }
        }

        if ($selectedGenerator !== null) {
            $recommendedInverterKw = (float)$selectedGenerator['continuous_kw'];
            $recommendedBatteryKwh = (float)$selectedGenerator['usable_battery_kwh'];
            $powerSourceLabel      = (string)$selectedGenerator['title'];

            $items[] = [
                'type'        => 'generator',
                'listing_id'  => (int)$selectedGenerator['listing_id'],
                'description' => $powerSourceLabel,
                'qty'         => 1,
                'unit_price'  => (float)$selectedGenerator['price'],
                'line_total'  => (float)$selectedGenerator['price'],
            ];
        } else {
            // 5b. Power station that covers the inverter but not the battery.
            $bestGen = null;
            foreach ($generators as $g) {
                if ((float)$g['continuous_kw'] >= $requiredInverterKw) {
                    if ($bestGen === null || (float)$g['usable_battery_kwh'] > (float)$bestGen['usable_battery_kwh']) {
                        $bestGen = $g;
                    }
                }
            }

            if ($bestGen !== null) {
                $selectedGenerator     = $bestGen;
                $recommendedInverterKw = (float)$bestGen['continuous_kw'];
                $recommendedBatteryKwh = (float)$bestGen['usable_battery_kwh'];
                $powerSourceLabel      = (string)$bestGen['title'];

                $items[] = [
                    'type'        => 'generator',
                    'listing_id'  => (int)$bestGen['listing_id'],
                    'description' => $powerSourceLabel,
                    'qty'         => 1,
                    'unit_price'  => (float)$bestGen['price'],
                    'line_total'  => (float)$bestGen['price'],
                ];

                if ($recommendedBatteryKwh < $requiredBatteryKwh) {
                    $warnings[] = 'The selected power station\'s usable battery ('
                        . number_format($recommendedBatteryKwh, 2)
                        . ' kWh) is below the estimated requirement ('
                        . number_format($requiredBatteryKwh, 2)
                        . ' kWh) for ' . $backupHours
                        . 'h backup. Consider expansion batteries, shorter backup, or a custom quote.';
                }
            } elseif (!empty($inverters) || !empty($batteries)) {
                // 5c. Component mode: separate inverter + battery bank.
                $sufficient = array_values(array_filter($inverters, fn($i) => (float)$i['continuous_kw'] >= $requiredInverterKw));

                if (!empty($sufficient)) {
                    $selectedInverter = $sufficient[0]; // priority/price ordered
                } elseif (!empty($inverters)) {
                    $selectedInverter = $inverters[count($inverters) - 1];
                    $warnings[] = 'No listed inverter meets the estimated peak load; the largest available inverter was selected. A custom quote is recommended.';
                }

                if ($selectedInverter !== null) {
                    $recommendedInverterKw = (float)$selectedInverter['continuous_kw'];
                    $items[] = [
                        'type'        => 'inverter',
                        'listing_id'  => (int)$selectedInverter['listing_id'],
                        'description' => (string)$selectedInverter['title'],
                        'qty'         => 1,
                        'unit_price'  => (float)$selectedInverter['price'],
                        'line_total'  => (float)$selectedInverter['price'],
                    ];
                }

                if (!empty($batteries)) {
                    $selectedBattery = $batteries[0];
                    $perUnit = max(0.001, (float)($selectedBattery['usable_battery_kwh'] ?: $selectedBattery['battery_capacity_kwh']));
                    $batteryUnits = (int)max(1, ceil($requiredBatteryKwh / $perUnit));
                    $recommendedBatteryKwh = $perUnit * $batteryUnits;

                    $items[] = [
                        'type'        => 'battery',
                        'listing_id'  => (int)$selectedBattery['listing_id'],
                        'description' => (string)$selectedBattery['title'],
                        'qty'         => $batteryUnits,
                        'unit_price'  => (float)$selectedBattery['price'],
                        'line_total'  => $batteryUnits * (float)$selectedBattery['price'],
                    ];
                } else {
                    $warnings[] = 'No battery product is configured; the battery bank must be quoted manually.';
                }
            } else {
                $warnings[] = 'No inverter/battery products are configured in the catalogue. This estimate covers panels and balance-of-system costs only — contact KINAS VOLT for the full system price.';
            }
        }

        // ----------------------------------------------------
        // 6. Balance-of-system costs (admin controlled)
        // ----------------------------------------------------
        $bos = [
            ['installation_cost', 'Installation'],
            ['cabling_cost',      'Cabling & Protection'],
            ['mounting_cost',     'Mounting Structure'],
            ['transport_cost',    'Transport & Logistics'],
        ];

        foreach ($bos as [$key, $label]) {
            $cost = (float)$settings[$key];
            if ($cost > 0) {
                $items[] = [
                    'type'        => 'cost',
                    'listing_id'  => null,
                    'description' => $label,
                    'qty'         => 1,
                    'unit_price'  => $cost,
                    'line_total'  => $cost,
                ];
            }
        }

        $grandTotal = 0.0;
        foreach ($items as $item) {
            $grandTotal += $item['line_total'];
        }

        // ----------------------------------------------------
        // 7. Financials & impact
        // ----------------------------------------------------
        $monthlyGenerationKwh   = $actualPvKw * $sunHours * 30 * $pr;
        $monthlyConsumptionKwh  = $dailyKwh * 30;
        $billableKwh            = min($monthlyGenerationKwh, $monthlyConsumptionKwh);
        $monthlySavings         = $billableKwh * max(0, (float)$settings['electricity_tariff_ngn']);
        $annualSavings          = $monthlySavings * 12;
        $paybackYears           = $annualSavings > 0 ? $grandTotal / $annualSavings : 0;
        $roi20                  = $grandTotal > 0 ? (($annualSavings * 20) / $grandTotal) * 100 : 0;
        $co2TonsYear            = ($dailyKwh * 365 * max(0, (float)$settings['co2_kg_per_kwh'])) / 1000;

        return [
            'success'                   => true,
            'appliances'                => $appliances,
            'backup_hours'              => $backupHours,
            'total_load_w'              => (int)round($totalLoadW),
            'daily_kwh'                 => round($dailyKwh, 2),
            'design_daily_kwh'          => round($designDailyKwh, 2),
            'required_pv_kw'            => round($requiredPvKw, 2),
            'recommended_pv_kw'         => round($actualPvKw, 2),
            'panels_qty'                => $panelQty,
            'panel_wattage_w'           => $panelW,
            'required_inverter_kw'      => round($requiredInverterKw, 2),
            'recommended_inverter_kw'   => round($recommendedInverterKw, 2),
            'required_battery_kwh'      => round($requiredBatteryKwh, 2),
            'recommended_battery_kwh'   => round($recommendedBatteryKwh, 2),
            'power_source_label'        => $powerSourceLabel,
            'items'                     => $items,
            'grand_total'               => round($grandTotal),
            'monthly_generation_kwh'    => round($monthlyGenerationKwh, 1),
            'monthly_consumption_kwh'   => round($monthlyConsumptionKwh, 1),
            'monthly_savings'           => round($monthlySavings),
            'annual_savings'            => round($annualSavings),
            'payback_years'             => round($paybackYears, 1),
            'roi_20_years'              => round($roi20, 1),
            'co2_tons_year'             => round($co2TonsYear, 2),
            'warnings'                  => $warnings,
            'settings_used'             => $settings,
        ];
    }
}

// ============================================================
// PROPOSAL PERSISTENCE
// ============================================================

if (!function_exists('kinas_solar_make_reference')) {
    function kinas_solar_make_reference(): string
    {
        return 'SOL-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

if (!function_exists('kinas_solar_save_proposal')) {
    /**
     * Stores the calculation as a proposal with line items.
     * Returns the reference, or null when the tables are missing.
     */
    function kinas_solar_save_proposal($db, array $calc, array $customer): ?string
    {
        if (!kinas_solar_table_exists($db, 'solar_proposals')) {
            return null;
        }

        $reference = kinas_solar_make_reference();

        try {
            $db->prepare("
                INSERT INTO solar_proposals
                (reference, full_name, phone, email, city_state, property_type,
                 backup_hours, user_id, total_load_w, daily_kwh, required_pv_kw,
                 panels_recommended, required_inverter_kw, required_battery_kwh,
                 total_cost, monthly_savings, payback_years, co2_tons_year,
                 status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())
            ")->execute([
                $reference,
                trim((string)($customer['full_name'] ?? '')),
                trim((string)($customer['phone'] ?? '')),
                trim((string)($customer['email'] ?? '')),
                trim((string)($customer['city_state'] ?? '')),
                trim((string)($customer['property_type'] ?? '')),
                (int)($calc['backup_hours'] ?? 24),
                isset($customer['user_id']) ? (int)$customer['user_id'] : null,
                (int)($calc['total_load_w'] ?? 0),
                (float)($calc['daily_kwh'] ?? 0),
                (float)($calc['required_pv_kw'] ?? 0),
                (int)($calc['panels_qty'] ?? 0),
                (float)($calc['required_inverter_kw'] ?? 0),
                (float)($calc['required_battery_kwh'] ?? 0),
                (float)($calc['grand_total'] ?? 0),
                (float)($calc['monthly_savings'] ?? 0),
                (float)($calc['payback_years'] ?? 0),
                (float)($calc['co2_tons_year'] ?? 0),
            ]);

            $proposalId = (int)$db->lastInsertId();

            if ($proposalId > 0 && kinas_solar_table_exists($db, 'solar_proposal_items')) {
                $itemStmt = $db->prepare("
                    INSERT INTO solar_proposal_items
                    (proposal_id, item_type, listing_id, description, qty, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($calc['items'] as $item) {
                    $itemStmt->execute([
                        $proposalId,
                        (string)($item['type'] ?? ''),
                        $item['listing_id'] !== null ? (int)$item['listing_id'] : null,
                        (string)($item['description'] ?? ''),
                        (int)($item['qty'] ?? 1),
                        (float)($item['unit_price'] ?? 0),
                        (float)($item['line_total'] ?? 0),
                    ]);
                }
            }

            return $reference;
        } catch (Throwable $e) {
            error_log('kinas_solar_save_proposal error: ' . $e->getMessage());
            return null;
        }
    }
}
